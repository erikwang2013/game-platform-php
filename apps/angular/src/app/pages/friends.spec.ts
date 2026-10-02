/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Signal, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { FriendRequest, FriendUser } from '../core/api.service';
import { Avatars } from '../core/avatar';
import { Msg, t as tr } from '../core/i18n/i18n';
import { FriendsPage } from './friends';

type Sig<T> = { (): T; set(v: T): void };
type Probe = {
  load(): void;
  pick(t: 'list' | 'req' | 'add'): void;
  accept(r: FriendRequest): void;
  reject(r: FriendRequest): void;
  remove(f: FriendUser): void;
  add(u: FriendUser): void;
  doSearch(ev: Event): void;
  tab: Sig<'list' | 'req' | 'add'>;
  fList: Sig<FriendUser[]>;
  rList: Sig<FriendRequest[]>;
  fErr: Sig<Msg>;
  rErr: Sig<Msg>;
  loading: Sig<boolean>;
  busy: Sig<string>;
  ok: Sig<Msg>;
  actErr: Sig<Msg>;
  q: Sig<string>;
  hits: Sig<FriendUser[] | null>;
  friendIds(): Set<string>;
  name(u: { nickname: string | null; username: string }): string;
};

/**
 * `Msg` 的渲染：T 端的 `| mt` 走 `t(key, params)`，这里给自由函数一个同形的替身。
 *
 * 为什么必须有它：`ok` / `actErr` / `fErr` / `rErr` 本批改成了**两态**（服务端原文 / 词条键）。
 * 服务端原文那一态是 string（`ApiError.msg` 与 `.message` 同值），断言直接比字符串照样过；
 * **键**那一态是对象，直接比会恒假 ⇒ 必须渲染。这一层恰好也是「`params` 有没有传丢」的仪器：
 * `t()` 对 `params` 里没有的占位符**原样留着** `{name}`，所以「存了键但没带名字」会被下面
 * 那两条 `toBe('已接受 bob')` 抓住。
 */
const txt = (v: Msg): string => (typeof v === 'string' ? v : tr(v.key, v.params));

const U1: FriendUser = { id: 'U1', username: 'alice', nickname: '爱丽丝', avatar: null };
const U2: FriendUser = { id: 'U2', username: 'bob', nickname: null, avatar: null };
/** 关系 hashid 与用户 hashid **故意不同**：写反了用例要能红 */
const FR1: FriendRequest = { id: 'REL1', user: U2, created_at: '2026-10-01 10:00:00' };

const ok = (data: unknown) => ({ code: 0, message: 'ok', data });
/** 搜索表单的提交事件：`new Event('submit')` 默认不可取消，页面里那句 preventDefault 会抛 */
const submit = (): Event => new Event('submit', { cancelable: true });

/**
 * 好友页。钉的重点是**两个 id 不能混**（关系 id vs 用户 id）与**服务端文案原样透出** ——
 * 这两条错了不会报错，只会静默 404 / 静默吞掉"要先接受申请"的提示，真机上很难看出来。
 */
