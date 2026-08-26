export type Phase =
  | 'Boot'
  | 'Idle'
  | 'Selected'
  | 'SwapAnim'
  | 'ResolveLogic'
  | 'ClearAnim'
  | 'GravityAnim'
  | 'RefillAnim';

const INPUT_PHASES: ReadonlySet<Phase> = new Set(['Idle', 'Selected']);

export class GameStateMachine {
  phase: Phase = 'Boot';

  set(phase: Phase): void {
    this.phase = phase;
  }

  canAcceptInput(): boolean {
    return INPUT_PHASES.has(this.phase);
  }
}
