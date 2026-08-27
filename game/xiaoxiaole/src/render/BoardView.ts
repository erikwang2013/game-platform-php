import * as THREE from 'three';
import type { Board, Coord, FallMove, PieceInstance, RefillSpawn } from '../domain/catalog/types';
import { PieceFactory } from './PieceFactory';

const PIECE_LIFT = 0.52;

export class BoardView {
  readonly pieceRoot = new THREE.Group();
  private slots: (THREE.Group | null)[][] = [];
  private readonly byUid = new Map<string, THREE.Group>();
  private heights: number[][] = [];
  private selected: THREE.Group | null = null;

  constructor(
    private readonly scene: THREE.Scene,
    private readonly factory: PieceFactory,
  ) {
    this.pieceRoot.name = 'pieces';
    scene.add(this.pieceRoot);
  }

  mount(board: Board): void {
    this.buildGround(board);
    this.rebuildPieces(board);
  }

  getTargets(): THREE.Object3D[] {
    return this.pieceRoot.children;
  }

  getMesh(pos: Coord): THREE.Group | null {
    return this.slots[pos.r]?.[pos.q] ?? null;
  }

  worldPos(q: number, r: number, lift = PIECE_LIFT): THREE.Vector3 {
    const h = this.heights[r]?.[q] ?? 0;
    return new THREE.Vector3(q, h + lift, r);
  }

  setSelected(pos: Coord | null): THREE.Group | null {
    if (this.selected) this.factory.setRing(this.selected, false);
    if (!pos) {
      this.selected = null;
      return null;
    }
    const mesh = this.getMesh(pos);
    if (mesh) {
      this.factory.setRing(mesh, true);
      this.selected = mesh;
    }
    return mesh;
  }

  swapSlots(a: Coord, b: Coord): void {
    const meshA = this.getMesh(a);
    const meshB = this.getMesh(b);
    this.slots[a.r]![a.q] = meshB;
    this.slots[b.r]![b.q] = meshA;
    if (meshA) meshA.userData.cell = { ...b };
    if (meshB) meshB.userData.cell = { ...a };
  }

  takeMeshes(cells: Coord[]): THREE.Group[] {
    const out: THREE.Group[] = [];
    for (const cell of cells) {
      const mesh = this.getMesh(cell);
      if (!mesh) continue;
      this.slots[cell.r]![cell.q] = null;
      this.byUid.delete(String(mesh.userData.uid));
      out.push(mesh);
    }
    return out;
  }

  detach(meshes: THREE.Group[]): void {
    for (const mesh of meshes) this.factory.release(mesh);
  }

  applyFalls(moves: FallMove[]): { obj: THREE.Group; x: number; y: number; z: number }[] {
    const tweens: { obj: THREE.Group; x: number; y: number; z: number }[] = [];
    for (const move of moves) {
      const mesh = this.byUid.get(move.uid) ?? this.getMesh(move.from);
      if (!mesh) continue;
      const fromSlot = this.getMesh(move.from);
      if (fromSlot === mesh) this.slots[move.from.r]![move.from.q] = null;
      this.slots[move.to.r]![move.to.q] = mesh;
      mesh.userData.cell = { ...move.to };
      this.byUid.set(move.uid, mesh);
      const dest = this.worldPos(move.to.q, move.to.r);
      tweens.push({ obj: mesh, x: dest.x, y: dest.y, z: dest.z });
    }
    return tweens;
  }

  spawnRefills(spawned: RefillSpawn[]): { obj: THREE.Group; x: number; y: number; z: number }[] {
    const tweens: { obj: THREE.Group; x: number; y: number; z: number }[] = [];
    for (const item of spawned) {
      const mesh = this.placePiece(item.piece, item.cell);
      const dest = this.worldPos(item.cell.q, item.cell.r);
      mesh.position.set(dest.x, dest.y + 3.2, dest.z);
      tweens.push({ obj: mesh, x: dest.x, y: dest.y, z: dest.z });
    }
    return tweens;
  }

  private rebuildPieces(board: Board): void {
    for (const child of [...this.pieceRoot.children]) {
      if (child instanceof THREE.Group) this.factory.release(child);
    }
    this.byUid.clear();
    const size = board.size;
    this.slots = Array.from({ length: size }, () => Array.from({ length: size }, () => null));
    for (let r = 0; r < size; r++) {
      for (let q = 0; q < size; q++) {
        const piece = board.cells[r]![q]!.occupant;
        if (!piece) continue;
        this.placePiece(piece, { q, r });
      }
    }
  }

  private placePiece(piece: PieceInstance, pos: Coord): THREE.Group {
    const mesh = this.factory.acquire(piece);
    mesh.userData.cell = { ...pos };
    const dest = this.worldPos(pos.q, pos.r);
    mesh.position.copy(dest);
    this.pieceRoot.add(mesh);
    this.slots[pos.r]![pos.q] = mesh;
    this.byUid.set(piece.uid, mesh);
    return mesh;
  }

  private buildGround(board: Board): void {
    const size = board.size;
    this.heights = board.cells.map((row) => row.map((cell) => cell.height));
    const soilA = new THREE.MeshPhongMaterial({ color: 0x6b8f3a, flatShading: true });
    const soilB = new THREE.MeshPhongMaterial({ color: 0x5c7a32, flatShading: true });
    const geom = new THREE.BoxGeometry(0.94, 0.14, 0.94);
    const tiles = new THREE.Group();
    tiles.name = 'tiles';
    for (let r = 0; r < size; r++) {
      for (let q = 0; q < size; q++) {
        const tile = new THREE.Mesh(geom, (q + r) % 2 === 0 ? soilA : soilB);
        tile.position.set(q, board.cells[r]![q]!.height, r);
        tiles.add(tile);
      }
    }
    const wood = new THREE.MeshPhongMaterial({ color: 0x6a4424 });
    const rimH = 0.28;
    const len = size + 0.35;
    const north = new THREE.Mesh(new THREE.BoxGeometry(len, rimH, 0.22), wood);
    north.position.set((size - 1) / 2, 0.05, -0.58);
    const south = north.clone();
    south.position.z = size - 0.42;
    const west = new THREE.Mesh(new THREE.BoxGeometry(0.22, rimH, len), wood);
    west.position.set(-0.58, 0.05, (size - 1) / 2);
    const east = west.clone();
    east.position.x = size - 0.42;
    const bed = new THREE.Mesh(new THREE.BoxGeometry(size + 0.8, 0.18, size + 0.8), wood);
    bed.position.set((size - 1) / 2, -0.16, (size - 1) / 2);
    this.scene.add(tiles, north, south, west, east, bed);
  }
}
