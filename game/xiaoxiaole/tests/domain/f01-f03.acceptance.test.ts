import { describe, expect, it } from 'vitest';
import {
  BOARD_SIZE,
  P0_PIECE_DEFS,
  P0_SPAWN_POOL,
  createBoard,
  trySwap,
  type Board,
  type Coord,
  type CreateBoardOptions,
  type GameEvent,
  type P0SpeciesId,
  type TrySwapResult,
} from '../../src/domain';

/**
 * F01–F03 acceptance (functional-design.md §5, §6.1, §11).
 *
 * Intended API:
 *   createBoard({ seed, species, width, height })
 *   trySwap(board, a, b) -> { board, events, spentMove }
 *
 * Scaffold currently types trySwap as `{ accepted }` and createBoard as
 * `{ seed, pool }`. Tests accept either spentMove or accepted.
 *
 * cells[r][q], r=0 is the top (gravity packs toward r=7).
 */

type Grid = P0SpeciesId[][];

const SPECIES: P0SpeciesId[] = ['wheat', 'apple', 'hen'];

function blocked(fn: string, err: unknown): never {
  const msg = err instanceof Error ? err.message : String(err);
  throw new Error(`BLOCKED: ${fn} (${msg})`);
}

function pos(q: number, r: number): Coord {
  return { q, r };
}

function spentMoveOf(result: TrySwapResult & { spentMove?: boolean }): boolean {
  if (typeof result.spentMove === 'boolean') return result.spentMove;
  if (typeof result.accepted === 'boolean') return result.accepted;
  throw new Error('BLOCKED: trySwap result missing spentMove/accepted');
}

function speciesAt(board: Board, q: number, r: number): string | null {
  return board.cells[r]?.[q]?.occupant?.speciesId ?? null;
}

function snapshot(board: Board): string {
  return board.cells
    .map((row) => row.map((cell) => cell.occupant?.speciesId ?? '.').join(''))
    .join('\n');
}

function cycleGrid(): Grid {
  return Array.from({ length: BOARD_SIZE }, (_, r) =>
    Array.from({ length: BOARD_SIZE }, (_, q) => SPECIES[(q + r) % 3]!),
  );
}

/** Bottom row becomes W W W after swapping apple at (2,7) with wheat at (3,7). */
function wheatClearGrid(): Grid {
  const grid = cycleGrid();
  grid[7][0] = 'wheat';
  grid[7][1] = 'wheat';
  grid[7][2] = 'apple';
  grid[7][3] = 'wheat';
  return grid;
}

/**
 * Swap hen (2,6) with wheat (3,6) → four wheat on row 6.
 * After gravity, apples at (2,4),(2,5),(2,7) pack into a vertical 3 at col 2.
 */
function cascadeGrid(): Grid {
  const grid = cycleGrid();
  grid[6][0] = 'wheat';
  grid[6][1] = 'wheat';
  grid[6][2] = 'hen';
  grid[6][3] = 'wheat';
  grid[4][2] = 'apple';
  grid[5][2] = 'apple';
  grid[7][2] = 'apple';
  return grid;
}

function overlay(board: Board, grid: Grid): Board {
  const cells = board.cells.map((row, r) =>
    row.map((cell, q) => {
      const speciesId = grid[r]?.[q];
      if (!speciesId) return { ...cell };
      const def = P0_PIECE_DEFS[speciesId];
      return {
        ...cell,
        q,
        r,
        occupant: {
          uid: `fix-${q}-${r}`,
          speciesId,
          def,
          special: 'none' as const,
        },
      };
    }),
  );
  return { ...board, cells, combo: 0 };
}

function matchesGrid(board: Board, grid: Grid): boolean {
  for (let r = 0; r < BOARD_SIZE; r++) {
    for (let q = 0; q < BOARD_SIZE; q++) {
      if (speciesAt(board, q, r) !== grid[r][q]) return false;
    }
  }
  return true;
}

function createOptions(grid?: Grid): CreateBoardOptions {
  return {
    seed: 1,
    species: SPECIES,
    width: 8,
    height: 8,
    pool: P0_SPAWN_POOL,
    grid,
  } as CreateBoardOptions;
}

function setup(grid?: Grid): Board {
  if (typeof createBoard !== 'function') {
    throw new Error('BLOCKED: createBoard is not exported from src/domain');
  }
  let board: Board;
  try {
    board = createBoard(createOptions(grid));
  } catch (err) {
    blocked('createBoard', err);
  }
  if (!board?.cells?.length) {
    throw new Error('BLOCKED: createBoard did not return an 8×8 cells snapshot');
  }
  if (grid && !matchesGrid(board, grid)) {
    board = overlay(board, grid);
  }
  if (grid && !matchesGrid(board, grid)) {
    throw new Error(
      'BLOCKED: cannot construct fixture board (createBoard ignores grid; overlay failed)',
    );
  }
  return board;
}

function swap(board: Board, a: Coord, b: Coord): TrySwapResult & { spentMove?: boolean } {
  if (typeof trySwap !== 'function') {
    throw new Error('BLOCKED: trySwap is not exported from src/domain');
  }
  try {
    return trySwap(board, a, b);
  } catch (err) {
    blocked('trySwap', err);
  }
}

function clearedCoords(events: GameEvent[]): Coord[] {
  const out: Coord[] = [];
  for (const event of events) {
    if (event.type === 'Cleared') out.push(...event.cells);
    if (event.type === 'Matches') {
      for (const run of event.runs) out.push(...run.cells);
    }
  }
  return out;
}

function hasCoord(coords: Coord[], q: number, r: number): boolean {
  return coords.some((c) => c.q === q && c.r === r);
}

