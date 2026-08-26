import { describe, expect, it } from 'vitest';
import {
  BOARD_SIZE,
  areOrthogonalAdjacent,
  createBoard,
  createBoardFromGrid,
  createEmptyBoard,
  getOccupant,
  isInBounds,
  selectCell,
  trySwap,
} from '../../src/domain/board';
import { hasMatch } from '../../src/domain/match';

const W = 'wheat';
const A = 'apple';
const H = 'hen';

/** Period-3 lattice: no horizontal or vertical run of 3. r=0 is top. */
function stableGrid(): string[][] {
  const cycle = [W, A, H];
  return Array.from({ length: BOARD_SIZE }, (_, r) =>
    Array.from({ length: BOARD_SIZE }, (_, q) => cycle[(q + r) % 3]!),
  );
}

/** Two-color checkerboard: safe canvas for planting isolated wheat runs. */
function checkerGrid(): string[][] {
  return Array.from({ length: BOARD_SIZE }, (_, r) =>
    Array.from({ length: BOARD_SIZE }, (_, q) => ((q + r) % 2 === 0 ? A : H)),
  );
}

function speciesAt(board: ReturnType<typeof createEmptyBoard>, q: number, r: number): string | null {
  return getOccupant(board, { q, r })?.speciesId ?? null;
}

describe('board grid', () => {
  it('creates an 8×8 empty board', () => {
    const board = createEmptyBoard();
    expect(board.size).toBe(8);
    expect(board.cells).toHaveLength(8);
    expect(board.cells.every((row) => row.length === 8)).toBe(true);
    for (let r = 0; r < 8; r++) {
      for (let q = 0; q < 8; q++) {
        expect(board.cells[r]![q]!.occupant).toBeNull();
        expect(board.cells[r]![q]!.q).toBe(q);
        expect(board.cells[r]![q]!.r).toBe(r);
        expect(board.cells[r]![q]!.locked).toBe(false);
      }
    }
  });

  it('fills an 8×8 board from seed with no opening matches', () => {
    const board = createBoard({ seed: 20260819 });
    expect(board.size).toBe(8);
    expect(board.seed).toBe(20260819);
    for (let r = 0; r < 8; r++) {
      for (let q = 0; q < 8; q++) {
        expect(getOccupant(board, { q, r })).not.toBeNull();
      }
    }
    expect(hasMatch(board)).toBe(false);
  });

  it('is deterministic for the same seed', () => {
    const a = createBoard({ seed: 11 });
    const b = createBoard({ seed: 11 });
    for (let r = 0; r < 8; r++) {
      for (let q = 0; q < 8; q++) {
        expect(speciesAt(a, q, r)).toBe(speciesAt(b, q, r));
      }
    }
  });

  it('creates a board from a species grid with unique piece uids', () => {
    const board = createBoardFromGrid(stableGrid());
    const uids = new Set<string>();
    for (let r = 0; r < 8; r++) {
      for (let q = 0; q < 8; q++) {
        const piece = getOccupant(board, { q, r });
        expect(piece).not.toBeNull();
        uids.add(piece!.uid);
      }
    }
    expect(uids.size).toBe(64);
    expect(speciesAt(board, 0, 0)).toBe(W);
    expect(speciesAt(board, 1, 0)).toBe(A);
    expect(speciesAt(board, 2, 0)).toBe(H);
  });

  it('rejects a grid that is not 8×8', () => {
    expect(() => createBoardFromGrid([[W]])).toThrow();
  });
});

describe('F01 adjacency', () => {
  it('allows orthogonal neighbors only', () => {
    expect(areOrthogonalAdjacent({ q: 3, r: 3 }, { q: 4, r: 3 })).toBe(true);
    expect(areOrthogonalAdjacent({ q: 3, r: 3 }, { q: 2, r: 3 })).toBe(true);
    expect(areOrthogonalAdjacent({ q: 3, r: 3 }, { q: 3, r: 4 })).toBe(true);
    expect(areOrthogonalAdjacent({ q: 3, r: 3 }, { q: 3, r: 2 })).toBe(true);
  });

  it('rejects diagonal, distant, and identical cells', () => {
    expect(areOrthogonalAdjacent({ q: 0, r: 0 }, { q: 1, r: 1 })).toBe(false);
    expect(areOrthogonalAdjacent({ q: 0, r: 0 }, { q: 0, r: 2 })).toBe(false);
    expect(areOrthogonalAdjacent({ q: 0, r: 0 }, { q: 2, r: 0 })).toBe(false);
    expect(areOrthogonalAdjacent({ q: 4, r: 4 }, { q: 4, r: 4 })).toBe(false);
  });

  it('treats board corners and edges as in-bounds', () => {
    expect(isInBounds({ q: 0, r: 0 })).toBe(true);
    expect(isInBounds({ q: 7, r: 7 })).toBe(true);
    expect(isInBounds({ q: 0, r: 7 })).toBe(true);
    expect(isInBounds({ q: 7, r: 0 })).toBe(true);
    expect(isInBounds({ q: -1, r: 0 })).toBe(false);
    expect(isInBounds({ q: 0, r: 8 })).toBe(false);
    expect(isInBounds({ q: 8, r: 0 })).toBe(false);
  });
});

