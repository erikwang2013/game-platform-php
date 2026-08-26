/** P0 same-type clear: 10 * n * combo (architecture 5.8, no fertilizer). */
export function scoreSameType(n: number, combo: number): number {
  if (n <= 0 || combo <= 0) {
    return 0;
  }
  return 10 * n * combo;
}

export function initialCombo(): number {
  return 1;
}
