/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Row } from '../core/api.service';
import { Crud, Field } from '../core/crud';
import { t, use } from '../core/i18n/i18n';
import { Act } from '../components/table';
import { Finance } from './finance';
import { Infra } from './infra';
import { Marketing } from './marketing';

/**
 * 资金批次（提现 / 阶梯限额 / 支付方式 / 优惠券 / CDN）的写操作接线钉子。
 * 钉三件事：**端点串 + HTTP 方法 + 请求体形状**、**资金动作的二次确认文案带订单标识与金额**、
 * **动作回执取自服务端 message**（自己编一句「操作成功」＝ 把「打款成功」和「打款已提交」糊成一种）。
 *
 * 上一批（admin-writes.spec.ts）的教训：护栏只看函数名不看实参，端点打错照样绿。
 */
describe('资金模块写操作接线', () => {
  let http: HttpTestingController;
  let confirmSpy: ReturnType<typeof vi.spyOn>;

  /** 让被测代码跑过 await 的微任务，好让它发出的下一个请求进到 HttpTestingController */
  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  beforeEach(() => {
    // 界面文案已经是词条（键 → 译文）：语言真值在模块级、模块加载时读一次偏好 ⇒
    // 用例要显式定中文，否则断言的是英文那一列（服务端 message 不受影响）
    use('zh');
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
    confirmSpy = vi.spyOn(globalThis, 'confirm').mockReturnValue(true);
  });

  afterEach(() => {
    confirmSpy.mockRestore();
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  /** 页面方法多是 protected，测试侧按鸭子类型取用；inject(Api) 在字段初始化器里 ⇒ 必须有注入上下文 */
  const build = <T>(make: () => T): T => TestBed.runInInjectionContext(make);
  /** expectOne 回调拿到的是 HttpRequest，但别处拿到的是 TestRequest —— 两种都收 */
  const url = (x: { request: { url: string } } | { url: string }): string =>
    ('request' in x ? x.request.url : x.url).split('?')[0]!;

  const ORDER: Row = {
    id: 'OHASH1',
    order_no: 'WD20260101',
    platform_amount: '100.5000',
    fiat_amount: '98.0000',
    currency: 'USD',
    status: 'pending',
  };

  /** 金额字段一律 text（过 number 控件＝过一趟 JS Number，DECIMAL 的小数位会被吃掉） */
  const moneyFieldsAreText = (fields: Field[], names: string[]): void => {
    for (const n of names) {
      const f = fields.find((x) => x.name === n);
      expect(f, `字段 ${n} 不在 ${fields.map((x) => x.name).join('/')} 里`).toBeDefined();
      expect(f!.type).toBe('text');
    }
  };

  describe('Finance 提现订单', () => {
    type F = {
      tab: { set(v: string): void };
      load(): Promise<void>;
      rows(): Row[];
      crud(): Crud | null;
      actions(): Act[];
      run(row: Row, key: string): Promise<void>;
      extra(row: Row, key: string): Promise<void>;
      batch(action: 'approve' | 'reject'): Promise<void>;
      note(): string;
      noteErr(): boolean;
      error(): string;
      heads(): Record<string, string>;
    };

    const flushOrders = async (list: Row[]): Promise<void> => {
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/withdraw/orders')
        .flush({ code: 0, message: 'ok', data: { list, total: list.length } });
      await tick();
    };

    it('订单只有行内动作：ends 一个都不给（没有编辑/删除，也没有新建）', () => {
      const f = build(() => new Finance()) as unknown as F;
      const c = f.crud()!;
      // 订单没有 PUT/DELETE 端点、也没有「新建」——ends 给了就会出现点了必 404 的按钮
      expect(c.ends.create).toBeUndefined();
      expect(c.ends.update).toBeUndefined();
      expect(c.ends.remove).toBeUndefined();
      // 动作 = extra 声明的这几个（review 的三个 action 共用一个端点：通过/驳回/二次确认/打款/同步；
      // receipt 是只读的 PDF 导出，端点与形状都不同，单列一条）
      expect(f.actions().map((a) => a.key)).toEqual([
        'approve',
        'reject',
        'confirm',
        'payout',
        'sync',
        'receipt',
      ]);
      // 用户名在嵌套的 user 里 ⇒ 表格列里必须有一列是摊平后的（表头存的是词条键，显示时过 `| t`）
      expect(t(f.heads()['user_name']!)).toBe('用户');
      expect(t(f.heads()['platform_amount']!)).toBe('平台币');
    });

    it('用户列从嵌套 user 摊平出来（表格只认平铺标量）', async () => {
      const f = build(() => new Finance()) as unknown as F;
      const done = f.load();
      await flushOrders([{ ...ORDER, user: { id: 'UH1', username: 'bob' } }]);
      await done;
      expect(f.rows()[0]!['user_name']).toBe('bob');
      // 原始值不动：user 对象还在（别把后端结构改写了）
      expect((f.rows()[0]!['user'] as Row)['username']).toBe('bob');
    });

    it('执行打款：二次确认带订单号与金额 → POST execute-payout {order_id} → 回执是服务端 message', async () => {
      const f = build(() => new Finance()) as unknown as F;
      // 走 run()（生产路径：行内按钮 → run → extra），顺带钉住动作后**回读列表**
      const done = f.run(ORDER, 'payout');
      expect(confirmSpy).toHaveBeenCalled();
      const ask = String(confirmSpy.mock.calls[0]![0]);
      expect(ask).toContain('WD20260101'); // 订单标识（订单号）
      expect(ask).toContain('OHASH1'); // 端点要的 hashid 也在
      expect(ask).toContain('100.5000'); // 平台币金额，原样字符串
      expect(ask).toContain('98.0000');
      expect(ask).toContain('USD');

      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/withdraw/execute-payout');
      expect(req.request.body).toEqual({ order_id: 'OHASH1' });
      // 「打款已提交」≠「打款成功」（渠道受理 ≠ 已到账）⇒ 回执必须是服务端那一句
      req.flush({ code: 0, message: '打款已提交（渠道受理）', data: { payout_status: 'processing' } });
      await tick();
      await flushOrders([ORDER]); // run() 之后回读列表（不是乐观改行）
      await done;

      expect(f.note()).toContain('打款已提交（渠道受理）');
      expect(f.noteErr()).toBe(false);
      expect(f.error()).toBe('');
    });

    it('同步状态：POST sync-payout {order_id}；失败进横幅且**不把列表打成错误态**', async () => {
      const f = build(() => new Finance()) as unknown as F;
      const done = f.extra(ORDER, 'sync');
      expect(String(confirmSpy.mock.calls[0]![0])).toContain('同步');
      http
        .expectOne((r) => r.method === 'POST' && url(r) === '/admin/v1/withdraw/sync-payout')
        .flush({ code: 0, message: '同步完成', data: {} });
      await done;
      expect(f.note()).toContain('同步完成');

      const done2 = f.extra(ORDER, 'sync');
      http
        .expectOne((r) => r.method === 'POST')
        .flush(
          { code: 422, message: '该订单尚未执行打款' },
          { status: 422, statusText: 'Unprocessable Entity' },
        );
      await done2;
      expect(f.note()).toContain('该订单尚未执行打款');
      expect(f.noteErr()).toBe(true);
      // 动作失败是这件事的结论，不是列表加载失败 —— 运营还得靠这张表接着处理下一笔
      expect(f.error()).toBe('');
    });

    it('审核：approve/reject/confirm 三个 action 走同一个 PUT，值域原样发', async () => {
      const f = build(() => new Finance()) as unknown as F;
      for (const action of ['approve', 'reject', 'confirm'] as const) {
        const done = f.extra(ORDER, action);
        // 驳回=退款+流水、通过=放行资金、确认=双审第二票 ⇒ 三个都要二次确认
        expect(String(confirmSpy.mock.calls.at(-1)![0])).toContain('WD20260101');
        const req = http.expectOne((r) => r.method === 'PUT');
        expect(url(req)).toBe('/admin/v1/withdraw/review');
        // validator: action in approve,reject,confirm —— 少一个值就是 422
        expect(req.request.body).toEqual({ order_id: 'OHASH1', action });
        req.flush({ code: 0, message: '审核通过', data: [] });
        await done;
        expect(f.note()).toContain('审核通过');
      }
    });

    it('取消确认：一个请求都不发', async () => {
      const f = build(() => new Finance()) as unknown as F;
      confirmSpy.mockReturnValue(false);
      await f.extra(ORDER, 'payout');
      await tick();
      http.expectNone(() => true);
      expect(f.note()).toBe('');
    });

    it('批量审核：确认框逐条列出待处理订单的订单号与金额，ids 发 hashid', async () => {
      const f = build(() => new Finance()) as unknown as F;
      const done = f.load();
      await flushOrders([
        ORDER,
        { id: 'OHASH2', order_no: 'WD20260102', platform_amount: '5.0000', currency: 'USD', status: 'approved' },
      ]);
      await done;

      const done2 = f.batch('reject');
      const ask = String(confirmSpy.mock.calls[0]![0]);
      expect(ask).toContain('WD20260101');
      expect(ask).toContain('100.5000');
      // 已 approved 的那笔不是待审核 ⇒ 不进选中集（服务端也会跳过，但确认框不能骗人）
      expect(ask).not.toContain('WD20260102');

      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/withdraw/batch-review');
      expect(req.request.body).toEqual({ ids: ['OHASH1'], action: 'reject' });
      req.flush({ code: 0, message: '批量处理完成: 1 笔', data: { processed: 1, failed: [] } });
      await tick();
      await flushOrders([]); // 成败都回读（被拒的那几笔在服务端仍是 pending）
      await done2;
      expect(f.note()).toContain('批量处理完成: 1 笔');
    });

    it('提现开关：GET 读数，PUT 后**读响应里的新状态**（不做乐观更新）', async () => {
      const f = build(() => new Finance()) as unknown as F & {
        switchOn(): boolean;
        setSwitch(on: boolean): Promise<void>;
      };
      f.tab.set('switch');
      const done = f.load();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/withdraw/switch')
        .flush({ code: 0, message: 'ok', data: { global_switch: true, enabled: true, status: 1 } });
      await done;
      expect(f.switchOn()).toBe(true);

      const done2 = f.setSwitch(false);
      const req = http.expectOne((r) => r.method === 'PUT');
      expect(url(req)).toBe('/admin/v1/withdraw/switch');
      expect(req.request.body).toEqual({ enabled: 0 }); // validator: in:0,1
      req.flush({ code: 0, message: '操作成功', data: { global_switch: false, enabled: false, status: 0 } });
      await done2;
      expect(f.switchOn()).toBe(false);
      expect(f.note()).toContain('操作成功');
    });
  });

  describe('Finance 阶梯限额 / 支付方式', () => {
    type F = {
      tab: { set(v: string): void };
      crud(): Crud | null;
      actions(): Act[];
      openSetForm(): void;
      formFields(): Field[];
    };

    it('阶梯限额：金额字段全 text、create = 全局限额重置、没有 DELETE 就不出删除', () => {
      const f = build(() => new Finance()) as unknown as F;
      f.tab.set('limits');
      const c = f.crud()!;
      moneyFieldsAreText(c.fields, [
        'single_min',
        'single_max',
        'daily_limit',
        'monthly_limit',
        'fee_pct',
        'fee_max',
        'auto_approve_threshold',
      ]);
      // 全档位重置只有一个入口（POST limits/set），逐档精调走 PUT limits/{hashid}
      expect(c.ends.create).toBe('/admin/v1/withdraw/limits/set');
      expect(c.ends.update?.('LHASH1')).toBe('/admin/v1/withdraw/limits/LHASH1');
      // 阶梯限额没有 DELETE 路由 ⇒ 行里不该出现「删除」
      expect(c.ends.remove).toBeUndefined();
      expect(f.actions().some((a) => a.key === 'delete')).toBe(false);
      expect(f.actions().some((a) => a.key === 'toggle')).toBe(false);
    });

    it('全局限额重置：借用同一个表单但字段集不同（写穿档位的三个参数）', () => {
      const f = build(() => new Finance()) as unknown as F;
      f.tab.set('limits');
      expect(f.formFields().map((x) => x.name)).toEqual([
        'single_min',
        'single_max',
        'daily_limit',
        'monthly_limit',
        'fee_pct',
        'fee_max',
        'auto_approve_threshold',
      ]);
      f.openSetForm();
      expect(f.formFields().map((x) => x.name)).toEqual([
        'daily_limit',
        'min_amount',
        'auto_approve_threshold',
      ]);
      moneyFieldsAreText(f.formFields(), ['daily_limit', 'min_amount', 'auto_approve_threshold']);
    });

    it('支付方式：CRUD 全套 + 专用 toggle；金额区间是 text，status 在表单里（create 必填）', () => {
      const f = build(() => new Finance()) as unknown as F;
      f.tab.set('methods');
      const c = f.crud()!;
      expect(c.ends.create).toBe('/admin/v1/payment/method/create');
      expect(c.ends.update?.('MHASH1')).toBe('/admin/v1/payment/method/MHASH1');
      expect(c.ends.remove?.('MHASH1')).toBe('/admin/v1/payment/method/MHASH1');
      expect(c.ends.toggle).toBe('/admin/v1/payment/method/toggle');
      expect(c.statused).toBe(true);
      moneyFieldsAreText(c.fields, ['min_amount', 'max_amount']);
      expect(c.fields.find((x) => x.name === 'status')!.type).toBe('switch');
      // countries 是数组字段（后端收数组），别退化成「一格文本里塞 JSON」
      expect(c.fields.find((x) => x.name === 'countries')!.type).toBe('multi');
      // provider 的值域是 validator 的 in: 白名单（18 个），少一个都 422
      const providers = c.fields.find((x) => x.name === 'provider')!.options!;
      expect(providers.map((o) => o.value)).toContain('paypal');
      expect(providers.length).toBe(18);
    });
  });

  describe('Infra CDN 厂商', () => {
    type I = {
      tab: { set(v: string): void };
      load(): Promise<void>;
      crud(): Crud | null;
      actions(): Act[];
      extra(row: Row, key: string): Promise<void>;
      note(): string;
      noteErr(): boolean;
      error(): string;
    };
    const CDN: Row = { id: 'CHASH1', name: 'Cloudflare 主', provider: 'cloudflare', status: 1 };

    it('CRUD + 启停 + 连通测试都在，config 留空 = 不修改（列表不回传凭据）', () => {
      const f = build(() => new Infra()) as unknown as I;
      const c = f.crud()!;
      expect(c.ends.create).toBe('/admin/v1/cdn/provider/create');
      expect(c.ends.update?.('CHASH1')).toBe('/admin/v1/cdn/provider/CHASH1');
      expect(c.ends.remove?.('CHASH1')).toBe('/admin/v1/cdn/provider/CHASH1');
      expect(c.ends.toggle).toBe('/admin/v1/cdn/provider/toggle');
      expect(c.statused).toBe(true);
      expect(f.actions().map((a) => a.key)).toEqual(['edit', 'delete', 'toggle', 'test']);
      const config = c.fields.find((x) => x.name === 'config')!;
      // 列表 unset 掉 config（凭据不回传）⇒ 不 keepIfEmpty 的话改个名字就把凭据清成 null
      expect(config.keepIfEmpty).toBe(true);
      expect(c.fields.find((x) => x.name === 'status')!.type).toBe('switch');
    });

    it('连通测试：POST /cdn/provider/test {id}，结果就地显示（成功读 message、失败进横幅）', async () => {
      const f = build(() => new Infra()) as unknown as I;
      const done = f.extra(CDN, 'test');
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/cdn/provider/test');
      expect(req.request.body).toEqual({ id: 'CHASH1' });
      req.flush({ code: 0, message: '连通正常', data: [] });
      await done;
      expect(f.note()).toContain('连通正常');
      expect(f.noteErr()).toBe(false);

      const done2 = f.extra(CDN, 'test');
      http
        .expectOne((r) => r.method === 'POST')
        .flush(
          { code: 422, message: 'HeadBucket 403 AccessDenied' },
          { status: 422, statusText: 'Unprocessable Entity' },
        );
      await done2;
      // 探不通是这台厂商的结论，不是列表加载失败 ⇒ 别家在列表里照常可见
      expect(f.note()).toContain('AccessDenied');
      expect(f.noteErr()).toBe(true);
      expect(f.error()).toBe('');
    });
  });

  describe('Marketing 优惠券', () => {
    type K = {
      tab: { set(v: string): void };
      load(): Promise<void>;
      rows(): Row[];
      crud(): Crud | null;
      actions(): Act[];
    };

    it('优惠券 CRUD + 启停走 update（没有专用 toggle）；金额是 text、表单不摆 status 开关', async () => {
      const f = build(() => new Marketing()) as unknown as K;
      const done = f.load();
      // 券列表与游戏表一起取（game_id 的选项必须在弹框打开前就绪）
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/coupon/list')
        .flush({
          code: 0,
          message: 'ok',
          data: {
            list: [
              { id: 'K1', name: '满100减10', type: 'fixed', value: '10.0000', game_id: 0, status: 1 },
              { id: 'K2', name: '9折', type: 'rate', value: '0.1000', game_id: 'GHASH1', status: 1 },
            ],
            total: 2,
          },
        });
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/game/list')
        .flush({ code: 0, message: 'ok', data: { list: [{ id: 'GHASH1', name: '王者' }], total: 1 } });
      await done;

      const c = f.crud()!;
      expect(c.ends.create).toBe('/admin/v1/coupon/create');
      expect(c.ends.update?.('K1')).toBe('/admin/v1/coupon/K1');
      expect(c.ends.remove?.('K1')).toBe('/admin/v1/coupon/K1');
      // 券没有专用状态端点 ⇒ 启用/停用走局部 PUT {status}（基类 statused + ends.update）
      expect(c.ends.toggle).toBeUndefined();
      expect(c.statused).toBe(true);
      expect(f.actions().map((a) => a.key)).toEqual(['edit', 'delete', 'toggle']);

      moneyFieldsAreText(c.fields, ['value', 'min_amount', 'max_discount']);
      // create 硬编码 status=1（CouponController.php:116）⇒ 表单里摆开关就是骗人
      expect(c.fields.some((x) => x.name === 'status')).toBe(false);
      // conditions 是 JSON 列，admin 的 validator 不收它 ⇒ 表单里没有这个字段
      expect(c.fields.some((x) => x.name === 'conditions')).toBe(false);
      // game_id 的选项是游戏 hashid；空串不能发（update 里 decodeId('') 直接 400）
      const game = c.fields.find((x) => x.name === 'game_id')!;
      expect(game.keepIfEmpty).toBe(true);
      expect(game.options!.map((o) => o.value)).toEqual(['GHASH1']);

      // game_name 摊平给表格看，game_id=0 归一成空串（0 = 全平台，别让表单置顶勾一个叫 0 的游戏）
      expect(f.rows()[0]!['game_name']).toBe('全平台');
      expect(f.rows()[0]!['game_id']).toBe('');
      expect(f.rows()[1]!['game_name']).toBe('王者');
    });
  });
});
