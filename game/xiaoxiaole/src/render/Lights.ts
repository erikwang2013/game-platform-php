import * as THREE from 'three';

export function addFarmLights(scene: THREE.Scene): void {
  const hemi = new THREE.HemisphereLight(0xfff3d6, 0x3f5c32, 0.9);
  scene.add(hemi);

  const sun = new THREE.DirectionalLight(0xffe4b5, 1.2);
  sun.position.set(7, 14, 5);
  sun.castShadow = false;
  scene.add(sun);
}
