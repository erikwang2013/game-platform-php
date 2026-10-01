/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, ParamMap, Router, convertToParamMap, provideRouter } from '@angular/router';
import { BehaviorSubject } from 'rxjs';
import { SearchPage } from './search';

type Sig<T> = { (): T; set(v: T): void };
type Probe = {
  q: Sig<string>;
  items: Sig<{ id: string; name: string }[]>;
  loading: Sig<boolean>;
  error: Sig<string>;
  page: Sig<number>;
  total: Sig<number>;
  lastPage(): number;
  go(raw: string, ev: Event): void;
  to(p: number): void;
  retry(): void;
};

/**
 * 搜索页的契约（不是实现细节）：
 *  ① **请求里不许有 `type`** —— 该端点在公开组，`type=user` 等于未鉴权的用户批量导出
 *     （服务端已把入口强制成 game，客户端也不许去试）。这条断言会把"顺手加个 type 参数"钉红。
 *  ② **末页自己算**：回包没有 `last_page`（本树其余列表都有）。沿用 `read r.last_page` 的写法
 *     在这里恒 undefined ⇒ 分页器直接不出现，所以用 total=45 这条读数咬住 3。
 *  ③ 打字**不发请求**：输入框不做双向绑定，只有提交/翻页（= URL 变化）才打接口。
 */
describe('SearchPage 全局搜索', () => {
  let http: HttpTestingController;
  let params: BehaviorSubject<ParamMap>;

  const probe = (p: SearchPage): Probe => p as unknown as Probe;

  const setup = (init: Record<string, string> = {}): SearchPage => {
    params = new BehaviorSubject(convertToParamMap(init));
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        // 直接 new 出来的页面不在路由出口里，ActivatedRoute 要自己喂（同 me.spec 用 provideRouter 的理由）
        { provide: ActivatedRoute, useValue: { queryParamMap: params.asObservable() } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    return TestBed.runInInjectionContext(() => new SearchPage());
  };

  const body = (list: object[], total: number, page = 1): Record<string, unknown> => ({
    code: 0,
    message: 'ok',
    data: { list, total, page, per_page: 20 },
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('空关键词不打请求，列表置空（服务端也会回空，没必要来回一趟）', () => {
    const p = probe(setup());
    http.expectNone(() => true);
    expect(p.q()).toBe('');
    expect(p.items()).toEqual([]);
    expect(p.loading()).toBe(false);
  });

  it('带 q 进入：打一次 /search，参数只有 q/page/per_page —— **没有 type**', () => {
    const p = probe(setup({ q: ' 扑克 ' }));
    const req = http.expectOne((r) => r.url === '/api/v1/search');
    expect(req.request.method).toBe('GET');
    expect(req.request.params.get('q')).toBe('扑克');
    expect(req.request.params.get('page')).toBe('1');
    expect(req.request.params.get('per_page')).toBe('20');
    expect(req.request.params.has('type')).toBe(false);
    req.flush(body([{ id: 'G1', name: '德州扑克' }], 1));

    expect(p.loading()).toBe(false);
    expect(p.items().length).toBe(1);
    expect(p.total()).toBe(1);
  });

  it('末页由 total 自算（回包没有 last_page）：total=45 ⇒ 3 页', () => {
    const p = probe(setup({ q: 'a' }));
    // 刻意**不带** last_page —— 这正是真实回包的形状
    http.expectOne((r) => r.url === '/api/v1/search').flush(body([{ id: 'G1', name: 'A' }], 45));

    expect(p.lastPage()).toBe(3);
    expect(p.page()).toBe(1);
  });

  it('翻页：先 navigate 带 page，再由 URL 变化触发请求', () => {
    const p = probe(setup({ q: 'a' }));
    http.expectOne((r) => r.url === '/api/v1/search').flush(body([], 45));

    const nav = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    p.to(2);
    expect(nav).toHaveBeenCalledWith(['/search'], { queryParams: { q: 'a', page: 2 } });

    params.next(convertToParamMap({ q: 'a', page: '2' }));
    const req = http.expectOne((r) => r.url === '/api/v1/search');
    expect(req.request.params.get('page')).toBe('2');
    req.flush(body([{ id: 'G2', name: 'B' }], 45, 2));
    expect(p.page()).toBe(2);
  });

  it('提交：preventDefault + navigate，且**不带 page**（换词回到第 1 页）', () => {
    const p = probe(setup({ q: 'a', page: '3' }));
    http.expectOne((r) => r.url === '/api/v1/search').flush(body([], 45, 3));

    const nav = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    // 必须 cancelable：不可取消的事件上 preventDefault() 是空操作，defaultPrevented 恒 false（假钉子）
    const ev = new Event('submit', { cancelable: true });
    p.go('  新词  ', ev);

    expect(ev.defaultPrevented).toBe(true);
    expect(nav).toHaveBeenCalledWith(['/search'], { queryParams: { q: '新词' } });
  });

  it('url 里 q 为空串时也不打请求（不是只有缺参数才算空）', () => {
    const p = probe(setup({ q: '   ' }));
    http.expectNone(() => true);
    expect(p.q()).toBe('');
  });

  it('服务端拒绝：message 原样透出，重试按当前 q/page 重发', () => {
    const p = probe(setup({ q: 'a' }));
    http.expectOne((r) => r.url === '/api/v1/search').flush({
      code: 500,
      message: 'Search temporarily unavailable',
      data: [],
    });

    expect(p.error()).toBe('Search temporarily unavailable');
    expect(p.items()).toEqual([]);

    p.retry();
    const again = http.expectOne((r) => r.url === '/api/v1/search');
    expect(again.request.params.get('q')).toBe('a');
    again.flush(body([{ id: 'G1', name: 'A' }], 1));
    expect(p.error()).toBe('');
    expect(p.items().length).toBe(1);
  });

  it('DOM：输入框是 URL 里的词、打字不发请求，提交后才 navigate', () => {
    params = new BehaviorSubject(convertToParamMap({ q: '扑克' }));
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: ActivatedRoute, useValue: { queryParamMap: params.asObservable() } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    const fixture = TestBed.createComponent(SearchPage);
    fixture.detectChanges();
    http.expectOne((r) => r.url === '/api/v1/search').flush(body([{ id: 'G1', name: '德州扑克' }], 1));
    fixture.detectChanges();

    const box: HTMLInputElement = fixture.nativeElement.querySelector('input[type=search]');
    expect(box.value).toBe('扑克');
    expect(fixture.nativeElement.textContent).toContain('德州扑克');

    // 打字：DOM 值变了，但**没有任何请求**（输入框不是双向绑定的）
    const nav = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    box.value = '麻将';
    box.dispatchEvent(new Event('input'));
    fixture.detectChanges();
    http.expectNone(() => true);
    expect(nav).not.toHaveBeenCalled();

    // 真提交（点按钮，走浏览器原生 submit）—— 只读 value 是测不出门死活的
    const btn: HTMLButtonElement = fixture.nativeElement.querySelector('button[type=submit]');
    btn.click();
    expect(nav).toHaveBeenCalledWith(['/search'], { queryParams: { q: '麻将' } });
  });
});
