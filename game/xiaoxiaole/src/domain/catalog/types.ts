/** 8×8 board; q = column, r = row, both in 0..7. */
export const BOARD_SIZE = 8 as const;
export type BoardSize = typeof BOARD_SIZE;

export type Faction =
  | 'crop'
  | 'veg'
  | 'fruit'
  | 'flora'
  | 'insect'
  | 'poultry'
  | 'livestock'
  | 'tree'
  | 'tool'
  | 'obstacle'
  | 'apex';

export type Role =
  | 'plant'
  | 'prey'
  | 'predator_mid'
  | 'predator_high'
  | 'apex'
  | 'obstacle'
  | 'skill';

export type Rarity = 'common' | 'rare' | 'legendary';

/** Geometry templates (D9). P0 uses grain / fruit / bird. */
export type GeometryTemplate =
  | 'grain'
  | 'produce'
  | 'fruit'
  | 'flower'
  | 'bug'
  | 'bird'
  | 'beast'
  | 'tree'
  | 'apex'
  | 'rock';

export type PieceTag = 'crop' | 'edible_by_poultry' | 'tree_seedling' | 'bone';

export type AccessoryId = 'beak' | 'petal' | 'trunk' | 'stem';

export type P0SpeciesId = 'wheat' | 'apple' | 'hen';

/** Catalog id; P0 is wheat | apple | hen, later species stay string-compatible. */
export type SpeciesId = P0SpeciesId | (string & {});

/** Linear RGB in 0..1 for template tint. */
export interface RGB {
  r: number;
  g: number;
  b: number;
}

export interface PieceDef {
  id: SpeciesId;
  faction: Faction;
  role: Role;
  tags: readonly PieceTag[];
  rarity: Rarity;
  template: GeometryTemplate;
  tint: RGB;
  accessory?: AccessoryId;
}

export interface Coord {
  q: number;
  r: number;
}

export type CellOverlay =
  | { kind: 'none' }
  | { kind: 'fertilizer' }
  | { kind: 'puddle' }
  | { kind: 'stone'; hp: number }
  | { kind: 'tree'; hp: number };

export type SpecialKind = 'none' | 'fertilizer_token';

export interface PieceInstance {
  uid: string;
  speciesId: SpeciesId;
  def: PieceDef;
  special: SpecialKind;
}

export interface Cell {
  q: number;
  r: number;
  /** Terrain wobble for render only; rules ignore this. */
  height: number;
  occupant: PieceInstance | null;
  overlay: CellOverlay;
  /** Elephant carnival: occupant cannot be swapped away. */
  locked: boolean;
}

/** Immutable-style snapshot; domain functions return a new Board. */
export interface Board {
  readonly size: BoardSize;
  /** cells[r][q] — row-major, length BOARD_SIZE × BOARD_SIZE. */
  cells: Cell[][];
  combo: number;
  seed: number;
  rngState: number;
  pool: SpawnPool;
  /** Monotonic counter for PieceInstance.uid. */
  nextUid: number;
}

export interface SpawnPool {
  speciesIds: SpeciesId[];
  weights: number[];
  maxApex: number;
  /** Combo threshold before the system may spawn apex; never via swap. */
  apexUnlock: number;
}

export type MatchKind = 'same' | 'eco' | 'elephant';

export interface MatchRun {
  kind: MatchKind;
  cells: Coord[];
  /** Set when kind === 'same'. */
  speciesId?: SpeciesId;
  /** Set when kind === 'eco'. */
  predatorSpeciesId?: SpeciesId;
}

export type RejectedSwapReason =
  | 'not_adjacent'
  | 'no_match'
  | 'immovable'
  | 'locked'
  | 'out_of_bounds'
  | 'empty';

export interface SwappedEvent {
  type: 'Swapped';
  a: Coord;
  b: Coord;
}

export interface RejectedSwapEvent {
  type: 'RejectedSwap';
  a: Coord;
  b: Coord;
  reason: RejectedSwapReason;
}

export interface MatchesEvent {
  type: 'Matches';
  runs: MatchRun[];
  combo: number;
}

export interface ClearedEvent {
  type: 'Cleared';
  cells: Coord[];
  runs: MatchRun[];
}

export interface FallMove {
  uid: string;
  from: Coord;
  to: Coord;
}

export interface FellEvent {
  type: 'Fell';
  moves: FallMove[];
}

export interface RefillSpawn {
  cell: Coord;
  piece: PieceInstance;
}

export interface RefilledEvent {
  type: 'Refilled';
  spawned: RefillSpawn[];
}

export interface ComboEvent {
  type: 'Combo';
  combo: number;
}

export type GameEvent =
  | SwappedEvent
  | RejectedSwapEvent
  | MatchesEvent
  | ClearedEvent
  | FellEvent
  | RefilledEvent
  | ComboEvent;

export interface CreateBoardOptions {
  seed: number;
  pool?: SpawnPool;
  /** When set, place these species instead of rolling the spawn pool. */
  grid?: (SpeciesId | null)[][];
}

export interface TrySwapResult {
  board: Board;
  events: GameEvent[];
  accepted: boolean;
  /** False when the swap is reverted (F01: no legal run). */
  spentMove: boolean;
}

export interface ResolveResult {
  board: Board;
  events: GameEvent[];
  combo: number;
  /** P0: sum of 10 * n * combo per cascade wave. */
  score: number;
}

export interface CreateBoardFromGridOptions {
  seed?: number;
  pool?: SpawnPool;
  combo?: number;
}

export type SelectCellResult =
  | { action: 'select'; selection: Coord }
  | { action: 'deselect'; selection: null }
  | { action: 'reselect'; selection: Coord }
  | { action: 'swap'; a: Coord; b: Coord; selection: null }
  | {
      action: 'ignore';
      selection: Coord | null;
      reason: RejectedSwapReason;
    };

export { P0_PIECE_DEFS, P0_SPAWN_POOL, P0_SPECIES_IDS } from './p0-catalog';

