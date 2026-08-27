import type { Board, FallMove, PieceInstance, RefillSpawn } from '../catalog/types';
import { cloneBoard, makePiece } from '../board/grid';
import { pickSpeciesFromState } from '../rng';

export function applyGravity(board: Board): { board: Board; fell: FallMove[] } {
  const next = cloneBoard(board);
  const fell: FallMove[] = [];

  for (let q = 0; q < next.size; q++) {
    const pieces: { piece: PieceInstance; fromR: number }[] = [];
    for (let r = next.size - 1; r >= 0; r--) {
      const occupant = next.cells[r]![q]!.occupant;
      if (occupant) {
        pieces.push({ piece: occupant, fromR: r });
      }
    }

    for (let r = 0; r < next.size; r++) {
      next.cells[r]![q]!.occupant = null;
    }

    let writeR = next.size - 1;
    for (const item of pieces) {
      next.cells[writeR]![q]!.occupant = item.piece;
      if (item.fromR !== writeR) {
        fell.push({
          uid: item.piece.uid,
          from: { q, r: item.fromR },
          to: { q, r: writeR },
        });
      }
      writeR -= 1;
    }
  }

  return { board: next, fell };
}

export function refillBoard(board: Board): { board: Board; spawned: RefillSpawn[] } {
  const next = cloneBoard(board);
  const spawned: RefillSpawn[] = [];

  for (let r = 0; r < next.size; r++) {
    for (let q = 0; q < next.size; q++) {
      if (next.cells[r]![q]!.occupant) {
        continue;
      }
      const picked = pickSpeciesFromState(next.rngState, next.pool);
      next.rngState = picked.state;
      const piece = makePiece(next, picked.speciesId);
      next.cells[r]![q]!.occupant = piece;
      spawned.push({ cell: { q, r }, piece });
    }
  }

  return { board: next, spawned };
}
