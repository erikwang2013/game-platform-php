import { describe, expect, it } from 'vitest';
import { BOARD_SIZE, createBoardFromGrid, getOccupant } from '../../src/domain/board';
import { applyGravity, refillBoard } from '../../src/domain/gravity';
import { createRng, pickSpecies } from '../../src/domain/rng';
import { P0_SPAWN_POOL } from '../../src/domain/catalog/p0-catalog';
import type { SpawnPool } from '../../src/domain/catalog/types';

const W = 'wheat';
const A = 'apple';
const H = 'hen';

function stableGrid(): string[][] {
  const cycle = [W, A, H];
  return Array.from({ length: BOARD_SIZE }, (_, r) =>
    Array.from({ length: BOARD_SIZE }, (_, q) => cycle[(q + r) % 3]!),
  );
}

const wheatPool: SpawnPool = {
  speciesIds: [W],
  weights: [1],
  maxApex: 1,
  apexUnlock: 5,
};

describe('F02 gravity', () => {
  it('compacts each column downward, sending gaps to the top', () => {
    const grid: (string | null)[][] = stableGrid();
    grid[0]![0] = A;
    grid[1]![0] = null;
    grid[2]![0] = W;
    const board = createBoardFromGrid(grid);
    const wheatUid = getOccupant(board, { q: 0, r: 2 })!.uid;
    const appleUid = getOccupant(board, { q: 0, r: 0 })!.uid;

    const { board: packed, fell } = applyGravity(board);

    expect(getOccupant(packed, { q: 0, r: 0 })).toBeNull();
    expect(getOccupant(packed, { q: 0, r: 1 })!.uid).toBe(appleUid);
    expect(getOccupant(packed, { q: 0, r: 2 })!.uid).toBe(wheatUid);
    expect(fell.some((f) => f.uid === appleUid && f.from.r === 0 && f.to.r === 1)).toBe(true);
    expect(fell.some((f) => f.uid === wheatUid)).toBe(false);
  });

  it('preserves bottom-to-top order when multiple holes exist', () => {
    const grid: (string | null)[][] = stableGrid();
    grid[3]![1] = null;
    grid[5]![1] = null;
    const topUid = getOccupant(createBoardFromGrid(grid), { q: 1, r: 0 })!.uid;
    const { board: packed } = applyGravity(createBoardFromGrid(grid));

    const column: (string | null)[] = [];
    for (let r = 0; r < 8; r++) {
      column.push(getOccupant(packed, { q: 1, r })?.uid ?? null);
    }
    const occupied = column.filter((id): id is string => id !== null);
    expect(occupied).toHaveLength(6);
    expect(column[0]).toBeNull();
    expect(column[1]).toBeNull();
    expect(column[2]).toBe(topUid);
  });

  it('does not move pieces that are already packed', () => {
    const board = createBoardFromGrid(stableGrid());
    const { board: packed, fell } = applyGravity(board);
    expect(fell).toHaveLength(0);
    for (let r = 0; r < 8; r++) {
      for (let q = 0; q < 8; q++) {
        expect(getOccupant(packed, { q, r })!.uid).toBe(getOccupant(board, { q, r })!.uid);
      }
    }
  });

  it('leaves a fully empty column empty', () => {
    const grid: (string | null)[][] = stableGrid();
    for (let r = 0; r < 8; r++) grid[r]![4] = null;
    const { board: packed, fell } = applyGravity(createBoardFromGrid(grid));
    expect(fell).toHaveLength(0);
    for (let r = 0; r < 8; r++) {
      expect(getOccupant(packed, { q: 4, r })).toBeNull();
    }
  });
});

describe('F02 refill', () => {
  it('fills empty cells from the top using the spawn pool', () => {
    const grid: (string | null)[][] = stableGrid();
    grid[0]![2] = null;
    grid[1]![2] = null;
    const packed = applyGravity(createBoardFromGrid(grid, { pool: wheatPool, seed: 1 })).board;
    const { board: filled, spawned } = refillBoard(packed);
    expect(getOccupant(filled, { q: 2, r: 0 })?.speciesId).toBe(W);
    expect(getOccupant(filled, { q: 2, r: 1 })?.speciesId).toBe(W);
    expect(spawned).toHaveLength(2);
    expect(spawned.every((item) => item.cell.q === 2)).toBe(true);
  });

  it('is deterministic for the same seed', () => {
    const grid: (string | null)[][] = stableGrid();
    for (let q = 0; q < 8; q++) grid[0]![q] = null;
    const a = refillBoard(applyGravity(createBoardFromGrid(grid, { seed: 77 })).board);
    const b = refillBoard(applyGravity(createBoardFromGrid(grid, { seed: 77 })).board);
    expect(a.spawned.map((item) => item.piece.speciesId)).toEqual(
      b.spawned.map((item) => item.piece.speciesId),
    );
    expect(a.spawned).toHaveLength(8);
  });

  it('consumes RNG left-to-right, top-to-bottom', () => {
    const grid: (string | null)[][] = stableGrid();
    grid[0]![0] = null;
    grid[0]![1] = null;
    const packed = applyGravity(createBoardFromGrid(grid, { seed: 64 })).board;
    const fresh = createRng(64);
    const first = pickSpecies(fresh, P0_SPAWN_POOL);
    const second = pickSpecies(fresh, P0_SPAWN_POOL);
    const { spawned } = refillBoard(packed);
    expect(spawned.map((item) => item.piece.speciesId)).toEqual([first, second]);
    expect(spawned[0]!.cell).toEqual({ q: 0, r: 0 });
    expect(spawned[1]!.cell).toEqual({ q: 1, r: 0 });
  });

  it('assigns unique uids to spawned pieces', () => {
    const grid: (string | null)[][] = stableGrid();
    for (let r = 0; r < 8; r++) grid[r]![0] = null;
    const { board } = refillBoard(applyGravity(createBoardFromGrid(grid, { seed: 2 })).board);
    const uids = new Set<string>();
    for (let r = 0; r < 8; r++) {
      for (let q = 0; q < 8; q++) {
        uids.add(getOccupant(board, { q, r })!.uid);
      }
    }
    expect(uids.size).toBe(64);
  });

  it('keeps P0 spawn pool at 3 species (max 8)', () => {
    expect(P0_SPAWN_POOL.speciesIds).toEqual(['wheat', 'apple', 'hen']);
    expect(P0_SPAWN_POOL.speciesIds.length).toBeLessThanOrEqual(8);
  });
});
