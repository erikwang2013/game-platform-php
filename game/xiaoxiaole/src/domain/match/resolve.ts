import type { Board, GameEvent, MatchRun, ResolveResult } from '../catalog/types';
import { cloneBoard } from '../board/grid';
import { applyGravity, refillBoard } from '../gravity';
import { scoreSameType } from '../score';
import { collectMatchCells, findSameTypeRuns } from './same-type';

const MAX_WAVES = 64;

export function resolveUntilStable(board: Board): ResolveResult {
  const events: GameEvent[] = [];
  let current = cloneBoard(board);
  let score = 0;
  let wave = 0;

  while (wave < MAX_WAVES) {
    const runs: MatchRun[] = findSameTypeRuns(current);
    if (runs.length === 0) {
      break;
    }
    if (wave > 0) {
      current.combo += 1;
      events.push({ type: 'Combo', combo: current.combo });
    } else if (current.combo < 1) {
      current.combo = 1;
    }

    const cells = collectMatchCells(current);
    events.push({ type: 'Matches', runs, combo: current.combo });
    score += scoreSameType(cells.length, current.combo);

    for (const cell of cells) {
      current.cells[cell.r]![cell.q]!.occupant = null;
    }
    events.push({ type: 'Cleared', cells, runs });

    const gravity = applyGravity(current);
    current = gravity.board;
    if (gravity.fell.length > 0) {
      events.push({ type: 'Fell', moves: gravity.fell });
    }

    const refill = refillBoard(current);
    current = refill.board;
    if (refill.spawned.length > 0) {
      events.push({ type: 'Refilled', spawned: refill.spawned });
    }
    wave += 1;
  }

  return {
    board: current,
    events,
    combo: wave === 0 ? board.combo : current.combo,
    score,
  };
}
