import { describe, expect, it } from 'vitest';
import { createRng, pickSpecies } from '../../src/domain/rng';
import { P0_SPAWN_POOL } from '../../src/domain/catalog/p0-catalog';
import type { SpawnPool } from '../../src/domain/catalog/types';

describe('createRng (mulberry32)', () => {
  it('returns values in [0, 1)', () => {
    const rng = createRng(1);
    for (let i = 0; i < 1000; i++) {
      const n = rng();
      expect(n).toBeGreaterThanOrEqual(0);
      expect(n).toBeLessThan(1);
    }
  });

  it('is deterministic for the same seed', () => {
    const a = createRng(42);
    const b = createRng(42);
    expect(Array.from({ length: 32 }, () => a())).toEqual(Array.from({ length: 32 }, () => b()));
  });

  it('diverges for different seeds', () => {
    const a = createRng(1);
    const b = createRng(2);
    expect(Array.from({ length: 8 }, () => a())).not.toEqual(Array.from({ length: 8 }, () => b()));
  });

  it('treats seed 0 as a valid numeric seed', () => {
    expect(createRng(0)()).toBeGreaterThanOrEqual(0);
    expect(createRng(0)()).toBe(createRng(0)());
  });

  it('maps negative seeds through unsigned 32-bit wrap', () => {
    const a = createRng(-1);
    const b = createRng(-1);
    expect(Array.from({ length: 4 }, () => a())).toEqual(Array.from({ length: 4 }, () => b()));
  });
});

describe('pickSpecies', () => {
  it('picks only from the pool species list', () => {
    const rng = createRng(7);
    for (let i = 0; i < 40; i++) {
      expect(P0_SPAWN_POOL.speciesIds).toContain(pickSpecies(rng, P0_SPAWN_POOL));
    }
  });

  it('is deterministic for the same seed and pool', () => {
    const seq = (seed: number) => {
      const rng = createRng(seed);
      return Array.from({ length: 20 }, () => pickSpecies(rng, P0_SPAWN_POOL));
    };
    expect(seq(99)).toEqual(seq(99));
  });

  it('always selects the only positive-weight species', () => {
    const pool: SpawnPool = {
      speciesIds: ['wheat', 'apple', 'hen'],
      weights: [0, 0, 1],
      maxApex: 1,
      apexUnlock: 5,
    };
    const rng = createRng(123);
    for (let i = 0; i < 16; i++) {
      expect(pickSpecies(rng, pool)).toBe('hen');
    }
  });

  it('throws on an empty spawn pool', () => {
    const rng = createRng(1);
    const empty: SpawnPool = { speciesIds: [], weights: [], maxApex: 1, apexUnlock: 5 };
    expect(() => pickSpecies(rng, empty)).toThrow();
  });
});
