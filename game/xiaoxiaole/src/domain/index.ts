export type {
  AccessoryId,
  Board,
  BoardSize,
  Cell,
  CellOverlay,
  ComboEvent,
  Coord,
  CreateBoardOptions,
  ClearedEvent,
  Faction,
  FallMove,
  FellEvent,
  GameEvent,
  GeometryTemplate,
  MatchKind,
  MatchRun,
  MatchesEvent,
  PieceDef,
  PieceInstance,
  PieceTag,
  P0SpeciesId,
  RGB,
  Rarity,
  RefillSpawn,
  RefilledEvent,
  RejectedSwapEvent,
  RejectedSwapReason,
  ResolveResult,
  Role,
  SelectCellResult,
  SpawnPool,
  SpecialKind,
  SpeciesId,
  SwappedEvent,
  TrySwapResult,
} from './catalog/types';

export { BOARD_SIZE } from './catalog/types';
export type { CreateBoardFromGridOptions } from './catalog/types';

export {
  P0_PIECE_DEFS,
  P0_SPAWN_POOL,
  P0_SPECIES_IDS,
  getPieceDef,
} from './catalog/p0-catalog';

export {
  areOrthogonalAdjacent,
  cloneBoard,
  createBoard,
  createBoardFromGrid,
  createEmptyBoard,
  getCell,
  getOccupant,
  isInBounds,
  selectCell,
  trySwap,
} from './board';

export {
  collectMatchCells,
  findSameTypeRuns,
  hasMatch,
  resolveUntilStable,
} from './match';

export { applyGravity, refillBoard } from './gravity';

export { createRng, pickSpecies, pickSpeciesFromState, stepRng } from './rng';
export type { Rng } from './rng';

export { initialCombo, scoreSameType } from './score';
