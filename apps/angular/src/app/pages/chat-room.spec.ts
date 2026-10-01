/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Signal, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, ParamMap, convertToParamMap, provideRouter } from '@angular/router';
import { BehaviorSubject } from 'rxjs';
import { ChatMessage, Conversation, FriendUser } from '../core/api.service';
import { Avatars } from '../core/avatar';
import { ChatRoomPage } from './chat-room';

const U2: FriendUser = { id: 'U2', username: 'bob', nickname: null, avatar: null };
const U3: FriendUser = { id: 'U3', username: 'carol', nickname: '卡罗', avatar: null };

/** 对方发来的两条：**is_read 故意一个 0 一个 1** —— 用来钉「已读状态不参与渲染」 */
const M_THEIRS_UNREAD: ChatMessage = {
  id: 'M1',
  from_user_id: 'U2',
  to_user_id: 'ME',
  content: '在吗',
  is_read: 0,
  created_at: '2026-10-01 10:00:00',
};
const M_THEIRS_READ: ChatMessage = {
  id: 'M2',
  from_user_id: 'U2',
  to_user_id: 'ME',
  content: '好的',
  is_read: 1,
  created_at: '2026-10-01 10:01:00',
};
const M_MINE: ChatMessage = {
  id: 'M3',
  from_user_id: 'ME',
  to_user_id: 'U2',
  content: '在的',
  is_read: 0,
  created_at: '2026-10-01 10:02:00',
};

const ok = (data: unknown) => ({ code: 0, message: 'ok', data });
const page1 = (items: ChatMessage[], last = 1): Record<string, unknown> => ({
  code: 0,
  message: 'ok',
  data: { items, total: items.length, page: 1, last_page: last },
});
/** 无 [formGroup] 的表单：(submit) 收到的事件必须可取消，否则页面里那句 preventDefault 会抛 */
const submit = (): Event => new Event('submit', { cancelable: true });
const CONV: Conversation = { peer: U2, last_message: '在吗', unread_count: 1, updated_at: '2026-10-01 10:00:00' };
const CONV3: Conversation = { peer: U3, last_message: '嗨', unread_count: 0, updated_at: '2026-10-01 09:00:00' };

type Sig<T> = { (): T; set(v: T): void };
type Probe = {
  load(): void;
  more(): void;
  send(ev: Event): void;
  peerId(): string;
  peerName(): string;
  val(ev: Event): string;
  msgs: Sig<ChatMessage[]>;
  page: Sig<number>;
  lastPage: Sig<number>;
  loading: Sig<boolean>;
  moreBusy: Sig<boolean>;
  err: Sig<string>;
  moreErr: Sig<string>;
  draft: Sig<string>;
  sending: Sig<boolean>;
  sendErr: Sig<string>;
  peer: Sig<FriendUser | null>;
};

/**
 * 聊天室（`/chat/:hashid`）。
 * 三条**写错了不报错、只静默显示错东西**的坑各有一组用例：
 *  ① `GET /chat/messages/{peer}` 调用即已读 ⇒ 进房间**只许打这一个** GET，绝不能再去打 /chat/read；
 *  ② 同一次响应里刚被标已读的仍是 `is_read:0` ⇒ 页面**不渲染任何已读标记**（快照天生是旧的）；
 *  ③ 非好友发送的 403 原文透出、草稿不清。
 * 外加：发完必须重拉（回包没有正文）＋ 分页前插 ＋ 名称回落链。
 */
