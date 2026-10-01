/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Row } from '../core/api.service';
import { use } from '../core/i18n/i18n';
import { Admins } from './admins';

/**
 * 翻页必须清勾选（`admins.spec.ts` 已顶到 500 行上限，故单开一支）。
 *
 * 缺陷形状：勾选态 `picked` 存的是 hashid，而屏幕上能反映它的只有**本页那 20 个复选框**。
 * `load()` 换掉整页行却不动 `picked` ⇒ 运营在第 1 页勾两行、翻到第 2 页看明细、顺手点
 * 「停用」，提交的是他以为早就取消掉的第 1 页那两行 —— **屏幕上没有任何提示**。
 * 与「有意跨页选择」不同，这是无意的延续，所以取清空、不加提示条（跨页批量真要做得成，
 * 得在批量条上显式写「已选 N 项（含其他页 M 项）」）。
 */
describe('Admins 翻页清勾选', () => {
  let http: HttpTestingController;
  const ME = { id: 'MEHASH', username: 'root' };
  const OTHER = { id: 'OTHERHASH', username: 'ops', status: 1 };
  const ROLES = [{ id: 'ROLE1', name: '运营', slug: 'ops' }];
  /** 必须 > pageSize(20)：基类 go() 会先夹到 pages()，total 不够就是原地不动、根本没翻页 */
  const TOTAL = 40;

  /** 让被测代码跑过 await 的微任务 */
  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  beforeEach(() => {
    use('en');
    // 必须在 new Admins() 之前落盘：Auth 是 providedIn:'root'，构造时就读 localStorage
    localStorage.setItem('ga_user', JSON.stringify(ME));
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    localStorage.clear();
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  /** 首屏与翻页都走 fetch() 里的 Promise.all：列表 + 角色候选，两个 GET 都得应答 */
  const answer = (rows: Row[]): void => {
    http
      .expectOne((r) => r.method === 'GET' && r.url.split('?')[0] === '/admin/v1/user')
      .flush({ code: 0, message: 'ok', data: { list: rows, total: TOTAL } });
    http
      .expectOne((r) => r.method === 'GET' && r.url.split('?')[0] === '/admin/v1/role')
      .flush({ code: 0, message: 'ok', data: { list: ROLES, total: ROLES.length } });
  };

  type P = {
    load(): Promise<void>;
    go(p: number): void;
    page(): number;
    /** 勾选态是受控信号：测试侧直接读写＝用户在表里勾了这几个 */
    picked: { (): string[]; set(v: string[]): void };
  };
  const build = (): P => TestBed.runInInjectionContext(() => new Admins()) as unknown as P;

  it('翻页清勾选：第 2 页提交的只该是第 2 页看得见的那些', async () => {
    const p = build();
    const first = p.load();
    answer([OTHER]);
    await first;
    p.picked.set(['OTHERHASH']);

    p.go(2);
    answer([OTHER]);
    await tick();

    expect(p.page()).toBe(2); // 前置：确实翻过去了（没翻成的话下面那条会因为别的原因绿）
    expect(p.picked()).toEqual([]);
  });
});
