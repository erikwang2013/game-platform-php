/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, ParamMap, Router, convertToParamMap, provideRouter } from '@angular/router';
import { Subject } from 'rxjs';
import { routes } from '../app.routes';
import { AnnouncementsPage } from './announcements';

/**
 * 公告的 URL 深链（此前弹框只活在组件内存里，地址栏不变 ⇒ 一条公告发不出去）。
 *
 * 钉三件：
 * ① `/announcements/<hashid>` 直接打得开，正文由详情接口取；
 * ② **URL 是唯一真值源** —— 点行/关闭只改 URL，弹框的开合由 paramMap 驱动，
 *    于是浏览器前进/后退天然正确，也不需要第二份状态；
 * ③ 在途回包按**对号入座**（A 的回包不许挂到 B 的 URL 下），关掉之后回包不许把弹框弹回来。
 */
describe('AnnouncementsPage 深链', () => {
  let http: HttpTestingController;
  let params: Subject<ParamMap>;
  let page: AnnouncementsPage;
  let nav: string[][];

  type Probe = {
    view(id: string): void;
    back(): void;
    cur(): { id: string; title: string } | null;
    detailErr(): string;
    detailLoading(): boolean;
  };
  const probe = (): Probe => page as unknown as Probe;

  /** 造一次页面；paramMap 用 Subject 是为了在同一次实例里模拟「先开 A、再开 B」 */
  const boot = (): void => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        // ApiBase 的字段初始化器要 inject(Router)
        provideRouter([]),
        { provide: ActivatedRoute, useValue: { paramMap: params } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    // inject(...) 写在字段初始化器里 ⇒ 必须在注入上下文里构造
    page = TestBed.runInInjectionContext(() => new AnnouncementsPage());
    nav = [];
    const router = TestBed.inject(Router);
    router.navigate = ((cmds: unknown[]) => {
      nav.push(cmds as string[]);
      return Promise.resolve(true);
    }) as Router['navigate'];
    // 构造函数里列表先发一发
    http
      .expectOne((r) => r.url === '/api/v1/announcement/list')
      .flush({ code: 0, message: 'ok', data: { list: [] } });
  };

  beforeEach(() => {
    params = new Subject<ParamMap>();
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('路由表里两个形状都在：列表页与 /announcements/:hashid 同组件', () => {
    boot();
    const paths = routes.map((r) => r.path);
    expect(paths).toContain('announcements');
    expect(paths).toContain('announcements/:hashid');
  });

  it('深链 /announcements/A1：直接取详情并渲染，列表页本身不弹框', () => {
    boot();
    http.expectNone((r) => r.url.includes('/announcement/detail/'));
    params.next(convertToParamMap({ hashid: 'A1' }));

    const req = http.expectOne('/api/v1/announcement/detail/A1');
    expect(req.request.method).toBe('GET');
    req.flush({ code: 0, message: 'ok', data: { id: 'A1', title: '维护通知', content: '正文' } });

    expect(probe().cur()?.title).toBe('维护通知');
    expect(probe().detailLoading()).toBe(false);
  });

  it('无 hashid（列表 URL）不发详情请求', () => {
    boot();
    params.next(convertToParamMap({}));
    http.expectNone((r) => r.url.includes('/announcement/detail/'));
    expect(probe().cur()).toBeNull();
  });

  it('点行与关闭都只改 URL：组件自己不写「打开/收起」状态', () => {
    boot();
    probe().view('A1');
    expect(nav).toEqual([['/announcements', 'A1']]);
    probe().back();
    expect(nav).toEqual([['/announcements', 'A1'], ['/announcements']]);
    // 改 URL 的两下都不该自己拉详情 —— 详情由 paramMap 订阅驱动
    http.expectNone((r) => r.url.includes('/announcement/detail/'));
  });

  it('在途回包对号入座：A 的正文不许挂到 B 的 URL 下', () => {
    boot();
    params.next(convertToParamMap({ hashid: 'A1' }));
    params.next(convertToParamMap({ hashid: 'B1' }));

    // A1 的响应晚到：此时 URL 已经是 B1
    http
      .expectOne('/api/v1/announcement/detail/A1')
      .flush({ code: 0, message: 'ok', data: { id: 'A1', title: 'A 的标题', content: 'A' } });
    expect(probe().cur()).toBeNull();

    http
      .expectOne('/api/v1/announcement/detail/B1')
      .flush({ code: 0, message: 'ok', data: { id: 'B1', title: 'B 的标题', content: 'B' } });
    expect(probe().cur()?.id).toBe('B1');
  });

  it('关掉弹框（URL 回到列表）之后，在途回包不许把弹框弹回来', () => {
    boot();
    params.next(convertToParamMap({ hashid: 'A1' }));
    params.next(convertToParamMap({}));
    http
      .expectOne('/api/v1/announcement/detail/A1')
      .flush({ code: 0, message: 'ok', data: { id: 'A1', title: 'A', content: 'A' } });

    expect(probe().cur()).toBeNull();
    expect(probe().detailLoading()).toBe(false);
  });

  it('详情取不到时把服务端原因透出，不装作加载中', () => {
    boot();
    params.next(convertToParamMap({ hashid: 'GONE' }));
    http
      .expectOne('/api/v1/announcement/detail/GONE')
      .flush({ code: 404, message: '公告不存在' }, { status: 404, statusText: 'Not Found' });

    expect(probe().detailErr()).toBe('公告不存在');
    expect(probe().detailLoading()).toBe(false);
  });
});
