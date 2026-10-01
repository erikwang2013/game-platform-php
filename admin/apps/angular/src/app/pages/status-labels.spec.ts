/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { t, use } from '../core/i18n/i18n';
import { Admins } from './admins';
import { Finance } from './finance';
import { Infra } from './infra';
import { Marketing } from './marketing';
import { Settings } from './settings';

/** protected 成员按鸭子类型取用（与各页既有的 spec 同一手法） */
type Any = Record<string, any>;

/**
 * 状态列的「表头说人话 + 值说人话」两条。
 *
 * 缺陷原样：表头写着 `状态(0禁用/1启用)`（把数据库编码当运营标签），值是一枚光秃秃的 `0`；
 * 提现表更糟 —— 同一张表里 `pending` 与中文混排。
 * 修法：**头改词条、值另摊一列**（`*_label`），原值一个字都不动（表单预填、行内动作的判据、
 * 批量入参都读原值）。这里逐页钉住「列指过去了」+「值真的翻了」+「原值还在」。
 */
describe('状态列：表头不含数据库编码、值摊平一列译文', () => {
  let http: HttpTestingController;
  const api = <T>(p: unknown): T & Any => p as T & Any;

  const headsOf = (p: unknown): Record<string, string> => {
    const h = api<Any>(p)['heads'];
    return (typeof h === 'function' ? h() : h) as Record<string, string>;
  };

  beforeEach(() => {
    use('zh');
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
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

  function make<T>(ctor: new () => T): T {
    return TestBed.runInInjectionContext(() => new ctor());
  }

  /**
   * 跑一次 load()：先把**目标**请求（按 url 前缀认）应答成 list，再把这一页顺带取的
   * 别的资源（角色表/权限树/游戏表）用空列表收尾 —— 不收尾的话 afterEach 的 verify() 会红。
   */
  async function load(page: unknown, urlPrefix: string, list: unknown[]): Promise<void> {
    const done = api<Any>(page)['load']() as Promise<void>;
    await tick();
    const target = http.expectOne((r) => r.url.startsWith(urlPrefix));
    target.flush({ code: 0, message: 'ok', data: { list, total: list.length } });
    for (const other of http.match(() => true)) {
      other.flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
    }
    await done;
  }

  it('管理员列表：列指到 status_label，表头是「状态」而不是「状态(0禁用/1启用)」', async () => {
    const p = make(Admins);
    const heads = headsOf(p);
    expect(heads['status']).toBeUndefined();
    expect(heads['status_label']).toBe('admin.head.status');

    await load(p, '/admin/v1/user?', [{ id: 'A1', username: 'ops', status: 1 }]);
    const row = api<Any>(p)['rows']()[0];
    expect(row['status_label']).toBe('启用');
    // 原值一个字节都没动：行内启停/批量入参读的就是它
    expect(row['status']).toBe(1);
  });

  it('CDN 厂商：0 显示「停用」', async () => {
    const p = make(Infra);
    expect(headsOf(p)['status']).toBeUndefined();

    await load(p, '/admin/v1/cdn/provider/list', [{ id: 'C1', name: 'akamai', status: 0 }]);
    const row = api<Any>(p)['rows']()[0];
    expect(row['status_label']).toBe('停用');
    expect(row['status']).toBe(0);
  });

  it('角色：0/1 摊成停用/启用', async () => {
    const p = make(Settings);
    api<Any>(p)['tab'].set('role');
    expect(headsOf(p)['status']).toBeUndefined();

    await load(p, '/admin/v1/role', [{ id: 'R1', name: '运营', status: 1 }]);
    expect(api<Any>(p)['rows']()[0]['status_label']).toBe('启用');
  });

  it('优惠券：0/1 摊成停用/启用（筛选下拉读的是原值）', async () => {
    const p = make(Marketing);
    expect(headsOf(p)['status']).toBeUndefined();

    await load(p, '/admin/v1/coupon/list', [{ id: 'K1', name: '满减券', status: 1, game_id: 0 }]);
    const row = api<Any>(p)['rows']()[0];
    expect(row['status_label']).toBe('启用');
    expect(row['status']).toBe(1);
  });

  it('支付方式：0 停用 / 1 启用', async () => {
    const p = make(Finance);
    api<Any>(p)['tab'].set('methods');
    expect(headsOf(p)['status']).toBeUndefined();

    await load(p, '/admin/v1/payment/method/list', [{ id: 'M1', name: 'alipay', status: 0 }]);
    expect(api<Any>(p)['rows']()[0]['status_label']).toBe('停用');
  });

  it('提现订单：英文枚举摊成中文（待审核/打款处理中），原值留着给行内动作判 pending', async () => {
    const p = make(Finance);
    api<Any>(p)['tab'].set('orders');
    const heads = headsOf(p);
    expect(heads['status']).toBeUndefined();
    expect(heads['payout_status']).toBeUndefined();
    expect(heads['status_label']).toBe('withdraw.status');
    expect(heads['payout_status_label']).toBe('withdraw.payout_status');

    await load(p, '/admin/v1/withdraw/orders', [
      { id: 'O1', status: 'pending', payout_status: 'processing' },
    ]);
    const row = api<Any>(p)['rows']()[0];
    expect(row['status_label']).toBe('待审核');
    expect(row['payout_status_label']).toBe('打款处理中');
    // 行内动作与批量审核按 `status === 'pending'` 判 —— 原值必须还是英文枚举
    expect(row['status']).toBe('pending');
    expect(row['payout_status']).toBe('processing');
  });

  it('提现订单：没进打款流程的空 payout_status 是占位符，不是「打款失败」', async () => {
    const p = make(Finance);
    api<Any>(p)['tab'].set('orders');

    await load(p, '/admin/v1/withdraw/orders', [{ id: 'O2', status: 'completed', payout_status: '' }]);
    const row = api<Any>(p)['rows']()[0];
    expect(row['status_label']).toBe('已完成');
    expect(row['payout_status_label']).toBe('—');
  });

  it('认不出的状态原样透出：不编名字，也不把未知值说成「停用」', async () => {
    const p = make(Admins);
    await load(p, '/admin/v1/user?', [{ id: 'A2', username: 'odd', status: 'banned' }]);
    expect(api<Any>(p)['rows']()[0]['status_label']).toBe('banned');
  });

  /**
   * ③ 的另一半：**词条值本身**也不许再带数据库编码。
   * 上一条钉的是「列指过去了」，这一条钉「指过去以后看到的是什么」——
   * 只把 `status` 换成 `status_label`、词条还写着 `状态(0禁用/1启用)`，等于一个字没改。
   * 13 种语言逐个查：编码段在每种语言里都带 0/1 与括号，一查就现形。
   */
  it('表头词条值是平词：13 种语言都不含 0/1 编码与括号', async () => {
    const KEYS = [
      'admin.head.status',
      'cdn.head.status',
      'role.head.status',
      'coupon.head.status',
      'payment.status',
    ];
    for (const code of ['zh', 'en', 'ar', 'bn', 'de', 'es', 'fr', 'hi', 'id', 'ja', 'ko', 'pt', 'ru']) {
      await use(code);
      for (const key of KEYS) {
        const text = t(key);
        expect(text, `${code}/${key}`).not.toMatch(/[0-9]/);
        expect(text, `${code}/${key}`).not.toMatch(/[（(]/);
        expect(text, `${code}/${key}`).not.toBe(key); // 查不到会原样回键名
      }
    }
    await use('zh');
  });
});
