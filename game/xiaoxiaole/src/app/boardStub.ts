/**
 * Local stand-in used only when `src/domain` createBoard/trySwap are not implemented.
 * Domain owns the real match/gravity engine; this copy only keeps P0 playable.
 */
import type {
  Board,
  Cell,
  Coord,
  CreateBoardOptions,
  FallMove,
  GameEvent,
  PieceInstance,
  RefillSpawn,
  ResolveResult,
  TrySwapResult,
} from '../domain/catalog/types';
import { BOARD_SIZE } from '../domain/catalog/types';
import {
  P0_PIECE_DEFS,
  P0_SPAWN_POOL,
} from '../domain/catalog/p0-catalog';
import type { P0SpeciesId } from '../domain/catalog/types';
import type { BoardApi } from './BoardPort';
import { isOrthogonal } from './BoardPort';

function stepRng(state: number): { value: number; next: number } {
  let a = state | 0;
  a = (a + 0x6d2b79f5) | 0;
  let t = Math.imul(a ^ (a >>> 15), 1 | a);
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
  return { value: ((t ^ (t >>> 14)) >>> 0) / 4294967296, next: a >>> 0 };
}

function tileHeight(q: number, r: number): number {
  return (((q * 13 + r * 7) % 5) - 2) * 0.03;
}

function cloneBoard(board: Board): Board {
  return {
    size: board.size,
    combo: board.combo,
    seed: board.seed,
    rngState: board.rngState,
    nextUid: board.nextUid,
    pool: {
      speciesIds: [...board.pool.speciesIds],
      weights: [...board.pool.weights],
      maxApex: board.pool.maxApex,
      apexUnlock: board.pool.apexUnlock,
    },
    cells: board.cells.map((row) =>
      row.map((cell) => ({
        ...cell,
        overlay: { ...cell.overlay },
        occupant: cell.occupant
          ? { ...cell.occupant, def: cell.occupant.def, special: cell.occupant.special }
          : null,
      })),
    ),
  };
}

function emptyCell(q: number, r: number): Cell {
  return {
    q,
    r,
    height: tileHeight(q, r),
    occupant: null,
    overlay: { kind: 'none' },
    locked: false,
  };
}

function pickPiece(board: Board): PieceInstance {
  const { speciesIds, weights } = board.pool;
  const total = weights.reduce((sum, w) => sum + w, 0) || 1;
  const rolled = stepRng(board.rngState);
  board.rngState = rolled.next;
  let cursor = rolled.value * total;
  let speciesId: string = speciesIds[0] ?? 'wheat';
  for (let i = 0; i < speciesIds.length; i++) {
    cursor -= weights[i] ?? 0;
    if (cursor <= 0) {
      speciesId = speciesIds[i] ?? 'wheat';
      break;
    }
  }
  const def =
    P0_PIECE_DEFS[speciesId as P0SpeciesId] ?? P0_PIECE_DEFS.wheat;
  const uid = `p${board.nextUid}`;
  board.nextUid += 1;
  return { uid, speciesId: def.id, def, special: 'none' };
}

function speciesAt(board: Board, q: number, r: number): string | null {
  return board.cells[r]?.[q]?.occupant?.speciesId ?? null;
}

function detectMatchCells(board: Board): Coord[] {
  const size = board.size;
  const hit = new Set<string>();
  const mark = (q: number, r: number) => hit.add(`${q},${r}`);

  for (let r = 0; r < size; r++) {
    let run = 1;
    for (let q = 1; q <= size; q++) {
      const prev = speciesAt(board, q - 1, r);
      const cur = q < size ? speciesAt(board, q, r) : null;
      if (prev && cur === prev) run += 1;
      else {
        if (prev && run >= 3) {
          for (let k = q - run; k < q; k++) mark(k, r);
        }
        run = 1;
      }
    }
  }

  for (let q = 0; q < size; q++) {
    let run = 1;
    for (let r = 1; r <= size; r++) {
      const prev = speciesAt(board, q, r - 1);
      const cur = r < size ? speciesAt(board, q, r) : null;
      if (prev && cur === prev) run += 1;
      else {
        if (prev && run >= 3) {
          for (let k = r - run; k < r; k++) mark(q, k);
        }
        run = 1;
      }
    }
  }

  return [...hit].map((key) => {
    const [q, r] = key.split(',').map(Number);
    return { q, r };
  });
}

