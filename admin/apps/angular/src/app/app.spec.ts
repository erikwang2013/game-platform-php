import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { App } from './app';

describe('App', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [App],
      // 模板用了 RouterLink / RouterOutlet，测试宿主须自带 Router 依赖，否则渲染即 NG0201（ActivatedRoute）
      providers: [provideRouter([])],
    }).compileComponents();
  });

  it('should create the app', () => {
    const fixture = TestBed.createComponent(App);
    const app = fixture.componentInstance;
    expect(app).toBeTruthy();
  });

  it('should render the console brand', async () => {
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();
    const compiled = fixture.nativeElement as HTMLElement;
    // 原断言指向 CLI 脚手架的 <h1>Hello, game-admin-angular</h1>，当前 app.html 已无该节点（只剩 .brand / 侧栏外壳）
    expect(compiled.querySelector('.brand')?.textContent).toContain('游戏运营后台');
  });
});
