import { describe, expect, it } from 'vitest';
import { BOARD_SIZE, createBoardFromGrid, getOccupant, trySwap } from '../../src/domain/board';
import {
  collectMatchCells,
  findSameTypeRuns,
  hasMatch,
  resolveUntilStable,
} from '../../src/domain/match';
import { scoreSameType } from '../../src/domain/score';

const W = 'wheat';
const A = 'apple';
const H = 'hen';

function stableGrid(): string[][] {
  const cycle = [W, A, H];
  return Array.from({ length: BOARD_SIZE }, (_, r) =>
    Array.from({ length: BOARD_SIZE }, (_, q) => cycle[(q + r) % 3]!),
  );
}

function checkerGrid(): string[][] {
  return Array.from({ length: BOARD_SIZE }, (_, r) =>
    Array.from({ length: BOARD_SIZE }, (_, q) => ((q + r) % 2 === 0 ? A : H)),
  );
}

function cellKey(q: number, r: number): string {
  return `${q},${r}`;
}

describe('F02 same-type detection', () => {
  it('finds a horizontal run of length 3', () => {
    const grid = checkerGrid();
    grid[3]![2] = W;
    grid[3]![3] = W;
    grid[3]![4] = W;
    const board = createBoardFromGrid(grid);
    const runs = findSameTypeRuns(board);
    expect(runs.some((run) => run.speciesId === W && run.cells.length === 3)).toBe(true);
    const cells = collectMatchCells(board);
    expect(cells).toHaveLength(3);
    expect(cells.map((c) => cellKey(c.q, c.r)).sort()).toEqual(['2,3', '3,3', '4,3']);
  });

  it('finds a vertical run of length 3', () => {
    const grid = checkerGrid();
    grid[2]![0] = W;
    grid[3]![0] = W;
    grid[4]![0] = W;
    const board = createBoardFromGrid(grid);
    const cells = collectMatchCells(board);
    expect(cells).toHaveLength(3);
    expect(cells.every((c) => c.q === 0)).toBe(true);
  });

  it('does not count a diagonal line of 3', () => {
    const grid = stableGrid();
    grid[0]![0] = W;
    grid[1]![1] = W;
    grid[2]![2] = W;
    const board = createBoardFromGrid(grid);
    expect(hasMatch(board)).toBe(false);
    expect(collectMatchCells(board)).toHaveLength(0);
  });

  it('detects runs longer than 3', () => {
    const grid = checkerGrid();
    for (let q = 0; q < 5; q++) grid[7]![q] = W;
    const board = createBoardFromGrid(grid);
    const run = findSameTypeRuns(board).find((item) => item.cells.length === 5);
    expect(run).toBeDefined();
    expect(run!.speciesId).toBe(W);
  });

  it('clears each intersecting L-shape cell once', () => {
    const grid = checkerGrid();
    grid[0]![0] = W;
    grid[0]![1] = W;
    grid[0]![2] = W;
    grid[1]![0] = W;
    grid[2]![0] = W;
    const board = createBoardFromGrid(grid);
    const keys = collectMatchCells(board)
      .map((c) => cellKey(c.q, c.r))
      .sort();
    expect(keys).toEqual(['0,0', '0,1', '0,2', '1,0', '2,0']);
    expect(keys).toHaveLength(5);
  });

  it('clears each intersecting T-shape cell once', () => {
    const grid = checkerGrid();
    grid[0]![1] = W;
    grid[0]![2] = W;
    grid[0]![3] = W;
    grid[1]![2] = W;
    grid[2]![2] = W;
    const board = createBoardFromGrid(grid);
    const keys = collectMatchCells(board)
      .map((c) => cellKey(c.q, c.r))
      .sort();
    expect(keys).toEqual(['1,0', '2,0', '2,1', '2,2', '3,0']);
    expect(keys).toHaveLength(5);
  });

  it('finds two disjoint runs in one scan', () => {
    const grid = checkerGrid();
    grid[0]![0] = W;
    grid[0]![1] = W;
    grid[0]![2] = W;
    grid[7]![4] = H;
    grid[7]![5] = H;
    grid[7]![6] = H;
    const board = createBoardFromGrid(grid);
    expect(collectMatchCells(board)).toHaveLength(6);
    expect(findSameTypeRuns(board)).toHaveLength(2);
  });

  it('does not match mixed species in a line of 3', () => {
    const grid = stableGrid();
    grid[4]![0] = W;
    grid[4]![1] = A;
    grid[4]![2] = W;
    const board = createBoardFromGrid(grid);
    expect(hasMatch(board)).toBe(false);
  });

  it('does not match across empty gaps', () => {
    const grid: (string | null)[][] = checkerGrid();
    grid[0]![0] = W;
    grid[0]![1] = null;
    grid[0]![2] = W;
    grid[0]![3] = W;
    const board = createBoardFromGrid(grid);
    const wheatRow0 = collectMatchCells(board).filter((c) => c.r === 0 && c.q <= 3);
    expect(wheatRow0).toHaveLength(0);
  });
});

