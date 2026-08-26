import * as THREE from 'three';

export class CameraRig {
  readonly camera: THREE.OrthographicCamera;
  private viewH = 12;

  constructor(aspect: number) {
    this.camera = new THREE.OrthographicCamera(
      (-this.viewH * aspect) / 2,
      (this.viewH * aspect) / 2,
      this.viewH / 2,
      -this.viewH / 2,
      0.1,
      80,
    );
  }

  lookAtBoard(size: number): void {
    const cx = (size - 1) / 2;
    const cz = (size - 1) / 2;
    const pitch = THREE.MathUtils.degToRad(45);
    const yaw = THREE.MathUtils.degToRad(30);
    const dist = 22;
    this.camera.position.set(
      cx + Math.sin(yaw) * Math.cos(pitch) * dist,
      Math.sin(pitch) * dist,
      cz + Math.cos(yaw) * Math.cos(pitch) * dist,
    );
    this.camera.lookAt(cx, 0.15, cz);
    this.camera.updateProjectionMatrix();
  }

  resize(aspect: number): void {
    const h = this.viewH;
    this.camera.left = (-h * aspect) / 2;
    this.camera.right = (h * aspect) / 2;
    this.camera.top = h / 2;
    this.camera.bottom = -h / 2;
    this.camera.updateProjectionMatrix();
  }
}