describe('ChatRoomPage（请求面）', () => {
  let http: HttpTestingController;
  let params: BehaviorSubject<ParamMap>;

  const setup = (hashid = 'U2'): ChatRoomPage => {
    params = new BehaviorSubject(convertToParamMap({ hashid }));
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        // 直接 new 出来的页面不在路由出口里，ActivatedRoute 要自己喂（同 search.spec 的理由）
        { provide: ActivatedRoute, useValue: { paramMap: params.asObservable() } },
        { provide: Avatars, useValue: { of: (): Signal<string> => signal('') } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    return TestBed.runInInjectionContext(() => new ChatRoomPage());
  };

  /** 构造时的两条：messages?page=1 与（认名字用的）conversations */
  const flushInit = (items: ChatMessage[] = [M_MINE], last = 1, convs: Conversation[] = [CONV]): void => {
    http.expectOne((r) => r.url === '/api/v1/chat/messages/U2').flush(page1(items, last));
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list: convs }));
  };

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  const probe = (p: ChatRoomPage): Probe => p as unknown as Probe;

  it('① 进房间只打 messages?page=1；**绝不打 /chat/read**（那次 GET 本身就是回执）', () => {
    const p = probe(setup());
    const req = http.expectOne((r) => r.url === '/api/v1/chat/messages/U2');
    expect(req.request.method).toBe('GET');
    expect(req.request.params.get('page')).toBe('1');
    req.flush(page1([M_THEIRS_UNREAD, M_MINE]));
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list: [CONV] }));
    expect(p.msgs().length).toBe(2);
    // 页面不许自己"补一个标记已读"：服务端在 messages 那次请求里已经做了
    http.expectNone('/api/v1/chat/read');
    http.expectNone('/api/v1/chat/unread-total');
  });

  it('认名字：会话列表里有就用它，**不再多打 friends**', () => {
    const p = probe(setup());
    flushInit();
    expect(p.peer()!.id).toBe('U2');
    expect(p.peerName()).toBe('bob');
    http.expectNone('/api/v1/friend/list');
  });

  // ⚠ 拆成两条：同一个用例里 setup() 两次会撞「TestBed 已实例化」，而那会以
  // "Cannot configure the test module..." 的形式红在**第二条 setup** 上，看着像被测代码的问题。
  it('没聊过（会话列表里没有这个人）→ 回落好友列表认名字', () => {
    const p = probe(setup('U3'));
    http.expectOne((r) => r.url === '/api/v1/chat/messages/U3').flush(page1([]));
    // 会话列表里只有 U2 ⇒ 认不出 U3，才轮到好友列表
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list: [CONV] }));
    http.expectOne('/api/v1/friend/list').flush(ok({ list: [U2, U3] }));
    expect(p.peerName()).toBe('卡罗');
  });

  it('两处都没有 → 不硬编名字，标题回落成「对话」', () => {
    const p = probe(setup('U9'));
    http.expectOne((r) => r.url === '/api/v1/chat/messages/U9').flush(page1([]));
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list: [] }));
    http.expectOne('/api/v1/friend/list').flush(ok({ list: [] }));
    expect(p.peer()).toBeNull();
    expect(p.peerName()).toBe('对话');
  });

  it('路由参数换了就用**新** hashid 请求（组件复用，不许读 snapshot）', () => {
    probe(setup('U2'));
    http.expectOne((r) => r.url === '/api/v1/chat/messages/U2').flush(page1([]));
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list: [] }));
    http.expectOne('/api/v1/friend/list').flush(ok({ list: [] }));

    params.next(convertToParamMap({ hashid: 'U3' }));
    const req = http.expectOne((r) => r.url === '/api/v1/chat/messages/U3');
    expect(req.request.url).toContain('/U3');
    req.flush(page1([]));
    // 回含 U3 的会话，认名字就不必再打 friends（否则一条 verify() 会因为未决请求而红）
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list: [CONV3] }));
  });

  it('发送：POST /chat/send 带 to_user_id + content，成功后**重拉 page=1**并清草稿', () => {
    const p = probe(setup());
    flushInit();
    p.draft.set('  在的  ');
    p.send(submit());
    const req = http.expectOne('/api/v1/chat/send');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ to_user_id: 'U2', content: '在的' });
    expect(p.sending()).toBe(true);
    req.flush(ok({ id: 'M9', created_at: '2026-10-01 10:03:00' }));
    expect(p.draft()).toBe('');
    expect(p.sending()).toBe(false);
    // 回包只有 id/created_at、**没有正文** ⇒ 想看见刚发的那条只能重拉
    http.expectOne((r) => r.url === '/api/v1/chat/messages/U2').flush(page1([M_MINE]));
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list: [CONV] }));
    http.expectNone((r) => r.url === '/api/v1/chat/send');
  });

  it('③ 非好友发送：403 文案**原样**透出，草稿不清、不重拉', () => {
    const p = probe(setup());
    flushInit();
    p.draft.set('在的');
    p.send(submit());
    http
      .expectOne('/api/v1/chat/send')
      .flush({ code: 403, message: 'Only friends can send messages', data: [] });
    expect(p.sendErr()).toBe('Only friends can send messages');
    expect(p.sending()).toBe(false);
    // 草稿留着：用户得先去加好友，回来接着发
    expect(p.draft()).toBe('在的');
    http.expectNone((r) => r.url === '/api/v1/chat/messages/U2');
  });

  it('空白内容不发请求（服务端 422 原文留给真发出去的那种错）', () => {
    const p = probe(setup());
    flushInit();
    p.draft.set('   ');
    p.send(submit());
    http.expectNone('/api/v1/chat/send');
    p.draft.set('x');
    p.send(submit());
    http.expectOne('/api/v1/chat/send').flush(ok({ id: 'M9', created_at: '' }));
    http.expectOne((r) => r.url === '/api/v1/chat/messages/U2').flush(page1([]));
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list: [CONV] }));
  });

  it('分页：last_page>1 才有入口，点了打 page=2 且**前插**在旧消息前面', () => {
    const p = probe(setup());
    flushInit([M_THEIRS_READ], 2);
    expect(p.page()).toBe(1);
    expect(p.lastPage()).toBe(2);
    p.more();
    const req = http.expectOne((r) => r.url === '/api/v1/chat/messages/U2');
    expect(req.request.params.get('page')).toBe('2');
    req.flush({ code: 0, message: 'ok', data: { items: [M_THEIRS_UNREAD], total: 2, page: 2, last_page: 2 } });
    expect(p.msgs().map((m) => m.id)).toEqual(['M1', 'M2']);
    expect(p.page()).toBe(2);
  });

  it('拉更早失败只出提示条，**不把已有消息换成错误页**', () => {
    const p = probe(setup());
    flushInit([M_THEIRS_READ], 2);
    p.more();
    http
      .expectOne((r) => r.url === '/api/v1/chat/messages/U2')
      .flush({ code: 500, message: '翻页炸了', data: [] });
    expect(p.moreErr()).toBe('翻页炸了');
    expect(p.err()).toBe('');
    expect(p.msgs().map((m) => m.id)).toEqual(['M2']);
    expect(p.moreBusy()).toBe(false);
  });
});

