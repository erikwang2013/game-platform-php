import type { GameEvent } from '../domain/catalog/types';

export class EventLog {
  readonly events: GameEvent[] = [];

  append(batch: GameEvent[]): void {
    this.events.push(...batch);
  }

  clear(): void {
    this.events.length = 0;
  }
}
