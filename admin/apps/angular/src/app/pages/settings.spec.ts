/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { use } from '../core/i18n/i18n';
import { Settings } from './settings';

/**
 * 「系统配置」页的仓内钉子。**此前这页零 spec** —— 本轮的搬家（`value` 从 placeholder 挪进
 * hint）唯一见证是三支 `/tmp` 真机探针，那些读数会随会话消失，这条不会。
 *
 * 只钉搬家那一条：**规则文案在框下的 `.hint` 里，不在 `placeholder` 里**。
 * 判据来自真机实测（2026-10-02）：字段宽 430 ⇒ 净宽 406，而这句文案 en 380px、**ja 405px**
 * （占用率 0.98 / **1.00**）—— 当 placeholder 就是「差 1px 必被截、还看不全」；`.hint` 是块级、
 * 能换行，读得完。同族先例（同一缺陷类的另一页）见 admins.spec.ts 的 ④。
 *
 * 其余 487 行不在这条的范围内：这页的角色 / 权限 / 指标 / 健康四个页签都还没有 spec，
 * 那是另一件事。
 */
describe('Settings 系统配置', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    // 语言钉在 en：断的是**成品文案**，不钉住的话「上一条用例留下的语言」会让断言随执行顺序变红
    use('en');
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  /** 默认页签就是 config（配置项），首屏只跳一跳 GET /admin/v1/config */
  const render = async (): Promise<ComponentFixture<Settings>> => {
    const f = TestBed.createComponent(Settings);
    f.detectChanges();
    http
      .expectOne((r) => r.method === 'GET' && r.url.startsWith('/admin/v1/config'))
      .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
    await tick();
    f.detectChanges();
    return f;
  };

  it('value 的规则文案在 hint 里而不是会被截断的 placeholder 里', async () => {
    const f = await render();
    (f.componentInstance as unknown as { openCreate(): void }).openCreate();
    f.detectChanges();

    const root = f.nativeElement as HTMLElement;
    const box = root.querySelector<HTMLTextAreaElement>('.modal textarea[name="value"]')!;
    // 搬到 hint 之后框里不再有 placeholder（搬回去 ⇒ 这句当场变红）
    expect(box.placeholder).toBe('');
    const hints = [...root.querySelectorAll('.modal .hint')].map((h) =>
      (h.textContent ?? '').trim(),
    );
    expect(hints).toContain('Required; empty = keep unchanged (the backend rejects empty strings)');
  });
});
