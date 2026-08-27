import * as THREE from 'three';
import type { Coord } from '../domain/catalog/types';

export class InputRaycaster {
  private readonly raycaster = new THREE.Raycaster();
  private readonly pointer = new THREE.Vector2();
  enabled = true;

  constructor(
    private readonly canvas: HTMLCanvasElement,
    private readonly camera: THREE.Camera,
    private readonly getTargets: () => THREE.Object3D[],
    private readonly onHit: (pos: Coord) => void,
  ) {
    this.canvas.addEventListener('pointerdown', this.onPointer);
  }

  dispose(): void {
    this.canvas.removeEventListener('pointerdown', this.onPointer);
  }

  private readonly onPointer = (ev: PointerEvent): void => {
    if (!this.enabled) return;
    const rect = this.canvas.getBoundingClientRect();
    const w = rect.width || 1;
    const h = rect.height || 1;
    this.pointer.x = ((ev.clientX - rect.left) / w) * 2 - 1;
    this.pointer.y = -((ev.clientY - rect.top) / h) * 2 + 1;
    this.raycaster.setFromCamera(this.pointer, this.camera);
    const hits = this.raycaster.intersectObjects(this.getTargets(), true);
    for (const hit of hits) {
      let node: THREE.Object3D | null = hit.object;
      while (node) {
        const cell = node.userData.cell as Coord | undefined;
        if (cell) {
          this.onHit(cell);
          return;
        }
        node = node.parent;
      }
    }
  };
}
