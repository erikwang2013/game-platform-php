import { GameApp } from './app/GameApp';

function bootLocal(): boolean {
  const params = new URLSearchParams(window.location.search);
  const debug = params.get('debug') === '1';
  const session = params.get('session_id') ?? params.get('session');
  return debug || !session;
}

const canvas = document.querySelector('#game-canvas');
const hudRoot = document.querySelector('#hud-root');
if (!(canvas instanceof HTMLCanvasElement) || !(hudRoot instanceof HTMLElement)) {
  throw new Error('Missing #game-canvas or #hud-root');
}

if (bootLocal()) {
  const app = new GameApp(canvas, hudRoot, { seed: 1 });
  app.start();
} else {
  const app = new GameApp(canvas, hudRoot, { seed: 1 });
  app.start();
}
