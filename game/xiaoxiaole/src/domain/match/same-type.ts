import { BOARD_SIZE } from '../catalog/types';
import type { Board, Coord, MatchRun, SpeciesId } from '../catalog/types';

function occupantSpecies(board: Board, q: number, r: number): SpeciesId | null {
  return board.cells[r]![q]!.occupant?.speciesId ?? null;
}

function scanLine(
  board: Board,
  length: number,
  get: (i: number) => { q: number; r: number },
  runs: MatchRun[],
): void {
  let start = 0;
  while (start < length) {
    const origin = get(start);
    const speciesId = occupantSpecies(board, origin.q, origin.r);
    if (!speciesId) {
      start += 1;
      continue;
    }
    let end = start + 1;
    while (end < length) {
      const cell = get(end);
      if (occupantSpecies(board, cell.q, cell.r) !== speciesId) {
        break;
      }
      end += 1;
    }
    if (end - start >= 3) {
      const cells: Coord[] = [];
      for (let i = start; i < end; i++) {
        cells.push(get(i));
      }
      runs.push({ kind: 'same', cells, speciesId });
    }
    start = end;
  }
}

export function findSameTypeRuns(board: Board): MatchRun[] {
  const runs: MatchRun[] = [];
  const size = board.size ?? BOARD_SIZE;
  for (let r = 0; r < size; r++) {
    scanLine(board, size, (q) => ({ q, r }), runs);
  }
  for (let q = 0; q < size; q++) {
    scanLine(board, size, (r) => ({ q, r }), runs);
  }
  return runs;
}

export function collectMatchCells(board: Board): Coord[] {
  const seen = new Set<string>();
  const cells: Coord[] = [];
  for (const run of findSameTypeRuns(board)) {
    for (const cell of run.cells) {
      const key = `${cell.q},${cell.r}`;
      if (!seen.has(key)) {
        seen.add(key);
        cells.push(cell);
      }
    }
  }
  return cells;
}

export function hasMatch(board: Board): boolean {
  return findSameTypeRuns(board).length > 0;
}
