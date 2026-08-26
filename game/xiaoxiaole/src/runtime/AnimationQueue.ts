import gsap from 'gsap';
import type { Object3D } from 'three';

export type TweenMove = { obj: Object3D; x: number; y: number; z: number };

export class AnimationQueue {
  swap(a: Object3D, b: Object3D): Promise<void> {
    const ax = a.position.x;
    const ay = a.position.y;
    const az = a.position.z;
    const bx = b.position.x;
    const by = b.position.y;
    const bz = b.position.z;
    return new Promise((resolve) => {
      const tl = gsap.timeline({ onComplete: () => resolve() });
      tl.to(a.position, { x: bx, y: by, z: bz, duration: 0.22, ease: 'power2.inOut' }, 0);
      tl.to(b.position, { x: ax, y: ay, z: az, duration: 0.22, ease: 'power2.inOut' }, 0);
    });
  }

  clear(objs: Object3D[]): Promise<void> {
    return new Promise((resolve) => {
      if (objs.length === 0) {
        resolve();
        return;
      }
      const tl = gsap.timeline({ onComplete: () => resolve() });
      for (const obj of objs) {
        tl.to(obj.scale, { x: 0.02, y: 0.02, z: 0.02, duration: 0.26, ease: 'back.in(1.6)' }, 0);
      }
    });
  }

  fall(moves: TweenMove[]): Promise<void> {
    return new Promise((resolve) => {
      if (moves.length === 0) {
        resolve();
        return;
      }
      const tl = gsap.timeline({ onComplete: () => resolve() });
      for (const move of moves) {
        tl.to(
          move.obj.position,
          { x: move.x, y: move.y, z: move.z, duration: 0.36, ease: 'bounce.out' },
          0,
        );
      }
    });
  }

  pulse(obj: Object3D): void {
    gsap.killTweensOf(obj.scale);
    obj.scale.set(1, 1, 1);
    gsap.to(obj.scale, {
      x: 1.12,
      y: 1.12,
      z: 1.12,
      duration: 0.38,
      yoyo: true,
      repeat: -1,
      ease: 'sine.inOut',
    });
  }

  stopPulse(obj: Object3D): void {
    gsap.killTweensOf(obj.scale);
    obj.scale.set(1, 1, 1);
  }
}
