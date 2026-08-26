/** Thin client port. Canonical types live in `src/domain/catalog/types.ts`. */

export type {
  Board,
  Cell,
  Coord,
  CreateBoardOptions,
  FallMove,
  GameEvent,
  GeometryTemplate,
  PieceInstance,
  RefillSpawn,
  ResolveResult,
  TrySwapResult,
} from '../domain/catalog/types';

export { BOARD_SIZE } from '../domain/catalog/types';
export { P0_PIECE_DEFS, P0_SPAWN_POOL } from '../domain/catalog/p0-catalog';

import type {
  Board,
  Coord,
  CreateBoardOptions,
  ResolveResult,
  TrySwapResult,
} from '../domain/catalog/types';

export type BoardApi = {
  createBoard: (options: CreateBoardOptions) => Board;
  trySwap: (board: Board, a: Coord, b: Coord) => TrySwapResult;
  resolveUntilStable: (board: Board) => ResolveResult;
};

/**
 * When false, `loadBoardApi` requires a working domain module.
 * Set true only to force the local stub engine.
 */
export const USE_STUB = false;

export function isOrthogonal(a: Coord, b: Coord): boolean {
  return Math.abs(a.q - b.q) + Math.abs(a.r - b.r) === 1;
}

export function sameCell(a: Coord, b: Coord): boolean {
  return a.q === b.q && a.r === b.r;
}