function applyGravity(board: Board, events: GameEvent[]): void {
  const size = board.size;
  const moves: FallMove[] = [];
  for (let q = 0; q < size; q++) {
    let write = size - 1;
    for (let r = size - 1; r >= 0; r--) {
      const piece = board.cells[r]![q]!.occupant;
      if (!piece) continue;
      if (r !== write) {
        moves.push({ uid: piece.uid, from: { q, r }, to: { q, r: write } });
        board.cells[write]![q]!.occupant = piece;
        board.cells[r]![q]!.occupant = null;
      }
      write -= 1;
    }
  }
  if (moves.length > 0) events.push({ type: 'Fell', moves });
}

function refill(board: Board, events: GameEvent[]): void {
  const size = board.size;
  const spawned: RefillSpawn[] = [];
  for (let q = 0; q < size; q++) {
    for (let r = 0; r < size; r++) {
      const cell = board.cells[r]![q]!;
      if (cell.occupant) continue;
      const piece = pickPiece(board);
      cell.occupant = piece;
      spawned.push({ cell: { q, r }, piece: { ...piece } });
    }
  }
  if (spawned.length > 0) events.push({ type: 'Refilled', spawned });
}

export function resolveUntilStable(board: Board): ResolveResult {
  const next = cloneBoard(board);
  const events: GameEvent[] = [];
  let combo = 0;
  let score = 0;
  while (true) {
    const cells = detectMatchCells(next);
    if (cells.length === 0) break;
    combo += 1;
    next.combo = combo;
    score += 10 * cells.length * combo;
    const runs = [{ kind: 'same' as const, cells: cells.map((c) => ({ ...c })) }];
    events.push({ type: 'Matches', runs, combo });
    events.push({ type: 'Cleared', cells: cells.map((c) => ({ ...c })), runs });
    for (const { q, r } of cells) {
      next.cells[r]![q]!.occupant = null;
    }
    applyGravity(next, events);
    refill(next, events);
    events.push({ type: 'Combo', combo });
  }
  return { board: next, events, combo, score };
}

export function createBoard(options: CreateBoardOptions): Board {
  const size = BOARD_SIZE;
  const board: Board = {
    size,
    combo: 0,
    seed: options.seed,
    rngState: options.seed >>> 0,
    nextUid: 1,
    pool: options.pool ?? {
      speciesIds: [...P0_SPAWN_POOL.speciesIds],
      weights: [...P0_SPAWN_POOL.weights],
      maxApex: P0_SPAWN_POOL.maxApex,
      apexUnlock: P0_SPAWN_POOL.apexUnlock,
    },
    cells: Array.from({ length: size }, (_, r) =>
      Array.from({ length: size }, (_, q) => emptyCell(q, r)),
    ),
  };
  for (let r = 0; r < size; r++) {
    for (let q = 0; q < size; q++) {
      board.cells[r]![q]!.occupant = pickPiece(board);
    }
  }
  for (let i = 0; i < 40 && detectMatchCells(board).length > 0; i++) {
    for (const pos of detectMatchCells(board)) {
      board.cells[pos.r]![pos.q]!.occupant = pickPiece(board);
    }
  }
  const settled = resolveUntilStable(board);
  return { ...settled.board, combo: 0 };
}

export function trySwap(board: Board, a: Coord, b: Coord): TrySwapResult {
  if (!isOrthogonal(a, b)) {
    return {
      accepted: false,
      spentMove: false,
      board,
      events: [{ type: 'RejectedSwap', a, b, reason: 'not_adjacent' }],
    };
  }
  const next = cloneBoard(board);
  const cellA = next.cells[a.r]?.[a.q];
  const cellB = next.cells[b.r]?.[b.q];
  if (!cellA?.occupant || !cellB?.occupant) {
    return {
      accepted: false,
      spentMove: false,
      board,
      events: [{ type: 'RejectedSwap', a, b, reason: 'empty' }],
    };
  }
  if (cellA.locked || cellB.locked) {
    return {
      accepted: false,
      spentMove: false,
      board,
      events: [{ type: 'RejectedSwap', a, b, reason: 'locked' }],
    };
  }
  const tmp = cellA.occupant;
  cellA.occupant = cellB.occupant;
  cellB.occupant = tmp;
  if (detectMatchCells(next).length === 0) {
    return {
      accepted: false,
      spentMove: false,
      board,
      events: [{ type: 'RejectedSwap', a, b, reason: 'no_match' }],
    };
  }
  next.combo = 0;
  const resolved = resolveUntilStable(next);
  return {
    accepted: true,
    spentMove: true,
    board: resolved.board,
    events: [{ type: 'Swapped', a, b }, ...resolved.events],
  };
}

export function createStubBoardApi(): BoardApi {
  return { createBoard, trySwap, resolveUntilStable };
}