describe('F02 scoring', () => {
  it('scores same_3 as 10 * n * combo', () => {
    expect(scoreSameType(3, 1)).toBe(30);
    expect(scoreSameType(5, 2)).toBe(100);
    expect(scoreSameType(0, 1)).toBe(0);
  });
});

describe('F02–F03 resolveUntilStable', () => {
  it('clears a planted triple, packs downward, and refills the top', () => {
    const grid = checkerGrid();
    grid[7]![0] = W;
    grid[7]![1] = W;
    grid[7]![2] = W;
    const board = createBoardFromGrid(grid, { seed: 1 });
    const above = [
      getOccupant(board, { q: 0, r: 6 })!.uid,
      getOccupant(board, { q: 1, r: 6 })!.uid,
      getOccupant(board, { q: 2, r: 6 })!.uid,
    ];

    const result = resolveUntilStable(board);
    expect(result.combo).toBeGreaterThanOrEqual(1);
    expect(getOccupant(result.board, { q: 0, r: 7 })!.uid).toBe(above[0]);
    expect(getOccupant(result.board, { q: 1, r: 7 })!.uid).toBe(above[1]);
    expect(getOccupant(result.board, { q: 2, r: 7 })!.uid).toBe(above[2]);
    for (let r = 0; r < 8; r++) {
      for (let q = 0; q < 8; q++) {
        expect(getOccupant(result.board, { q, r })).not.toBeNull();
      }
    }
    expect(hasMatch(result.board)).toBe(false);
  });

  it('scores an L-shape once per unique cell at combo 1', () => {
    const grid = checkerGrid();
    grid[0]![0] = W;
    grid[0]![1] = W;
    grid[0]![2] = W;
    grid[1]![0] = W;
    grid[2]![0] = W;
    const board = createBoardFromGrid(grid, { seed: 3 });
    const result = resolveUntilStable(board);
    expect(result.score).toBeGreaterThanOrEqual(scoreSameType(5, 1));
    const firstClear = result.events.find((e) => e.type === 'Cleared');
    expect(firstClear?.type).toBe('Cleared');
    if (firstClear?.type === 'Cleared') {
      expect(firstClear.cells).toHaveLength(5);
    }
    const firstMatch = result.events.find((e) => e.type === 'Matches');
    expect(firstMatch?.type).toBe('Matches');
    if (firstMatch?.type === 'Matches') {
      expect(firstMatch.combo).toBe(1);
    }
  });

  it('increments combo for each cascade after the first in the same action', () => {
    const grid: (string | null)[][] = checkerGrid();
    grid[7]![0] = A;
    grid[7]![1] = A;
    grid[7]![2] = A;
    grid[7]![3] = H;
    grid[6]![0] = W;
    grid[6]![1] = W;
    grid[6]![2] = null;
    grid[5]![2] = W;
    const board = createBoardFromGrid(grid, { seed: 8, combo: 1 });
    const result = resolveUntilStable(board);
    const matchEvents = result.events.filter((e) => e.type === 'Matches');
    expect(matchEvents.length).toBeGreaterThanOrEqual(2);
    expect(matchEvents[0]?.type).toBe('Matches');
    expect(matchEvents[1]?.type).toBe('Matches');
    if (matchEvents[0]?.type === 'Matches' && matchEvents[1]?.type === 'Matches') {
      expect(matchEvents[0].combo).toBe(1);
      expect(matchEvents[1].combo).toBe(2);
    }
    expect(result.combo).toBeGreaterThanOrEqual(2);
    expect(result.score).toBeGreaterThanOrEqual(scoreSameType(3, 1) + scoreSameType(3, 2));
  });

  it('resets combo to 1 on the next successful player swap', () => {
    const gridA: (string | null)[][] = checkerGrid();
    gridA[7]![0] = A;
    gridA[7]![1] = A;
    gridA[7]![2] = A;
    gridA[7]![3] = H;
    gridA[6]![0] = W;
    gridA[6]![1] = W;
    gridA[6]![2] = null;
    gridA[5]![2] = W;
    const first = resolveUntilStable(createBoardFromGrid(gridA, { seed: 11, combo: 1 }));
    expect(first.combo).toBeGreaterThan(1);

    const gridB = checkerGrid();
    gridB[0]![0] = W;
    gridB[0]![1] = W;
    gridB[0]![2] = A;
    gridB[1]![2] = W;
    const swapped = trySwap(createBoardFromGrid(gridB, { seed: 12, combo: first.combo }), {
      q: 2,
      r: 0,
    }, { q: 2, r: 1 });
    expect(swapped.accepted).toBe(true);
    const firstMatch = swapped.events.find((e) => e.type === 'Matches');
    expect(firstMatch?.type).toBe('Matches');
    if (firstMatch?.type === 'Matches') {
      expect(firstMatch.combo).toBe(1);
    }
  });

  it('returns combo 0 extra work when the board has no runs', () => {
    const board = createBoardFromGrid(stableGrid(), { seed: 1 });
    const result = resolveUntilStable(board);
    expect(result.events).toHaveLength(0);
    expect(result.score).toBe(0);
    expect(hasMatch(result.board)).toBe(false);
  });
});
