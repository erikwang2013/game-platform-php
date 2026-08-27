import * as THREE from 'three';
import type { GeometryTemplate, PieceInstance, RGB } from '../domain/catalog/types';

function rgbToHex(tint: RGB): number {
  const r = Math.round(tint.r * 255);
  const g = Math.round(tint.g * 255);
  const b = Math.round(tint.b * 255);
  return (r << 16) | (g << 8) | b;
}

function phong(color: number, extras: Partial<THREE.MeshPhongMaterialParameters> = {}): THREE.MeshPhongMaterial {
  return new THREE.MeshPhongMaterial({
    color,
    shininess: 28,
    specular: 0x334422,
    ...extras,
  });
}

export class PieceFactory {
  private readonly pools = new Map<string, THREE.Group[]>();

  acquire(piece: PieceInstance): THREE.Group {
    const key = piece.def.template;
    const pool = this.pools.get(key) ?? [];
    const group = pool.pop() ?? this.build(piece.def.template);
    this.tint(group, rgbToHex(piece.def.tint));
    group.scale.set(1, 1, 1);
    group.visible = true;
    group.userData.uid = piece.uid;
    group.userData.speciesId = piece.speciesId;
    group.userData.template = piece.def.template;
    this.setRing(group, false);
    return group;
  }

  release(group: THREE.Group): void {
    this.setRing(group, false);
    group.visible = false;
    group.removeFromParent();
    const key = String(group.userData.template ?? 'grain');
    const pool = this.pools.get(key) ?? [];
    pool.push(group);
    this.pools.set(key, pool);
  }

  setRing(group: THREE.Group, on: boolean): void {
    const ring = group.getObjectByName('selectRing');
    if (ring) ring.visible = on;
  }

  private tint(group: THREE.Group, hex: number): void {
    const body = group.getObjectByName('body');
    if (body instanceof THREE.Mesh && body.material instanceof THREE.MeshPhongMaterial) {
      body.material = body.material.clone();
      body.material.color.setHex(hex);
    }
  }

  private build(template: GeometryTemplate): THREE.Group {
    if (template === 'fruit') return this.buildApple();
    if (template === 'bird') return this.buildHen();
    return this.buildWheat();
  }

  private withRing(group: THREE.Group): THREE.Group {
    const ring = new THREE.Mesh(
      new THREE.TorusGeometry(0.36, 0.03, 8, 22),
      phong(0xfff1a8, { emissive: 0xffc14a, emissiveIntensity: 0.9, shininess: 8 }),
    );
    ring.name = 'selectRing';
    ring.rotation.x = Math.PI / 2;
    ring.position.y = -0.22;
    ring.visible = false;
    group.add(ring);
    return group;
  }

  private buildWheat(): THREE.Group {
    const g = new THREE.Group();
    const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.055, 0.42, 6), phong(0x7a5a22));
    stem.position.y = -0.02;
    const head = new THREE.Mesh(new THREE.ConeGeometry(0.18, 0.46, 7), phong(0xe6c15a));
    head.name = 'body';
    head.position.y = 0.28;
    g.add(stem, head);
    return this.withRing(g);
  }

  private buildApple(): THREE.Group {
    const g = new THREE.Group();
    const body = new THREE.Mesh(new THREE.SphereGeometry(0.28, 14, 12), phong(0xd4453a));
    body.name = 'body';
    body.scale.y = 0.92;
    const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.03, 0.16, 6), phong(0x4a3218));
    stem.position.y = 0.3;
    const leaf = new THREE.Mesh(new THREE.SphereGeometry(0.09, 8, 6), phong(0x4f8a3a));
    leaf.scale.set(1.4, 0.28, 0.8);
    leaf.position.set(0.1, 0.3, 0);
    g.add(body, stem, leaf);
    return this.withRing(g);
  }

  private buildHen(): THREE.Group {
    const g = new THREE.Group();
    const body = new THREE.Mesh(new THREE.SphereGeometry(0.24, 12, 10), phong(0xf3e4c8));
    body.name = 'body';
    body.scale.set(1.15, 0.85, 1);
    body.position.y = 0.02;
    const head = new THREE.Mesh(new THREE.SphereGeometry(0.13, 10, 8), phong(0xf7ead2));
    head.position.set(0.16, 0.18, 0);
    const beak = new THREE.Mesh(new THREE.ConeGeometry(0.045, 0.12, 6), phong(0xe0892c));
    beak.rotation.z = -Math.PI / 2;
    beak.position.set(0.28, 0.16, 0);
    const comb = new THREE.Mesh(new THREE.ConeGeometry(0.05, 0.1, 5), phong(0xc43c2c));
    comb.position.set(0.14, 0.32, 0);
    g.add(body, head, beak, comb);
    return this.withRing(g);
  }
}
