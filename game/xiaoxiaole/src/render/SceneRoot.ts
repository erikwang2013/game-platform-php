import * as THREE from 'three';

export class SceneRoot {
  readonly renderer: THREE.WebGLRenderer;
  readonly scene: THREE.Scene;

  constructor(canvas: HTMLCanvasElement) {
    this.renderer = new THREE.WebGLRenderer({
      canvas,
      antialias: true,
      alpha: false,
      powerPreference: 'high-performance',
    });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.setSize(window.innerWidth, window.innerHeight);
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.08;
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color(0x8ebdd4);
    this.scene.fog = new THREE.Fog(0x8ebdd4, 30, 52);
  }

  render(camera: THREE.Camera): void {
    this.renderer.render(this.scene, camera);
  }

  resize(): void {
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.setSize(window.innerWidth, window.innerHeight);
  }
}