function maxCombo(result: TrySwapResult): number {
  let max = result.board.combo ?? 0;
  for (const event of result.events) {
    if (event.type === 'Combo' || event.type === 'Matches') {
      max = Math.max(max, event.combo);
    }
  }
  return max;
}

describe('F01–F03 acceptance', () => {
  it('exports createBoard and trySwap', () => {
    if (typeof createBoard !== 'function' || typeof trySwap !== 'function') {
      throw new Error(
        'BLOCKED: expected createBoard({ seed, species, width, height }) and trySwap(board, a, b) from src/domain',
      );
    }
    expect(typeof createBoard).toBe('function');
    expect(typeof trySwap).toBe('function');
  });

  it('rejects a diagonal swap', () => {
    const board = setup(cycleGrid());
    const before = snapshot(board);
    const result = swap(board, pos(0, 0), pos(1, 1));

    expect(spentMoveOf(result)).toBe(false);
    expect(snapshot(result.board)).toBe(before);
    const rejected = result.events.filter((e) => e.type === 'RejectedSwap');
    if (rejected.length > 0) {
      expect(rejected[0]?.reason).toBe('not_adjacent');
    }
  });

  it('reverts a swap with no match and does not spend a move', () => {
    const board = setup(cycleGrid());
    const before = snapshot(board);
    const a = pos(0, 0);
    const b = pos(1, 0);
    expect(speciesAt(board, a.q, a.r)).toBe('wheat');
    expect(speciesAt(board, b.q, b.r)).toBe('apple');

    const result = swap(board, a, b);

    expect(spentMoveOf(result)).toBe(false);
    expect(snapshot(result.board)).toBe(before);
    expect(speciesAt(result.board, a.q, a.r)).toBe('wheat');
    expect(speciesAt(result.board, b.q, b.r)).toBe('apple');
  });

  it('clears three wheat in a row', () => {
    const board = setup(wheatClearGrid());
    const result = swap(board, pos(2, 7), pos(3, 7));

    expect(spentMoveOf(result)).toBe(true);
    const cleared = clearedCoords(result.events);
    expect(cleared.length).toBeGreaterThan(0);
    expect(hasCoord(cleared, 0, 7)).toBe(true);
    expect(hasCoord(cleared, 1, 7)).toBe(true);
    expect(hasCoord(cleared, 2, 7)).toBe(true);
    const sameWheat = result.events.some(
      (e) =>
        (e.type === 'Matches' || e.type === 'Cleared') &&
        e.runs.some((run) => run.kind === 'same' && run.speciesId === 'wheat'),
    );
    expect(sameWheat).toBe(true);
  });

  it('compacts pieces down with gravity after a clear', () => {
    const board = setup(wheatClearGrid());
    const hen = board.cells[6][2]?.occupant;
    expect(hen?.speciesId).toBe('hen');
    const henUid = hen?.uid;
    expect(henUid).toBeTruthy();

    const result = swap(board, pos(2, 7), pos(3, 7));
    expect(spentMoveOf(result)).toBe(true);

    const firstClear = result.events.find((e) => e.type === 'Cleared');
    expect(firstClear?.type).toBe('Cleared');
    const fellMoves = result.events
      .filter((e) => e.type === 'Fell')
      .flatMap((e) => e.moves);
    expect(fellMoves.length).toBeGreaterThan(0);

    const henFall = fellMoves.find((m) => m.uid === henUid);
    expect(henFall).toBeTruthy();
    expect(henFall!.from).toEqual(pos(2, 6));
    expect(
      firstClear &&
        hasCoord(firstClear.cells, henFall!.to.q, henFall!.to.r),
    ).toBe(true);
    expect(henFall!.to.r).toBeGreaterThan(henFall!.from.r);
  });

  it('refills empty top cells after gravity', () => {
    const board = setup(wheatClearGrid());
    const result = swap(board, pos(2, 7), pos(3, 7));
    expect(spentMoveOf(result)).toBe(true);

    const refilled = result.events.filter((e) => e.type === 'Refilled');
    expect(refilled.length).toBeGreaterThan(0);
    const spawned = refilled.flatMap((e) => e.spawned);
    expect(spawned.length).toBeGreaterThan(0);
    expect(spawned.every((s) => SPECIES.includes(s.piece.speciesId as P0SpeciesId))).toBe(
      true,
    );

    for (const row of result.board.cells) {
      for (const cell of row) {
        expect(cell.occupant, `empty cell at q=${cell.q} r=${cell.r}`).not.toBeNull();
      }
    }
    const topSpawns = spawned.filter((s) => s.cell.r === 0);
    expect(topSpawns.length).toBeGreaterThan(0);
  });

  it('increments combo above 1 when a second match forms after refill', () => {
    const board = setup(cascadeGrid());
    const result = swap(board, pos(2, 6), pos(3, 6));

    expect(spentMoveOf(result)).toBe(true);

    const matchRounds = result.events.filter((e) => e.type === 'Matches');
    const clearRounds = result.events.filter((e) => e.type === 'Cleared');
    expect(Math.max(matchRounds.length, clearRounds.length)).toBeGreaterThanOrEqual(2);

    const combo = maxCombo(result);
    expect(combo).toBeGreaterThan(1);

    const firstCleared = clearRounds[0] ? clearRounds[0].cells : [];
    const laterCleared = clearRounds.slice(1).flatMap((e) => e.cells);
    const appleCascade =
      laterCleared.some((c) => c.q === 2) ||
      matchRounds.some(
        (e) =>
          e.combo > 1 &&
          e.runs.some((run) => run.speciesId === 'apple' || run.cells.some((c) => c.q === 2)),
      );
    expect(appleCascade || laterCleared.length > 0).toBe(true);
    expect(firstCleared.length).toBeGreaterThan(0);
  });
});