describe('FriendsPage 好友（请求面）', () => {
  let http: HttpTestingController;
  let page: FriendsPage;

  const probe = (): Probe => page as unknown as Probe;

  /** 构造时打的两个列表；两条各自独立降级，所以分别 flush */
  const flushInit = (friends: FriendUser[] = [], reqs: FriendRequest[] = []): void => {
    http.expectOne('/api/v1/friend/list').flush(ok({ list: friends }));
    http.expectOne('/api/v1/friend/requests').flush(ok({ list: reqs }));
  };

  /** 动作成功后页面会重拉两个列表（那条性质本身另有专门用例）——这里显式冲掉，免得 verify() 报未决请求 */
  const flushReload = (friends: FriendUser[] = [], reqs: FriendRequest[] = []): void => {
    http.expectOne('/api/v1/friend/list').flush(ok({ list: friends }));
    http.expectOne('/api/v1/friend/requests').flush(ok({ list: reqs }));
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', children: [] }]),
        // 头像要带 token 取字节（另有一条真图用例在文件末尾），这里不打真接口
        { provide: Avatars, useValue: { of: (): Signal<string> => signal('') } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    page = TestBed.runInInjectionContext(() => new FriendsPage());
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('进页面打两个列表，且不额外打别的', () => {
    flushInit([U1], [FR1]);
    expect(probe().fList().length).toBe(1);
    expect(probe().rList().length).toBe(1);
    expect(probe().loading()).toBe(false);
    http.expectNone('/api/v1/friend/search');
  });

  it('一个列表挂了另一个照常显示（不是全有全无）', () => {
    http.expectOne('/api/v1/friend/list').flush({ code: 500, message: 'boom', data: [] });
    http.expectOne('/api/v1/friend/requests').flush(ok({ list: [FR1] }));
    expect(probe().fErr()).toBe('boom');
    expect(probe().rErr()).toBe('');
    expect(probe().rList().length).toBe(1);
  });

  it('接受/拒绝传的是**关系** id（request_id），不是用户 id', () => {
    flushInit();
    probe().accept(FR1);
    const a = http.expectOne('/api/v1/friend/accept');
    expect(a.request.method).toBe('POST');
    expect(a.request.body).toEqual({ request_id: 'REL1' });
    // 关系 id 与用户 id 故意不同：写反就是这里红
    expect(a.request.body.friend_id).toBeUndefined();
    a.flush(ok([]));
    flushReload();

    probe().reject(FR1);
    const r = http.expectOne('/api/v1/friend/reject');
    expect(r.request.body).toEqual({ request_id: 'REL1' });
    r.flush(ok([]));
    flushReload();
  });

  it('删除好友传的是**用户** id（friend_id），不是关系 id', () => {
    flushInit();
    probe().remove(U2);
    const req = http.expectOne('/api/v1/friend/remove');
    expect(req.request.body).toEqual({ friend_id: 'U2' });
    expect(req.request.body.request_id).toBeUndefined();
    req.flush(ok([]));
    flushReload();
  });

  it('加好友传对方**用户** id', () => {
    flushInit();
    probe().add(U2);
    const req = http.expectOne('/api/v1/friend/request');
    expect(req.request.body).toEqual({ friend_id: 'U2' });
    req.flush(ok({ id: 'REL2' }));
    flushReload();
  });

  it('动作成功后**两个列表都重拉**（接受申请同时改两个列表，只刷一边必漏）', () => {
    flushInit();
    probe().accept(FR1);
    http.expectOne('/api/v1/friend/accept').flush(ok([]));
    http.expectOne('/api/v1/friend/list').flush(ok({ list: [U2] }));
    http.expectOne('/api/v1/friend/requests').flush(ok({ list: [] }));
    expect(probe().fList().length).toBe(1);
    expect(probe().rList().length).toBe(0);
    // 整句比：`{name}` 没传丢才算过（丢了这里拿到的是字面量 `已接受 {name}`）
    expect(txt(probe().ok())).toBe('已接受 bob');
  });

  it('「互相加不自动接受」：对方已有待处理申请时，422 原样透出且不重拉列表', () => {
    flushInit([], [FR1]);
    probe().add(FR1.user); // B 反过来加 A —— 服务端会挡
    http.expectOne('/api/v1/friend/request').flush({
      code: 422,
      message: 'Already friends or request pending',
      data: [],
    });
    expect(probe().actErr()).toBe('Already friends or request pending');
    expect(probe().busy()).toBe('');
    // 没成功就不该重拉（也不该把对方偷偷变成好友）
    http.expectNone('/api/v1/friend/list');
    expect(probe().fList().length).toBe(0);
  });

  it('不是收件人时服务端的 404 文案原样透出（不自造"你没有权限"之类）', () => {
    flushInit();
    probe().accept(FR1);
    http
      .expectOne('/api/v1/friend/accept')
      .flush({ code: 404, message: 'Request not found', data: [] });
    expect(probe().actErr()).toBe('Request not found');
    http.expectNone('/api/v1/friend/list');
  });

  it('删好友是幂等的：第二次删（服务端照样回成功）不报错', () => {
    flushInit([U2]);
    probe().remove(U2);
    http.expectOne('/api/v1/friend/remove').flush(ok([]));
    http.expectOne('/api/v1/friend/list').flush(ok({ list: [] }));
    http.expectOne('/api/v1/friend/requests').flush(ok({ list: [] }));
    expect(txt(probe().ok())).toBe('已删除好友 bob');
    expect(probe().actErr()).toBe('');

    // 再点一次（对方早就不在列表里了）：客户端不自己判"他是不是我好友"，照样发、照样成功
    probe().remove(U2);
    http.expectOne('/api/v1/friend/remove').flush(ok([]));
    http.expectOne('/api/v1/friend/list').flush(ok({ list: [] }));
    http.expectOne('/api/v1/friend/requests').flush(ok({ list: [] }));
    expect(probe().actErr()).toBe('');
  });

  it('搜索：空关键词不发请求（服务端对空 q 只回空列表，白跑一趟）', () => {
    flushInit();
    probe().q.set('   ');
    probe().doSearch(submit());
    http.expectNone('/api/v1/friend/search');
    expect(probe().hits()).toBeNull();
  });

  it('搜索：关键词进了 query，结果装进 hits', () => {
    flushInit();
    probe().q.set(' ali ');
    probe().doSearch(submit());
    const req = http.expectOne((r) => r.url === '/api/v1/friend/search');
    expect(req.request.params.get('q')).toBe('ali');
    req.flush(ok({ list: [U1] }));
    expect(probe().hits()!.length).toBe(1);
  });

  it('搜索失败把旧结果清掉（不拿上一次的结果冒充这一次）', () => {
    flushInit();
    probe().q.set('ali');
    probe().doSearch(submit());
    http.expectOne((r) => r.url === '/api/v1/friend/search').flush(ok({ list: [U1] }));
    expect(probe().hits()!.length).toBe(1);

    probe().doSearch(submit());
    http
      .expectOne((r) => r.url === '/api/v1/friend/search')
      .flush({ code: 500, message: '搜索炸了', data: [] });
    expect(probe().hits()).toBeNull();
    expect(probe().actErr()).toBe('搜索炸了');
  });

  it('名字优先取昵称，没昵称用用户名', () => {
    flushInit();
    expect(probe().name(U1)).toBe('爱丽丝');
    expect(probe().name(U2)).toBe('bob');
  });

  it('403 的服务端文案原样透出（不包装成「操作失败」）', () => {
    flushInit();
    probe().remove(U2);
    http
      .expectOne('/api/v1/friend/remove')
      .flush({ code: 403, message: 'Access to this file is denied', data: [] });
    expect(probe().actErr()).toBe('Access to this file is denied');
  });
});

/**
 * DOM 级：模板**真的按数据分流**。
 * 上面那组全是读信号，把模板改错（比如搜索结果里无条件摆「加好友」）它们照样全绿。
 */
describe('FriendsPage（DOM 级）', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<FriendsPage>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', children: [] }]),
        { provide: Avatars, useValue: { of: (): Signal<string> => signal('') } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(FriendsPage);
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  const init = (friends: FriendUser[], reqs: FriendRequest[]): void => {
    fixture.detectChanges();
    http.expectOne('/api/v1/friend/list').flush(ok({ list: friends }));
    http.expectOne('/api/v1/friend/requests').flush(ok({ list: reqs }));
    fixture.detectChanges();
  };

  it('好友列表：昵称、@用户名、没头像时用首字母，删除按钮在旁边', () => {
    init([U1], []);
    const el: HTMLElement = fixture.nativeElement;
    expect(el.querySelectorAll('.row').length).toBe(1);
    expect(el.textContent).toContain('爱丽丝');
    expect(el.textContent).toContain('@alice');
    // ⚠ 必须限定在 .row 里找：页头的「刷新」也是 button.btn.ghost，不限定会取到它
    expect(el.querySelector('.row button')!.textContent).toContain('删除');
    // 没有头像、也没有取到字节 ⇒ 首字母兜底，不留碎图
    expect(el.querySelector('.row img')).toBeNull();
    expect(el.querySelector('.row .av')!.textContent).toContain('爱');
    // 整页过表（本批把字面量换成了键）：键名不外泄、`Msg` 不印成 `[object Object]`
    expect(el.textContent).not.toMatch(/\b(?:friends|app|nav|common)\./);
    expect(el.textContent).not.toContain('[object Object]');
  });

  it('申请列表：接受与拒绝两个按钮都在，且点「接受」发的是关系 id', () => {
    init([], [FR1]);
    const el: HTMLElement = fixture.nativeElement;
    (fixture.componentInstance as unknown as Probe).pick('req');
    fixture.detectChanges();

    const btns = [...el.querySelectorAll<HTMLButtonElement>('.row button')];
    expect(btns.map((b) => b.textContent!.trim())).toEqual(['接受', '拒绝']);

    btns[0]!.click();
    const req = http.expectOne('/api/v1/friend/accept');
    expect(req.request.body).toEqual({ request_id: 'REL1' });
    req.flush(ok([]));
    http.expectOne('/api/v1/friend/list').flush(ok({ list: [U2] }));
    http.expectOne('/api/v1/friend/requests').flush(ok({ list: [] }));
    fixture.detectChanges();

    // 成功提示本批改成了**键 + `{name}` 参数**（在 `await` 之后才落值，存成串就把语言冻住）。
    // 这条同时钉三件：模板过了 `| mt`（否则是 `[object Object]`）、`params.name` 没传丢、
    // 名字取的是**被接受的那个人**（FR1.user = U2 = bob，不是关系 id REL1）。
    expect(el.textContent).toContain('已接受 bob');
    expect(el.textContent).not.toContain('[object Object]');
    expect(el.textContent).not.toContain('{name}');
  });

  it('搜索结果里已是好友的只给标记、不给「加好友」按钮（点了必 422，提示不好看）', () => {
    init([U1], []);
    const probe = fixture.componentInstance as unknown as Probe;
    probe.pick('add');
    fixture.detectChanges();

    probe.q.set('a');
    probe.doSearch(submit());
    http.expectOne((r) => r.url === '/api/v1/friend/search').flush(ok({ list: [U1, U2] }));
    fixture.detectChanges();

    const el: HTMLElement = fixture.nativeElement;
    const rows = [...el.querySelectorAll('.row')];
    expect(rows.length).toBe(2);
    expect(rows[0]!.querySelector('button')).toBeNull();
    expect(rows[0]!.textContent).toContain('已是好友');
    expect(rows[1]!.querySelector('button')!.textContent).toContain('加好友');
  });

  it('还没搜过时给的是"去搜"的引导，不是"没有找到"（两者不能混）', () => {
    init([], []);
    (fixture.componentInstance as unknown as Probe).pick('add');
    fixture.detectChanges();
    const el: HTMLElement = fixture.nativeElement;
    expect(el.textContent).toContain('搜人加好友');
    expect(el.textContent).not.toContain('没有找到');
    // placeholder 不进 textContent，单独取属性 —— 漏 `| t` 时这里印的是键名
    expect(el.querySelector('input[type=search]')?.getAttribute('placeholder')).toBe(
      '按用户名或昵称搜索',
    );
    expect(el.textContent).not.toContain('[object Object]');
  });
});

/**
 * `Avatars` 的真身（上面几组都是桩）。它管的是「相对地址必须带 token 取字节」这条 ——
 * 直接 `<img [src]="avatar">` 是碎图，取法见 core/avatar.ts 的注释。
 */
describe('Avatars 头像地址', () => {
  let http: HttpTestingController;
  let av: Avatars;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
    av = TestBed.runInInjectionContext(() => new Avatars());
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  /** fileBlob 里是 `switchMap(async …)`（要按 blob.type 分流 JSON 信封）⇒ 值不是同步落下的 */
  const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

  it('相对地址（落库值）带 token 取字节，拿到后信号里是可显示地址', async () => {
    // 落库形状按真值写：aetherupload 的 savedPath 是 `{group}_{subdir}_{name}`（下划线拼的，
    // 见 SavedPathResolver::encode），**不含斜杠** —— 路由段 /user/file/{savedPath} 也匹配不了斜杠。
    // 拿带目录的假路径当夹具，测的是 readUrl 的截断行为而不是本服务，属于自造输入。
    const s = av.of('/api/v1/user/file/image_202610_abc123.png');
    expect(s()).toBe('');
    const req = http.expectOne('/api/v1/user/file/image_202610_abc123.png');
    req.flush(new Blob(['x'], { type: 'image/png' }));
    await tick();
    expect(s()).toContain('blob:');
  });

  it('同一张图取两次只打一个请求（列表里同一个 peer 多处出现）', () => {
    const a = av.of('/api/v1/user/file/x.png');
    const b = av.of('/api/v1/user/file/x.png');
    expect(a).toBe(b);
    http.expectOne('/api/v1/user/file/x.png').flush(new Blob(['x'], { type: 'image/png' }));
  });

  it('第三方绝对地址直接用，不发请求；没有头像就更不该发', () => {
    expect(av.of('https://cdn.example.com/a.png')()).toBe('https://cdn.example.com/a.png');
    expect(av.of(null)()).toBe('');
    expect(av.of('')()).toBe('');
    http.expectNone(() => true);
  });

  it('取不到时回落空串（模板据此用首字母），不是把一个错误地址塞给 img', async () => {
    const s = av.of('/api/v1/user/file/image_202610_gone.png');
    // 同一轮里放一个**必然成功**的对照：让它和 s 等同样多的 tick 后翻成 blob:，
    // 才证明下面那个 '' 是"没翻"而不是"还没轮到"（否则这句断言恒真，红了也看不出来）
    const okS = av.of('/api/v1/user/file/image_202610_ok.png');
    // 失败是 HTTP 200 + JSON 信封被 responseType:'blob' 收成 Blob（见 upload.ts 的 fileBlob），
    // 所以这里也要回一个 type 为 json 的 Blob —— 回裸字符串的话 type 是 undefined，走不到那条分支
    http.expectOne('/api/v1/user/file/image_202610_gone.png').flush(
      new Blob([JSON.stringify({ code: 404, message: 'File not found', data: [] })], {
        type: 'application/json',
      }),
    );
    http
      .expectOne('/api/v1/user/file/image_202610_ok.png')
      .flush(new Blob(['x'], { type: 'image/png' }));
    await tick();
    expect(okS()).toContain('blob:');
    expect(s()).toBe('');
  });
});
