import type { SpawnPool, SpeciesId } from '../catalog/types';

export type Rng = () => number;

/**
 * mulberry32: one step from an unsigned 32-bit state.
 * `unit` is in [0, 1); `state` is the next seed.
 */
export function stepRng(state: number): { unit: number; state: number } {
  let a = (state + 0x6d2b79f5) >>> 0;
  let t = a;
  t = Math.imul(t ^ (t >>> 15), t | 1);
  t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
  const unit = ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  return { unit, state: a };
}

export function createRng(seed: number): Rng {
  let state = seed >>> 0;
  return () => {
    const stepped = stepRng(state);
    state = stepped.state;
    return stepped.unit;
  };
}

export function pickSpeciesFromUnit(unit: number, pool: SpawnPool): SpeciesId {
  if (pool.speciesIds.length === 0 || pool.weights.length === 0) {
    throw new Error('empty spawn pool');
  }
  const total = pool.weights.reduce((sum, weight) => sum + weight, 0);
  if (total <= 0) {
    throw new Error('spawn pool weights must sum to > 0');
  }
  let roll = unit * total;
  for (let i = 0; i < pool.speciesIds.length; i++) {
    roll -= pool.weights[i]!;
    if (roll < 0) {
      return pool.speciesIds[i]!;
    }
  }
  return pool.speciesIds[pool.speciesIds.length - 1]!;
}

export function pickSpecies(rng: Rng, pool: SpawnPool): SpeciesId {
  return pickSpeciesFromUnit(rng(), pool);
}

export function pickSpeciesFromState(
  state: number,
  pool: SpawnPool,
): { speciesId: SpeciesId; state: number } {
  const stepped = stepRng(state);
  return {
    speciesId: pickSpeciesFromUnit(stepped.unit, pool),
    state: stepped.state,
  };
}