describe('F01 trySwap', () => {
  it('reverts a swap that creates no 3+ same-type run and does not spend a move', () => {
    const board = createBoardFromGrid(stableGrid());
    const result = trySwap(board, { q: 0, r: 0 }, { q: 1, r: 0 });
    expect(result.accepted).toBe(false);
    expect(result.spentMove).toBe(false);
    expect(speciesAt(result.board, 0, 0)).toBe(W);
    expect(speciesAt(result.board, 1, 0)).toBe(A);
    expect(result.board.combo).toBe(board.combo);
    expect(result.events.some((e) => e.type === 'RejectedSwap')).toBe(true);
    expect(hasMatch(result.board)).toBe(false);
  });

  it('rejects a diagonal swap without mutating occupancy', () => {
    const board = createBoardFromGrid(stableGrid());
    const result = trySwap(board, { q: 0, r: 0 }, { q: 1, r: 1 });
    expect(result.accepted).toBe(false);
    expect(result.spentMove).toBe(false);
    expect(speciesAt(result.board, 0, 0)).toBe(W);
    expect(speciesAt(result.board, 1, 1)).toBe(H);
    const rejected = result.events.find((e) => e.type === 'RejectedSwap');
    expect(rejected?.type).toBe('RejectedSwap');
    if (rejected?.type === 'RejectedSwap') {
      expect(rejected.reason).toBe('not_adjacent');
    }
  });

  it('rejects out-of-bounds and same-cell swaps', () => {
    const board = createBoardFromGrid(stableGrid());
    expect(trySwap(board, { q: 0, r: 0 }, { q: -1, r: 0 }).accepted).toBe(false);
    expect(trySwap(board, { q: 7, r: 7 }, { q: 7, r: 8 }).spentMove).toBe(false);
    expect(trySwap(board, { q: 2, r: 2 }, { q: 2, r: 2 }).accepted).toBe(false);
  });

  it('accepts an orthogonal swap that forms a same-type run and spends a move', () => {
    const grid = checkerGrid();
    grid[0]![0] = W;
    grid[0]![1] = W;
    grid[0]![2] = A;
    grid[1]![2] = W;
    const board = createBoardFromGrid(grid);
    expect(hasMatch(board)).toBe(false);

    const result = trySwap(board, { q: 2, r: 0 }, { q: 2, r: 1 });
    expect(result.accepted).toBe(true);
    expect(result.spentMove).toBe(true);
    expect(result.events.some((e) => e.type === 'Swapped')).toBe(true);
    expect(result.events.some((e) => e.type === 'Cleared')).toBe(true);
    expect(hasMatch(result.board)).toBe(false);
    expect(speciesAt(board, 2, 0)).toBe(A);
  });

  it('resolves gravity and refill after an accepted swap', () => {
    const grid = checkerGrid();
    grid[0]![0] = W;
    grid[0]![1] = W;
    grid[0]![2] = A;
    grid[1]![2] = W;
    const board = createBoardFromGrid(grid);
    const result = trySwap(board, { q: 2, r: 0 }, { q: 2, r: 1 });
    expect(result.accepted).toBe(true);
    expect(result.events.some((e) => e.type === 'Refilled')).toBe(true);
    for (let r = 0; r < 8; r++) {
      for (let q = 0; q < 8; q++) {
        expect(getOccupant(result.board, { q, r })).not.toBeNull();
      }
    }
  });

  it('does not treat hen + wheat + wheat as a legal P0 match', () => {
    const grid = stableGrid();
    grid[0]![0] = H;
    grid[0]![1] = W;
    grid[0]![2] = W;
    grid[0]![3] = A;
    const board = createBoardFromGrid(grid);
    expect(hasMatch(board)).toBe(false);
    const result = trySwap(board, { q: 6, r: 6 }, { q: 7, r: 6 });
    expect(result.accepted).toBe(false);
    expect(result.spentMove).toBe(false);
  });
});

describe('F01 selectCell', () => {
  it('selects an occupied cell, then requests swap on an orthogonal neighbor', () => {
    const board = createBoardFromGrid(stableGrid());
    const selected = selectCell(board, null, { q: 0, r: 0 });
    expect(selected).toEqual({ action: 'select', selection: { q: 0, r: 0 } });
    const swap = selectCell(board, { q: 0, r: 0 }, { q: 1, r: 0 });
    expect(swap.action).toBe('swap');
    if (swap.action === 'swap') {
      expect(swap.a).toEqual({ q: 0, r: 0 });
      expect(swap.b).toEqual({ q: 1, r: 0 });
    }
  });

  it('does not request a diagonal swap', () => {
    const board = createBoardFromGrid(stableGrid());
    const result = selectCell(board, { q: 0, r: 0 }, { q: 1, r: 1 });
    expect(result.action).not.toBe('swap');
  });
});
