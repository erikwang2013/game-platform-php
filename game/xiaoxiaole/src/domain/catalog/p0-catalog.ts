import type { P0SpeciesId, PieceDef, SpawnPool, SpeciesId } from './types';

export const P0_SPECIES_IDS: readonly P0SpeciesId[] = ['wheat', 'apple', 'hen'];

export const P0_PIECE_DEFS: Record<P0SpeciesId, PieceDef> = {
  wheat: {
    id: 'wheat',
    faction: 'crop',
    role: 'plant',
    tags: ['crop'],
    rarity: 'common',
    template: 'grain',
    tint: { r: 0.86, g: 0.72, b: 0.22 },
  },
  apple: {
    id: 'apple',
    faction: 'fruit',
    role: 'plant',
    tags: ['edible_by_poultry'],
    rarity: 'common',
    template: 'fruit',
    tint: { r: 0.82, g: 0.18, b: 0.16 },
    accessory: 'stem',
  },
  hen: {
    id: 'hen',
    faction: 'poultry',
    role: 'predator_mid',
    tags: [],
    rarity: 'common',
    template: 'bird',
    tint: { r: 0.95, g: 0.88, b: 0.72 },
    accessory: 'beak',
  },
};

export const P0_SPAWN_POOL: SpawnPool = {
  speciesIds: ['wheat', 'apple', 'hen'],
  weights: [1, 1, 1],
  maxApex: 1,
  apexUnlock: 5,
};

const P0_IDS = new Set<string>(P0_SPECIES_IDS);

export function getPieceDef(speciesId: SpeciesId): PieceDef {
  if (P0_IDS.has(speciesId)) {
    return P0_PIECE_DEFS[speciesId as P0SpeciesId];
  }
  throw new Error(`unknown speciesId: ${speciesId}`);
}
