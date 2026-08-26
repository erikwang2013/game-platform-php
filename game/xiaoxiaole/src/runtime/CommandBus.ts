import type { Coord } from '../domain/catalog/types';

export type Command =
  | { type: 'Select'; pos: Coord }
  | { type: 'Swap'; a: Coord; b: Coord }
  | { type: 'Quit' };

type Handler = (command: Command) => void;

export class CommandBus {
  private readonly handlers: Handler[] = [];

  on(handler: Handler): () => void {
    this.handlers.push(handler);
    return () => {
      const i = this.handlers.indexOf(handler);
      if (i >= 0) this.handlers.splice(i, 1);
    };
  }

  dispatch(command: Command): void {
    for (const handler of this.handlers) handler(command);
  }
}
