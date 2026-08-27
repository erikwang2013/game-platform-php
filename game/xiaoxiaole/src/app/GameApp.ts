import type { Board, Coord, GameEvent } from '../domain/catalog/types';
import { isOrthogonal, sameCell, type BoardApi } from './BoardPort';
import { GameStateMachine } from './GameStateMachine';
import { loadBoardApi } from './loadBoardApi';
import { HudOverlay } from '../hud/HudOverlay';
import { SceneRoot } from '../render/SceneRoot';
import { CameraRig } from '../render/CameraRig';
import { addFarmLights } from '../render/Lights';
import { BoardView } from '../render/BoardView';
import { PieceFactory } from '../render/PieceFactory';
import { InputRaycaster } from '../render/InputRaycaster';
import { AnimationQueue } from '../runtime/AnimationQueue';
import { CommandBus } from '../runtime/CommandBus';
import { EventLog } from '../runtime/EventLog';

type Wave = {
  cleared: Coord[];
  fell: Extract<GameEvent, { type: 'Fell' }>['moves'];
  refilled: Extract<GameEvent, { type: 'Refilled' }>['spawned'];
  combo: number;
};

export class GameApp {
  private readonly sm = new GameStateMachine();
  private readonly commands = new CommandBus();
  private readonly log = new EventLog();
  private readonly anim = new AnimationQueue();
  private api!: BoardApi;
  private board!: Board;
  private sceneRoot!: SceneRoot;
  private cameraRig!: CameraRig;
  private boardView!: BoardView;
  private hud!: HudOverlay;
  private selected: Coord | null = null;
  private score = 0;

  constructor(
    private readonly canvas: HTMLCanvasElement,
    private readonly hudRoot: HTMLElement,
    private readonly options: { seed: number },
  ) {}

  start(): void {
    this.sm.set('Boot');
    this.api = loadBoardApi();
    this.board = this.api.createBoard({ seed: this.options.seed });
    this.hud = new HudOverlay(this.hudRoot);
    this.sceneRoot = new SceneRoot(this.canvas);
    this.cameraRig = new CameraRig(window.innerWidth / Math.max(window.innerHeight, 1));
    this.cameraRig.lookAtBoard(this.board.size);
    addFarmLights(this.sceneRoot.scene);
    const factory = new PieceFactory();
    this.boardView = new BoardView(this.sceneRoot.scene, factory);
    this.boardView.mount(this.board);
    new InputRaycaster(
      this.canvas,
      this.cameraRig.camera,
      () => this.boardView.getTargets(),
      (pos) => this.onCell(pos),
    );
    window.addEventListener('resize', this.onResize);
    this.sm.set('Idle');
    this.loop();
  }

  private readonly loop = (): void => {
    requestAnimationFrame(this.loop);
    this.sceneRoot.render(this.cameraRig.camera);
  };

  private readonly onResize = (): void => {
    this.sceneRoot.resize();
    this.cameraRig.resize(window.innerWidth / Math.max(window.innerHeight, 1));
  };

  private onCell(pos: Coord): void {
    if (!this.sm.canAcceptInput()) return;
    if (this.sm.phase === 'Idle') {
      this.pick(pos);
      return;
    }
    if (!this.selected) {
      this.pick(pos);
      return;
    }
    if (sameCell(this.selected, pos)) {
      this.clearPick();
      this.sm.set('Idle');
      return;
    }
    if (!isOrthogonal(this.selected, pos)) {
      this.pick(pos);
      return;
    }
    const a = this.selected;
    this.clearPick();
    void this.performSwap(a, pos).catch((err: unknown) => {
      console.error('[xiaoxiaole] swap failed', err);
      this.sm.set('Idle');
    });
  }

  private pick(pos: Coord): void {
    this.selected = pos;
    const mesh = this.boardView.setSelected(pos);
    if (mesh) this.anim.pulse(mesh);
    this.sm.set('Selected');
    this.commands.dispatch({ type: 'Select', pos });
  }

  private clearPick(): void {
    if (this.selected) {
      const mesh = this.boardView.getMesh(this.selected);
      if (mesh) this.anim.stopPulse(mesh);
    }
    this.selected = null;
    this.boardView.setSelected(null);
  }

  private async performSwap(a: Coord, b: Coord): Promise<void> {
    const meshA = this.boardView.getMesh(a);
    const meshB = this.boardView.getMesh(b);
    if (!meshA || !meshB) {
      this.sm.set('Idle');
      return;
    }
    this.sm.set('SwapAnim');
    this.commands.dispatch({ type: 'Swap', a, b });
    await this.anim.swap(meshA, meshB);

    this.sm.set('ResolveLogic');
    const result = this.api.trySwap(this.board, a, b);
    if (!result.accepted) {
      await this.anim.swap(meshA, meshB);
      this.sm.set('Idle');
      return;
    }

    this.boardView.swapSlots(a, b);
    this.board = result.board;
    let events = result.events;
    const resolvedAlready = events.some((e) => e.type === 'Cleared' || e.type === 'Matches');
    if (!resolvedAlready) {
      const extra = this.api.resolveUntilStable(this.board);
      this.board = extra.board;
      events = events.concat(extra.events);
    }
    this.log.append(events);
    await this.playWaves(groupWaves(events));
    this.hud.setScore(this.score);
    this.sm.set('Idle');
  }

  private async playWaves(waves: Wave[]): Promise<void> {
    for (const wave of waves) {
      this.sm.set('ResolveLogic');
      this.hud.setCombo(wave.combo);
      this.score += 10 * wave.cleared.length * Math.max(wave.combo, 1);
      this.hud.setScore(this.score);

      this.sm.set('ClearAnim');
      const dying = this.boardView.takeMeshes(wave.cleared);
      await this.anim.clear(dying);
      this.boardView.detach(dying);

      this.sm.set('GravityAnim');
      await this.anim.fall(this.boardView.applyFalls(wave.fell));

      this.sm.set('RefillAnim');
      await this.anim.fall(this.boardView.spawnRefills(wave.refilled));
    }
  }
}

function groupWaves(events: GameEvent[]): Wave[] {
  const waves: Wave[] = [];
  let current = emptyWave();
  const flush = (): void => {
    if (current.cleared.length || current.fell.length || current.refilled.length) {
      waves.push(current);
      current = emptyWave();
    }
  };
  for (const event of events) {
    switch (event.type) {
      case 'Matches':
        flush();
        current.combo = event.combo;
        break;
      case 'Cleared':
        current.cleared.push(...event.cells);
        break;
      case 'Fell':
        current.fell.push(...event.moves);
        break;
      case 'Refilled':
        current.refilled.push(...event.spawned);
        break;
      case 'Combo':
        // Domain emits Combo at the start of cascade N+1, before Matches.
        // Flush the previous wave first so its combo is not overwritten.
        flush();
        current.combo = event.combo;
        break;
      default:
        break;
    }
  }
  flush();
  return waves;
}

function emptyWave(): Wave {
  return { cleared: [], fell: [], refilled: [], combo: 1 };
}
