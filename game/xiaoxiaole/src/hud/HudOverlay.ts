export class HudOverlay {
  private readonly scoreEl: HTMLElement;
  private readonly comboEl: HTMLElement;

  constructor(root: HTMLElement) {
    root.innerHTML = `
      <aside class="hud-panel" aria-label="对局状态">
        <h1 class="hud-title">田园消消乐</h1>
        <p class="hud-stamp">debug P0</p>
        <dl class="hud-stats">
          <div>
            <dt>分数</dt>
            <dd id="hud-score">0</dd>
          </div>
          <div>
            <dt>连击</dt>
            <dd id="hud-combo">0</dd>
          </div>
        </dl>
      </aside>
    `;
    this.scoreEl = root.querySelector('#hud-score')!;
    this.comboEl = root.querySelector('#hud-combo')!;
  }

  setScore(n: number): void {
    this.scoreEl.textContent = String(n);
  }

  setCombo(n: number): void {
    this.comboEl.textContent = n > 1 ? `×${n}` : String(n);
  }
}
