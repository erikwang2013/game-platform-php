/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Row } from '../core/api.service';
import { use } from '../core/i18n/i18n';
import { Users } from './users';

/** protected 成员按鸭子类型取用（与 users.spec.ts 同一手法） */
type Any = Record<string, any>;

/**
 * 用户详情抽屉里的**钱包与流水**（只读）。
 *
 * 钉四件事：① 钱包只认详情回包的 `data.wallet`（**没有这个键 = 没有钱包**，不是四个 0）；
 * ② 流水端点串 + `per_page` 别名（后端读的是 per_page，只发 page_size 会退回自己的默认值）；
 * ③ 流水取不到时**显示错误**（空表与加载失败在界面上长得一样，是最容易骗过自己的地方）；
 * ④ 关抽屉要把流水一起清掉（留着上一个人的记录是实打实的错）。
 * 另外钉一条只读边界：这一屏没有任何改余额的入口，动作只有翻页。
 */
describe('Users 钱包与流水', () => {
  let http: HttpTestingController;
  let page: Users;
  const api = (): Any => page as unknown as Any;

  beforeEach(() => {
    use('zh');
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
    page = TestBed.runInInjectionContext(() => new Users());
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  /**
   * 认请求只能用**路径**：`api` 是把参数拼进 URL 字符串再发的
   * （`api.service.ts` 的 `query()`：`full = url + query(params)`），**不是** `HttpParams`
   * ⇒ `request.url` 里带着查询串、`request.params` 恒空。本树既有用例同一手法。
   */
  const path = (r: { url: string }): string => r.url.split('?')[0]!;
  /** 反过来，查参数只能从 URL 里解回来（`params.get()` 在这个写法下恒为 null） */
  const q = (r: { url: string }): URLSearchParams => new URLSearchParams(r.url.split('?')[1] ?? '');

  const row: Row = { id: 'UHASH1', username: 'alice' };
  const DETAIL = '/admin/v1/platform/user/UHASH1';
  const TX = DETAIL + '/transactions';
  const txOk = (over: Record<string, unknown> = {}) => ({
    code: 0,
    message: 'ok',
    data: { items: [], total: 0, page: 1, per_page: 20, last_page: 1, ...over },
  });

  /** 开抽屉：详情与流水两个请求都要应答（open() 之后紧跟一次 loadTx()） */
  async function open(detail: Row, tx: Record<string, unknown> = txOk()): Promise<void> {
    const done = api()['open'](row) as Promise<void>;
    await tick();
    http.expectOne((r) => path(r) === DETAIL).flush({ code: 0, message: 'ok', data: detail });
    await tick();
    http.expectOne((r) => path(r) === TX).flush(tx);
    // flush 只是**同步**把响应交出去，api 那串 promise 链（envelope → list → 落 signal）
    // 还要走完微任务才轮到断言；`tick()` 是宏任务，先把它前面的微任务全放掉。
    await tick();
    await done;
  }

  it('钱包卡吃 data.wallet 的四个字符串金额；余额原样（不求和、不 parseFloat）', async () => {
    await open({
      ...row,
      wallet: {
        id: 'WH1',
        balance: '1234.56000000',
        frozen_balance: '10.00000000',
        total_earned: '9999.00000000',
        total_spent: '8764.44000000',
      },
    });

    const w = api()['wallet']() as Row;
    expect(w).not.toBeNull();
    // 原样是**字符串**：任何 Number() 化都会把 18 位整数 + 8 位小数截成 17 位有效数字
    expect(w['balance']).toBe('1234.56000000');
    expect(w['total_spent']).toBe('8764.44000000');
  });

  it('详情回包没有 wallet 键 = 该用户没有钱包（回 null，界面才能与「四个 0」分开说）', async () => {
    await open({ ...row });
    expect(api()['wallet']()).toBeNull();
  });

  it('流水端点串 + 分页参数：page 与 per_page 都要发出去（后端读 per_page）', async () => {
    const done = api()['open'](row) as Promise<void>;
    await tick();
    http.expectOne((r) => path(r) === DETAIL).flush({ code: 0, message: 'ok', data: { ...row } });
    await tick();

    const tx = http.expectOne((r) => path(r) === TX);
    // 端点串要逐字对：打到 /admin/v1/user/* 就是别人的钱包（或 404）
    expect(path(tx.request)).toBe(TX);
    expect(q(tx.request).get('page')).toBe('1');
    // 后端这个端点读的是 `per_page`：漏了它只会拿到服务端默认条数，界面还不报错
    expect(q(tx.request).get('per_page')).toBe('20');
    tx.flush(
      txOk({
        items: [
          {
            id: 'TX1',
            type: 'deposit',
            amount: '100.00000000',
            balance_after: '1234.56000000',
            ref_type: 'deposit_order',
            ref_id: 'R1',
            remark: '',
            created_at: '2026-10-01T04:00:00.000000Z',
          },
        ],
        total: 1,
      }),
    );
    await tick();
    await done;

    const rows = api()['txs']() as Row[];
    expect(rows.length).toBe(1);
    expect(rows[0]!['type']).toBe('deposit');
    expect(api()['txTotal']()).toBe(1);
    expect(api()['txError']()).toBe('');
  });

  it('流水取不到时留错误，不静默留空（空表与加载失败不能长一个样）', async () => {
    const done = api()['open'](row) as Promise<void>;
    await tick();
    http.expectOne((r) => path(r) === DETAIL).flush({ code: 0, message: 'ok', data: { ...row } });
    await tick();
    http.expectOne((r) => path(r) === TX).flush(
      { code: 500, message: '数据库连接失败', data: null },
      { status: 500, statusText: 'Server Error' },
    );
    await tick();
    await done;

    expect(api()['txs']()).toEqual([]);
    expect(api()['txError']()).toContain('数据库连接失败');
    expect(api()['txLoading']()).toBe(false);
  });

  it('翻页夹在 [1, 末页]；同一页不重复取数', async () => {
    await open({ ...row }, txOk({ total: 45 }));
    expect(api()['txPages']).toBe(3);

    // 越界回落到末页
    const jump = api()['goTx'](9) as void;
    expect(jump).toBeUndefined();
    await tick();
    const last = http.expectOne((r) => path(r) === TX);
    expect(q(last.request).get('page')).toBe('3');
    last.flush(txOk({ total: 45, page: 3 }));
    await tick();

    // 已经在第 3 页，再点一次不发请求
    api()['goTx'](3);
    await tick();
    http.expectNone((r) => path(r) === TX);
    // 往下越界回落到第 1 页
    api()['goTx'](0);
    await tick();
    const first = http.expectOne((r) => path(r) === TX);
    expect(q(first.request).get('page')).toBe('1');
    first.flush(txOk({ total: 45, page: 1 }));
    await tick();
  });

  it('关抽屉把流水一起清掉（下次开别人不会先闪一遍上一个人的记录）', async () => {
    await open({ ...row }, txOk({ total: 45 }));
    expect(api()['txTotal']()).toBe(45);

    api()['closeDetail']();
    expect(api()['detail']()).toBeNull();
    expect(api()['txs']()).toEqual([]);
    expect(api()['txTotal']()).toBe(0);
    expect(api()['txPage']()).toBe(1);
  });

  it('详情键值走 col.* 词条：抽屉里不摆 last_login_ip 这种裸列名', async () => {
    await open({
      ...row,
      real_name: '张三',
      last_login_ip: '203.0.113.9',
      updated_at: '2026-10-01T04:00:00.000000Z',
    });

    const labels = (api()['info']() as { label: string }[]).map((p) => p.label);
    expect(labels).toEqual(['ID', '用户名', '真实姓名', '最后登录IP', '更新时间']);
  });
});
