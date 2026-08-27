import { BOARD_SIZE } from '../catalog/types';
import { P0_SPAWN_POOL, getPieceDef } from '../catalog/p0-catalog';
import type {
  Board,
  Cell,
  Coord,
  CreateBoardFromGridOptions,
  CreateBoardOptions,
  PieceInstance,
  SpawnPool,
  SpeciesId,
} from '../catalog/types';
import { pickSpeciesFromState } from '../rng';
import { initialCombo } from '../score';
import { hasMatch } from '../match/same-type';

export function isInBounds(coord: Coord, size: number = BOARD_SIZE): boolean {
  return (
    Number.isInteger(coord.q) &&
    Number.isInteger(coord.r) &&
    coord.q >= 0 &&
    coord.r >= 0 &&
    coord.q < size &&
    coord.r < size
  );
}

export function getCell(board: Board, coord: Coord): Cell | null {
  if (!isInBounds(coord, board.size)) {
    return null;
  }
  return board.cells[coord.r]![coord.q]!;
}

export function getOccupant(board: Board, coord: Coord): PieceInstance | null {
  return getCell(board, coord)?.occupant ?? null;
}

export function cloneBoard(board: Board): Board {
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
        q: cell.q,
        r: cell.r,
        height: cell.height,
        locked: cell.locked,
        overlay: { ...cell.overlay },
        occupant: cell.occupant
          ? {
              uid: cell.occupant.uid,
              speciesId: cell.occupant.speciesId,
              def: cell.occupant.def,
              special: cell.occupant.special,
            }
          : null,
      })),
    ),
  };
}

export function makePiece(board: Board, speciesId: SpeciesId): PieceInstance {
  const uid = `p${board.nextUid}`;
  board.nextUid += 1;
  return {
    uid,
    speciesId,
    def: getPieceDef(speciesId),
    special: 'none',
  };
}

function emptyCell(q: number, r: number): Cell {
  return {
    q,
    r,
    height: 0,
    occupant: null,
    overlay: { kind: 'none' },
    locked: false,
  };
}

export function createEmptyBoard(options: {
  seed?: number;
  pool?: SpawnPool;
  combo?: number;
} = {}): Board {
  const seed = options.seed ?? 0;
  const cells: Cell[][] = [];
  for (let r = 0; r < BOARD_SIZE; r++) {
    const row: Cell[] = [];
    for (let q = 0; q < BOARD_SIZE; q++) {
      row.push(emptyCell(q, r));
    }
    cells.push(row);
  }
  return {
    size: BOARD_SIZE,
    cells,
    combo: options.combo ?? initialCombo(),
    seed,
    rngState: seed >>> 0,
    pool: options.pool ?? {
      ...P0_SPAWN_POOL,
      speciesIds: [...P0_SPAWN_POOL.speciesIds],
      weights: [...P0_SPAWN_POOL.weights],
    },
    nextUid: 1,
  };
}

function wouldCompleteRun(
  cells: Cell[][],
  q: number,
  r: number,
  speciesId: SpeciesId,
): boolean {
  if (q >= 2) {
    const left = cells[r]![q - 1]!.occupant;
    const farther = cells[r]![q - 2]!.occupant;
    if (left && farther && left.speciesId === speciesId && farther.speciesId === speciesId) {
      return true;
    }
  }
  if (r >= 2) {
    const up = cells[r - 1]![q]!.occupant;
    const farther = cells[r - 2]![q]!.occupant;
    if (up && farther && up.speciesId === speciesId && farther.speciesId === speciesId) {
      return true;
    }
  }
  return false;
}

export function createBoard(options: CreateBoardOptions): Board {
  if (options.grid) {
    return createBoardFromGrid(options.grid, { seed: options.seed, pool: options.pool });
  }
  const board = createEmptyBoard({ seed: options.seed, pool: options.pool });
  for (let r = 0; r < board.size; r++) {
    for (let q = 0; q < board.size; q++) {
      let picked: SpeciesId | null = null;
      for (let attempt = 0; attempt < 24; attempt++) {
        const step = pickSpeciesFromState(board.rngState, board.pool);
        board.rngState = step.state;
        if (!wouldCompleteRun(board.cells, q, r, step.speciesId)) {
          picked = step.speciesId;
          break;
        }
        picked = step.speciesId;
      }
      board.cells[r]![q]!.occupant = makePiece(board, picked!);
    }
  }
  if (hasMatch(board)) {
    throw new Error('createBoard produced opening matches');
  }
  return board;
}

export function createBoardFromGrid(
  grid: (SpeciesId | string | null)[][],
  options: CreateBoardFromGridOptions = {},
): Board {
  if (grid.length !== BOARD_SIZE || grid.some((row) => row.length !== BOARD_SIZE)) {
    throw new Error('grid must be 8×8');
  }
  const board = createEmptyBoard({
    seed: options.seed ?? 0,
    pool: options.pool,
    combo: options.combo,
  });
  for (let r = 0; r < BOARD_SIZE; r++) {
    for (let q = 0; q < BOARD_SIZE; q++) {
      const speciesId = grid[r]![q];
      if (speciesId) {
        board.cells[r]![q]!.occupant = makePiece(board, speciesId);
      }
    }
  }
  return board;
}
