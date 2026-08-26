import type { BoardApi } from './BoardPort';
import { USE_STUB } from './BoardPort';
import { createStubBoardApi } from './boardStub';

function asBoardApi(mod: unknown): BoardApi | null {
  if (!mod || typeof mod !== 'object') return null;
  const rec = mod as Record<string, unknown>;
  if (typeof rec.createBoard !== 'function' || typeof rec.trySwap !== 'function') {
    return null;
  }
  const createBoard = rec.createBoard as BoardApi['createBoard'];
  const trySwap = rec.trySwap as BoardApi['trySwap'];
  const resolveUntilStable: BoardApi['resolveUntilStable'] =
    typeof rec.resolveUntilStable === 'function'
      ? (rec.resolveUntilStable as BoardApi['resolveUntilStable'])
      : (board) => ({ board, events: [], combo: board.combo, score: 0 });
  try {
    const probe = createBoard({ seed: 1 });
    if (!probe?.cells) return null;
  } catch {
    return null;
  }
  return { createBoard, trySwap, resolveUntilStable };
}

/**
 * Prefer `src/domain` when createBoard / trySwap actually run.
 * Placeholder throws ("not implemented") keep the local stub.
 */
export function loadBoardApi(): BoardApi {
  if (USE_STUB) {
    return createStubBoardApi();
  }
  const barrels = {
    ...import.meta.glob('../domain/index.ts', { eager: true }),
    ...import.meta.glob('../domain.ts', { eager: true }),
  };
  for (const mod of Object.values(barrels)) {
    const api = asBoardApi(mod);
    if (api) return api;
  }
  throw new Error('[xiaoxiaole] domain createBoard/trySwap missing; set USE_STUB=true to use the local stub');
}
