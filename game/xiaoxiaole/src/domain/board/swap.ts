import type { Board, Coord, RejectedSwapReason, SelectCellResult, TrySwapResult } from '../catalog/types';
import { resolveUntilStable } from '../match/resolve';
import { initialCombo } from '../score';
import { hasMatch } from '../match/same-type';
import { cloneBoard, getOccupant, isInBounds } from './grid';

export function areOrthogonalAdjacent(a: Coord, b: Coord): boolean {
  return Math.abs(a.q - b.q) + Math.abs(a.r - b.r) === 1;
}

function reject(board: Board, a: Coord, b: Coord, reason: RejectedSwapReason): TrySwapResult {
  return {
    board,
    accepted: false,
    spentMove: false,
    events: [{ type: 'RejectedSwap', a, b, reason }],
  };
}

export function trySwap(board: Board, a: Coord, b: Coord): TrySwapResult {
  if (!isInBounds(a, board.size) || !isInBounds(b, board.size)) {
    return reject(board, a, b, 'out_of_bounds');
  }
  if (a.q === b.q && a.r === b.r) {
    return reject(board, a, b, 'not_adjacent');
  }
  if (!areOrthogonalAdjacent(a, b)) {
    return reject(board, a, b, 'not_adjacent');
  }
  const pieceA = getOccupant(board, a);
  const pieceB = getOccupant(board, b);
  if (!pieceA || !pieceB) {
    return reject(board, a, b, 'empty');
  }
  if (board.cells[a.r]![a.q]!.locked || board.cells[b.r]![b.q]!.locked) {
    return reject(board, a, b, 'locked');
  }

  const next = cloneBoard(board);
  const left = next.cells[a.r]![a.q]!;
  const right = next.cells[b.r]![b.q]!;
  const tmp = left.occupant;
  left.occupant = right.occupant;
  right.occupant = tmp;

  if (!hasMatch(next)) {
    return reject(board, a, b, 'no_match');
  }

  next.combo = initialCombo();
  const resolved = resolveUntilStable(next);
  return {
    board: resolved.board,
    accepted: true,
    spentMove: true,
    events: [{ type: 'Swapped', a, b }, ...resolved.events],
  };
}

export function selectCell(
  board: Board,
  selected: Coord | null,
  clicked: Coord,
): SelectCellResult {
  if (!isInBounds(clicked, board.size)) {
    return { action: 'ignore', selection: selected, reason: 'out_of_bounds' };
  }
  if (!getOccupant(board, clicked)) {
    return { action: 'ignore', selection: selected, reason: 'empty' };
  }
  if (selected === null) {
    return { action: 'select', selection: clicked };
  }
  if (selected.q === clicked.q && selected.r === clicked.r) {
    return { action: 'deselect', selection: null };
  }
  if (areOrthogonalAdjacent(selected, clicked)) {
    return { action: 'swap', a: selected, b: clicked, selection: null };
  }
  return { action: 'reselect', selection: clicked };
}
