/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Signal, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Conversation, FriendUser } from '../core/api.service';
import { Avatars } from '../core/avatar';
import { ChatPage } from './chat';

const U2: FriendUser = { id: 'U2', username: 'bob', nickname: null, avatar: null };
const U3: FriendUser = { id: 'U3', username: 'carol', nickname: '卡罗', avatar: null };

const C1: Conversation = {
  peer: U2,
  last_message: '在吗',
  unread_count: 3,
  updated_at: '2026-10-01 10:00:00',
};
const C2: Conversation = {
  peer: U3,
  last_message: '好的',
  unread_count: 0,
  updated_at: '2026-10-01 09:00:00',
};

const ok = (data: unknown) => ({ code: 0, message: 'ok', data });

/**
 * 消息页（会话列表）。
 * 钉的是**服务端没有会话表**这件事的两面：列表只由 conversations() 产生（不许拿好友列表补行），
 * 以及「打开对话即已读 ⇒ 回来要刷新」这句提示必须真的写在页面上（它是本页唯一能表达该语义的地方）。
 */
describe('ChatPage 会话列表（请求面）', () => {
  let http: HttpTestingController;
  let page: ChatPage;

  const setup = (): void => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', children: [] }]),
        { provide: Avatars, useValue: { of: (): Signal<string> => signal('') } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    page = TestBed.runInInjectionContext(() => new ChatPage());
  };

  beforeEach(setup);
  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  const probe = (): {
    load(): void;
    list: Signal<Conversation[]>;
    loading: Signal<boolean>;
    err: Signal<string>;
    name(u: FriendUser): string;
  } => page as unknown as never;

  it('进页面只打一次 /chat/conversations，不打任何会话详情', () => {
    const req = http.expectOne('/api/v1/chat/conversations');
    expect(req.request.method).toBe('GET');
    req.flush(ok({ list: [C1, C2] }));
    expect(probe().list().length).toBe(2);
    expect(probe().loading()).toBe(false);
    // 会话列表页不需要 messages —— 那是进房间才打的
    http.expectNone((r) => r.url.startsWith('/api/v1/chat/messages/'));
  });

  it('失败给错误态，重试再打一次', () => {
    http
      .expectOne('/api/v1/chat/conversations')
      .flush({ code: 500, message: '会话炸了', data: [] });
    expect(probe().err()).toBe('会话炸了');
    probe().load();
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list: [C1] }));
    expect(probe().err()).toBe('');
    expect(probe().list().length).toBe(1);
  });

  it('昵称优先，没有昵称用用户名', () => {
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list: [] }));
    expect(probe().name(U3)).toBe('卡罗');
    expect(probe().name(U2)).toBe('bob');
  });
});

describe('ChatPage（DOM 级）', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<ChatPage>;

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
    fixture = TestBed.createComponent(ChatPage);
    fixture.detectChanges();
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  const flush = (list: Conversation[]): void => {
    http.expectOne('/api/v1/chat/conversations').flush(ok({ list }));
    fixture.detectChanges();
  };
  const el = (): HTMLElement => fixture.nativeElement;

  it('每行是一个链到 /chat/{对方用户 hashid} 的 <a>（不是用户名，也不是会话 id）', () => {
    flush([C1]);
    const a = el().querySelector('a.row')!;
    expect(a.getAttribute('href')).toBe('/chat/U2');
  });

  it('未读数只在 >0 的行上出现（0 不显示「0」）', () => {
    flush([C1, C2]);
    const rows = [...el().querySelectorAll('a.row')];
    expect(rows.length).toBe(2);
    // C1 未读 3 → 有 chip；C2 未读 0 → 没有
    expect(rows[0]!.querySelector('.chip')!.textContent!.trim()).toBe('3');
    expect(rows[1]!.querySelector('.chip')).toBeNull();
  });

  it('行里显示的是最后一条摘要与对方名字', () => {
    flush([C1, C2]);
    const rows = [...el().querySelectorAll('a.row')];
    expect(rows[0]!.textContent).toContain('bob');
    expect(rows[0]!.textContent).toContain('在吗');
    expect(rows[1]!.textContent).toContain('卡罗');
  });

  it('空列表给的是「还没有聊天记录」+ 去好友列表的入口（不是错误态）', () => {
    flush([]);
    expect(el().textContent).toContain('还没有聊天记录');
    expect(el().querySelector('a.btn')!.getAttribute('href')).toBe('/friends');
  });

  it('页面上如实写着「新消息到达后刷新」（本树没有 WS 客户端，这是唯一的告知处）', () => {
    flush([]);
    expect(el().textContent).toContain('新消息到达后刷新');
  });
});
