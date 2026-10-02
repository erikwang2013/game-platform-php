/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, ParamMap, Router, convertToParamMap, provideRouter } from '@angular/router';
import { Subject } from 'rxjs';
import { routes } from '../app.routes';
import { Msg, t as tr } from '../core/i18n/i18n';
import { TicketsPage } from './tickets';

/**
 * 工单的 URL 深链（与公告页同一套；此前弹框只活在组件内存里）。
 *
 * 钉三件：
 * ① `/tickets/<hashid>` 直接打得开（详情对**非归属人**回 404 而不是 403，
 *    「找不到」里也包含「不是你的」，所以那条提示必须原样透出）；
 * ② 点行/关闭只改 URL，开合由 paramMap 驱动；
 * ③ 在途回包对号入座 + 关掉之后回包不许把弹框弹回来。
 */
describe('TicketsPage 深链', () => {
  let http: HttpTestingController;
  let params: Subject<ParamMap>;
  let page: TicketsPage;
  let nav: string[][];

  type Probe = {
    view(id: string): void;
    back(): void;
    cur(): { id: string; subject: string } | null;
    detailErr(): Msg;
    detailLoading(): boolean;
    typeLabel(t?: string): string;
    label(s: string): string;
    reply: { set(v: string): void };
    replyBusy(): boolean;
    send(): void;
  };
  const probe = (): Probe => page as unknown as Probe;

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
    page = TestBed.runInInjectionContext(() => new TicketsPage());
    nav = [];
    const router = TestBed.inject(Router);
    router.navigate = ((cmds: unknown[]) => {
      nav.push(cmds as string[]);
      return Promise.resolve(true);
    }) as Router['navigate'];
    // 构造函数里列表先发一发（服务端分页，列表项不含正文）
    http
      .expectOne((r) => r.url === '/api/v1/ticket/list')
      .flush({ code: 0, message: 'ok', data: { items: [], page: 1, last_page: 1, total: 0 } });
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

  it('路由表里两个形状都在，且详情路径带 authGuard（工单是私有数据）', () => {
    boot();
    const paths = routes.map((r) => r.path);
    expect(paths).toContain('tickets');
    expect(paths).toContain('tickets/:hashid');
    const detail = routes.find((r) => r.path === 'tickets/:hashid');
    // 私有页：深链不能因为「是个新路由」而漏掉守卫
    expect(detail?.canActivate?.length).toBe(1);
  });

  it('深链 /tickets/T1：直接取详情并渲染，列表页本身不发详情请求', () => {
    boot();
    http.expectNone((r) => r.url.includes('/api/v1/ticket/T'));
    params.next(convertToParamMap({ hashid: 'T1' }));

    const req = http.expectOne('/api/v1/ticket/T1');
    expect(req.request.method).toBe('GET');
    req.flush({
      code: 0,
      message: 'ok',
      data: { id: 'T1', subject: '提现没到账', status: 'open', content: '正文', replies: [] },
    });

    expect(probe().cur()?.subject).toBe('提现没到账');
    expect(probe().detailLoading()).toBe(false);
  });

  it('点行与关闭都只改 URL：组件自己不写「打开/收起」状态', () => {
    boot();
    probe().view('T1');
    expect(nav).toEqual([['/tickets', 'T1']]);
    probe().back();
    expect(nav).toEqual([['/tickets', 'T1'], ['/tickets']]);
    http.expectNone((r) => r.url.includes('/api/v1/ticket/T'));
  });

  it('在途回包对号入座：T1 的正文不许挂到 T2 的 URL 下', () => {
    boot();
    params.next(convertToParamMap({ hashid: 'T1' }));
    params.next(convertToParamMap({ hashid: 'T2' }));

    http.expectOne('/api/v1/ticket/T1').flush({
      code: 0,
      message: 'ok',
      data: { id: 'T1', subject: 'T1 的主题', status: 'open', content: 'x', replies: [] },
    });
    expect(probe().cur()).toBeNull();

    http.expectOne('/api/v1/ticket/T2').flush({
      code: 0,
      message: 'ok',
      data: { id: 'T2', subject: 'T2 的主题', status: 'open', content: 'x', replies: [] },
    });
    expect(probe().cur()?.id).toBe('T2');
  });

  it('关掉弹框之后，在途回包不许把弹框弹回来', () => {
    boot();
    params.next(convertToParamMap({ hashid: 'T1' }));
    params.next(convertToParamMap({}));
    http.expectOne('/api/v1/ticket/T1').flush({
      code: 0,
      message: 'ok',
      data: { id: 'T1', subject: 'T1', status: 'open', content: 'x', replies: [] },
    });

    expect(probe().cur()).toBeNull();
    expect(probe().detailLoading()).toBe(false);
  });

  /**
   * `send()` 里那条**嵌套**回读（回复成功后重拉详情，让服务端定状态）。它与 `open()` 是
   * **两条独立的在途请求**，`open()` 的判据管不到它 ⇒ 上面两条用例也盖不到它：
   * 那条路要先把回复发出去才会走到。删掉 `send()` 里 `if (this.loadingId !== d.id) return;`
   * 两条即变红（next 一条、error 一条）。
   *
   * 现场：回复发出 → 服务端回成功 → 页面回读详情 → 用户在回读落地前点了「关闭」。
   * 不挂判据的话：框自己弹回来；且此时地址栏已在 `/tickets`，再点「关闭」是
   * `navigate(['/tickets'])` 与当前 URL 同址 ⇒ `onSameUrlNavigation` 默认 `'ignore'`
   * ⇒ paramMap 不再发 ⇒ `clear()` 永不执行 ⇒ **框卡住关不掉**。
   */
  it('回复后的嵌套回读：关框后落地同样不许把弹框弹回来', () => {
    boot();
    params.next(convertToParamMap({ hashid: 'T1' }));
    http.expectOne('/api/v1/ticket/T1').flush({
      code: 0,
      message: 'ok',
      data: { id: 'T1', subject: 'T1', status: 'waiting', content: 'x', replies: [] },
    });

    probe().reply.set('已经帮你查了');
    probe().send();
    http
      .expectOne((r) => r.url === '/api/v1/ticket/T1/reply' && r.method === 'POST')
      .flush({ code: 0, message: 'ok', data: { id: 'R1' } });

    // 回读（第二次详情请求）+ 列表重拉，两条都还在路上
    const reread = http.expectOne('/api/v1/ticket/T1');
    const listReq = http.expectOne((r) => r.url === '/api/v1/ticket/list');

    params.next(convertToParamMap({})); // ← 用户在这一刻点了「关闭」
    reread.flush({
      code: 0,
      message: 'ok',
      data: { id: 'T1', subject: 'T1', status: 'waiting', content: 'x', replies: [] },
    });
    listReq.flush({ code: 0, message: 'ok', data: { items: [], page: 1, last_page: 1, total: 0 } });

    expect(probe().cur()).toBeNull(); // 不挂判据时这里被写回 ⇒ 框弹回来且关不掉
    expect(probe().detailErr()).toBe('');
    expect(probe().replyBusy()).toBe(false);
  });

  it('非归属工单回的是 404（不是 403）：原因原样透出，不装作加载中', () => {
    boot();
    params.next(convertToParamMap({ hashid: 'NOTMINE' }));
    http
      .expectOne('/api/v1/ticket/NOTMINE')
      .flush({ code: 404, message: '工单不存在' }, { status: 404, statusText: 'Not Found' });

    expect(probe().detailErr()).toBe('工单不存在');
    expect(probe().detailLoading()).toBe(false);
  });

  /**
   * 渲染级钉子：本批把整页字面量换成了键，**漏 `| t` / `| mt` 的两个症状**一次钉住 ——
   * 键名被原样印到屏幕上（`tickets.` 这样的前缀）、`Msg` 对象被插值成 `[object Object]`。
   *
   * 为什么本文件必须有这一条：其余用例钉的都是 URL 与在途回包，**一个模板读点都不看**；
   * 而「键引用」那条钉子只查得到「模板上写了个不存在的键」，查不到「模板上压根没写 `| t`」。
   * 三个读点各管一段：
   *   · 列表行 / 详情 —— 数据表存**键**（`typeLabel()`/`label()` 回的是键名），模板过 `| t`；
   *   · `error()` —— 服务端**原文**那一态（`.msg` 是 string，过不过 `| mt` 都看不出差别）；
   *   · `formErr()` —— **键**那一态（本地校验写 `{ key: … }`）⇒ 漏 `| mt` 会印成
   *     `[object Object]`。把模板上 `formErr` 那行的 `| mt` 去掉，本用例即红：这是 `| mt`
   *     在**本页**唯一的仪器（`send()`/`open()` 那几处服务端原文的 `| mt` 由类型与真机管）。
   */
  it('渲染：列表/详情/表单都过了表（键名不外泄、Msg 不印成 [object Object]）', () => {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: ActivatedRoute, useValue: { paramMap: params } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    const fixture = TestBed.createComponent(TicketsPage);
    fixture.detectChanges();
    http.expectOne((r) => r.url === '/api/v1/ticket/list').flush({
      code: 0,
      message: 'ok',
      data: {
        items: [
          {
            id: 'T1',
            subject: '提现没到账',
            type: 'withdraw',
            status: 'waiting',
            reply_count: 2,
            created_at: '2026-01-01 00:00:00',
          },
        ],
        page: 1,
        last_page: 1,
        total: 1,
      },
    });
    fixture.detectChanges();

    const el: HTMLElement = fixture.nativeElement;
    expect(el.textContent).toContain('客服工单');
    expect(el.textContent).toContain('提现没到账');
    expect(el.textContent).toContain('提现问题');
    expect(el.textContent).toContain('待回复'); // ← 本批改正的方向（见下一条用例）
    expect(el.textContent).toContain('2 条回复');
    expect(el.textContent).toContain('新建工单');
    expect(el.textContent).not.toMatch(/\b(?:tickets|common|wallet)\./);
    expect(el.textContent).not.toContain('[object Object]');

    // 详情：开框同样只走 URL（与其余用例同一套真值源约定）
    params.next(convertToParamMap({ hashid: 'T1' }));
    fixture.detectChanges();
    http.expectOne('/api/v1/ticket/T1').flush({
      code: 0,
      message: 'ok',
      data: {
        id: 'T1',
        subject: '提现没到账',
        type: 'withdraw',
        status: 'waiting',
        content: '正文',
        replies: [
          { id: 'R1', is_admin: true, content: '已处理', created_at: '2026-01-01 00:00:00' },
        ],
      },
    });
    fixture.detectChanges();
    expect(el.textContent).toContain('客服');
    expect(el.textContent).toContain('追加回复');
    expect(el.textContent).toContain('发送');
    expect(el.textContent).toContain('关闭');
    // placeholder 不进 textContent，单独取属性 —— 漏 `| t` 时这里印的是键名
    expect(el.querySelector('.modal textarea')?.getAttribute('placeholder')).toBe('补充说明…');
    expect(el.textContent).not.toMatch(/\b(?:tickets|common|wallet)\./);
    expect(el.textContent).not.toContain('[object Object]');

    // 关框（同样只改 URL）→ 开新建表单 → 空标题提交：本地校验落的是**键**那一态
    params.next(convertToParamMap({}));
    fixture.detectChanges();
    expect(el.querySelector('.modal')).toBeNull();
    (el.querySelector('.sect button') as HTMLButtonElement).click();
    fixture.detectChanges();
    expect(el.textContent).toContain('问题类型');
    expect(el.textContent).toContain('提交工单');
    (el.querySelector('.card.stack button.primary') as HTMLButtonElement).click();
    fixture.detectChanges();
    expect(el.textContent).toContain('请填写标题');
    expect(el.textContent).not.toContain('tickets.err_subject_required');
    expect(el.textContent).not.toContain('[object Object]');
  });

  /**
   * 类型 / 状态短码过表 —— 钉死 `waiting` 的**方向**（本批改的是语义错，不是译文口味）。
   *
   * `waiting` 是**用户**回复之后由服务端置上的：`TicketController::reply:166` 那条回复写的是
   * `is_admin = 0` ⇒ 真相是「等客服回」。本树原先译成「已回复」，把方向说反了（用户看到
   * 「已回复」会以为该自己等着，实际球在他这边）；现在照 react 树同格的 `ticket.status_waiting`
   * 取「待回复」。把 `STATUS_LABEL.waiting` 改回 `tickets.status_closed` 之类同表键，或把
   * `status_waiting` 的 zh 改回「已回复」，本用例即红 —— 这是那次改动的**唯一**仪器：
   * 本文件其余用例钉的是 URL 与在途回包，一个标签都不看。
   *
   * `typeLabel()` / `label()` 回的是**裸键名**（模板上过 `| t`）⇒ 断言走 `tr()` 查表。
   * 别用「恒等包一层」的写法（对字符串原样吐出 ⇒ 断言恒绿），这里直接 `tr()`。
   * 末三条钉**透传**：服务端将来加短码不许被吞成空白（`t()` 对认不出的键原样返回）。
   */
  it('类型/状态短码过表：waiting 是「待回复」不是「已回复」，未知短码原样透出', () => {
    boot();
    expect(tr(probe().label('open'))).toBe('待受理');
    expect(tr(probe().label('waiting'))).toBe('待回复');
    expect(tr(probe().label('closed'))).toBe('已关闭');
    expect(tr(probe().typeLabel('deposit'))).toBe('充值问题');
    expect(tr(probe().typeLabel('other'))).toBe('其他');
    // 服务端将来加的类型/状态：查不到就原样出，且 `t()` 也不认这个「键」⇒ 一路原样到屏幕上
    expect(tr(probe().typeLabel('brand_new'))).toBe('brand_new');
    expect(tr(probe().label('half_open'))).toBe('half_open');
    // 类型字段可空（服务端不强制）⇒ 占位符
    expect(tr(probe().typeLabel())).toBe('—');
  });
});
