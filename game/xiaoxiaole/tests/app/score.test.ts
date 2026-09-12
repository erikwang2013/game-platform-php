import { describe, expect, it } from 'vitest';
import { GameApp } from '../../src/app/GameApp';

/** Coord 在计分里只用到 length；Wave 未导出，测试侧按用到的字段就地声明 */
type Wave = { cleared: unknown[]; fell: unknown[]; refilled: unknown[]; combo: number };

/** 只跑 playWaves 的计分那几行，hud / anim / boardView 全用空替身顶掉，不碰 canvas 与 three */
function harness() {
  const app = new GameApp(null as unknown as HTMLCanvasElement, null as unknown as HTMLElement, { seed: 1 });
  const scores: number[] = [];
  const combos: number[] = [];
  const view = app as unknown as {
    playWaves(waves: Wave[]): Promise<void>;
    hud: { setScore(n: number): void; setCombo(n: number): void };
    anim: { clear(meshes: unknown[]): Promise<void>; fall(meshes: unknown[]): Promise<void> };
    boardView: {
      takeMeshes(cleared: unknown[]): unknown[];
      detach(meshes: unknown[]): void;
      applyFalls(fell: unknown[]): unknown[];
      spawnRefills(refilled: unknown[]): unknown[];
    };
  };
  view.hud = { setScore: (n) => scores.push(n), setCombo: (n) => combos.push(n) };
  view.anim = { clear: async () => {}, fall: async () => {} };
  view.boardView = {
    takeMeshes: () => [],
    detach: () => {},
    applyFalls: () => [],
    spawnRefills: () => [],
  };
  return { view, scores, combos };
}

describe('GameApp 计分', () => {
  it('每个 wave 按 cleared.length 只累加一次：10 * cleared.length * max(combo, 1)', async () => {
    const { view, scores, combos } = harness();
    await view.playWaves([
      { cleared: [0, 1, 2], fell: [], refilled: [], combo: 1 },
      { cleared: [0, 1], fell: [], refilled: [], combo: 2 },
      { cleared: [0], fell: [], refilled: [], combo: 0 },
      { cleared: [], fell: [], refilled: [], combo: 5 }, // 空消除不涨分
    ]);
    expect(scores).toEqual([30, 70, 80, 80]);
    expect(combos).toEqual([1, 2, 0, 5]);
  });
});