describe('ChatRoomPage（DOM 级）', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<ChatRoomPage>;

  const setup = (hashid = 'U2'): void => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: { paramMap: new BehaviorSubject(convertToParamMap({ hashid })).asObservable() },
        },
        { provide: Avatars, useValue: { of: (): Signal<string> => signal('') } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(ChatRoomPage);
    fixture.detectChanges();
  };

  const flushInit = (items: ChatMessage[], last = 1): void => {
    http.expectOne((r) => r.url === '/api/v1/chat/messages/U2').flush(page1(items, last));
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list: [CONV] }));
    fixture.detectChanges();
  };

  const el = (): HTMLElement => fixture.nativeElement;

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('② is_read **不参与渲染**：对方两条（0 与 1）渲染出的 class 完全相同', () => {
    setup();
    flushInit([M_THEIRS_UNREAD, M_THEIRS_READ, M_MINE]);
    const rows = [...el().querySelectorAll('.row.msg')];
    expect(rows.length).toBe(3);
    // 阳性对照：文本真的渲染出来了（否则下面"class 相同"可能只是因为压根没渲染）
    expect(rows[0]!.textContent).toContain('在吗');
    expect(rows[1]!.textContent).toContain('好的');
    // is_read:0 的那条与 is_read:1 的那条同类；与自己发的那条不同类（按发送方分，不按已读分）
    expect(rows[0]!.className).toBe(rows[1]!.className);
    expect(rows[0]!.className).not.toBe(rows[2]!.className);
  });

  it('② 消息行上没有任何「已读/未读」标记（快照天生是旧的，渲染出来就是自己骗自己）', () => {
    setup();
    flushInit([M_THEIRS_UNREAD, M_MINE]);
    // 判据只在**消息行**里找，不看整页：页顶那句说明本来就写着"已读"（它讲的是回执语义，
    // 不是逐条标记），整页断言会把那句说明本身判成缺陷。
    const rows = [...el().querySelectorAll('.row.msg')];
    expect(rows.length).toBe(2);
    const MARK = /未读|已读/;
    // 阳性对照：这个正则咬得住真正的逐条标记（否则下面那句 not.toMatch 恒真）
    expect(MARK.test('未读 1')).toBe(true);
    expect(MARK.test(rows.map((r) => r.textContent).join('|'))).toBe(false);
  });

  it('自己的消息按 from_user_id 判定（对方那侧全是别人发的）', () => {
    setup();
    flushInit([M_THEIRS_UNREAD, M_MINE]);
    const rows = [...el().querySelectorAll('.row.msg')];
    expect(rows[0]!.classList.contains('mine')).toBe(false);
    expect(rows[1]!.classList.contains('mine')).toBe(true);
  });

  it('标题是对方名字，页面写明「新消息到达后刷新」', () => {
    setup();
    flushInit([M_MINE]);
    expect(el().querySelector('h2')!.textContent).toContain('bob');
    expect(el().textContent).toContain('新消息到达后刷新');
  });

  it('没消息时给「还没有聊过天」，不是空白卡片', () => {
    setup();
    flushInit([]);
    expect(el().textContent).toContain('还没有聊过天');
  });

  it('「加载更早」只在 last_page>1 时出现', () => {
    setup();
    flushInit([M_MINE], 1);
    expect(el().textContent).not.toContain('加载更早');
    // 同一条断言的反面：换成两页时它必须出现（否则上面那句恒真）
    TestBed.resetTestingModule();
    setup();
    flushInit([M_MINE], 2);
    expect(el().textContent).toContain('加载更早的消息');
  });
});
