/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Row } from '../core/api.service';
import { Crud } from '../core/crud';
import { Act, Table } from '../components/table';
import { use } from '../core/i18n/i18n';
import { Risk } from './risk';

/**
 * 风控批次里「有表单 / 动钱」的两块（规则、风险用户）的写操作接线钉子；
 * 其余标签页（事件 / 团伙 / 反作弊 / IP / 设备）在 risk-actions.spec.ts。
 * 钉四件事：**端点串 + HTTP 方法 + 请求体形状**、**二次确认文案能认出对象**、
 * **回执取自服务端**（金额是服务端算的，不是前端编的）、**按状态过滤的行内按钮**。
 *
 * 本批特有的陷阱，各自单独钉：
 *  1. 规则的 update 是**全量**语义（RiskRuleController::fill 对 create/update 同一套必填 +
 *     status 缺省落 1）⇒ 局部更新会把停用的规则改回启用。钉子必须走 submit()（承载 fullEdit
 *     分支的那一层），并且要有 DOM→Row 那条路（见本文件的「DOM→Row→PUT」用例：页面级
 *     submit(values) 会绕过 FormModal.fire()，把组件层改坏也全绿）。
 *  2. 启停是**服务端自己翻转**（路径带 hashid、请求体为空），不是 PUT update {status}。
 *  3. 冻结/解冻的金额是 bcmath 十进制**字符串**，前端原样上送（过一次 Number 就没了小数位）。
 */
describe('风控模块写操作接线', () => {
  let http: HttpTestingController;
  let confirmSpy: ReturnType<typeof vi.spyOn>;
  let promptSpy: ReturnType<typeof vi.spyOn>;

  /** 让被测代码跑过 await 的微任务，好让它发出的下一个请求进到 HttpTestingController */
  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  beforeEach(() => {
    // 本文件按中文断界面文案（如按 textContent==='编辑' 找行内按钮）⇒ 语言显式定
    use('zh');
    // 两个组件（Risk / Table）在这里一次性声明：build() 走 runInInjectionContext 会**实例化**
    // 测试模块，之后再 configureTestingModule 就是 "already been instantiated"
    TestBed.configureTestingModule({
      imports: [Risk, Table],
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
    confirmSpy = vi.spyOn(globalThis, 'confirm').mockReturnValue(true);
    promptSpy = vi.spyOn(globalThis, 'prompt').mockReturnValue('');
  });

  afterEach(() => {
    confirmSpy.mockRestore();
    promptSpy.mockRestore();
    try {
      http.verify();
    } finally {
      // 必须显式复位：否则用例失败留下的未决请求会污染下一个用例的 configureTestingModule
      TestBed.resetTestingModule();
    }
  });

  /** 页面方法多是 protected，测试侧按鸭子类型取用；inject(Api) 在字段初始化器里 ⇒ 必须有注入上下文 */
  const build = <T>(make: () => T): T => TestBed.runInInjectionContext(make);
  const url = (x: { request: { url: string } } | { url: string }): string =>
    ('request' in x ? x.request.url : x.url).split('?')[0]!;

  type P = {
    tab: { set(v: string): void };
    rows(): Row[];
    crud(): Crud | null;
    actions(): Act[];
    run(row: Row, key: string): Promise<void>;
    submit(values: Row): Promise<void>;
    panel(): string;
    result(): unknown;
    timelineRows(): Row[];
    note(): string;
    noteErr(): boolean;
    formError(): string;
  };

  const RULE: Row = {
    id: 'RULE1',
    name: '夜间提现激增',
    type: 'frequency',
    action: 'warn',
    scope: 'all',
    priority: 100,
    status: 0,
    config: '{"max_count":10,"window_minutes":720}',
  };

  describe('风控规则', () => {
    /** 页面级取数：rules 标签页（每条用例都从这一步开始） */
    const loadRules = async (p: P, list: Row[] = [RULE]): Promise<void> => {
      p.tab.set('rules');
      const done = (p as unknown as { load(): Promise<void> }).load();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/rule/list')
        .flush({ code: 0, message: 'ok', data: { list, total: list.length } });
      await done;
    };

    it('ends：create 走 /create，update/toggle 带 hashid；statused + fullEdit', () => {
      const p = build(() => new Risk()) as unknown as P;
      p.tab.set('rules');
      const c = p.crud()!;
      expect(c.ends.create).toBe('/admin/v1/risk/rule/create');
      expect(c.ends.update?.('RULE1')).toBe('/admin/v1/risk/rule/RULE1');
      // 函数形态：hashid 在路径里。写成固定串（/risk/rule/toggle）就是 404
      expect(typeof c.ends.toggle).toBe('function');
      expect((c.ends.toggle as (id: string) => string)('RULE1')).toBe(
        '/admin/v1/risk/rule/RULE1/toggle',
      );
      expect(c.ends.remove).toBeUndefined();
      expect(c.statused).toBe(true);
      // 缺了它，编辑一次就会把停用中的规则改成启用（status 缺失后端落 1）
      expect(c.fullEdit).toBe(true);
      // 字段名必须与 fill() 读的键一致（差一个就是 422 或者静默不落库）
      expect(c.fields.map((f) => f.name)).toEqual([
        'name',
        'type',
        'action',
        'scope',
        'priority',
        'status',
        'config',
      ]);
      // 编辑 / 启用停用（statused+toggle）/ 试算（extra）；没有删除端点 ⇒ 不出删除
      expect(p.actions().map((a) => a.key)).toEqual(['edit', 'toggle', 'test']);
    });

    it('启停：POST /risk/rule/{hashid}/toggle，**不带请求体**（服务端自己翻转，不认客户端给的 status）', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadRules(p);
      const done = p.run(p.rows()[0]!, 'toggle');
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/risk/rule/RULE1/toggle');
      expect(req.request.body).toBeNull();
      req.flush({ code: 0, message: 'ok', data: { status: 1 } });
      await tick();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/rule/list')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
    });

    it('编辑是全量：与旧值相同的字段照样发（含停用开关 status=0）', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadRules(p);
      await p.run(p.rows()[0]!, 'edit');
      const done = p.submit({
        name: '夜间提现激增',
        type: 'frequency',
        action: 'block',
        scope: 'all',
        priority: '100',
        status: 0,
        config: '{"max_count":10,"window_minutes":720}',
      });
      const put = http.expectOne((r) => r.method === 'PUT');
      expect(url(put)).toBe('/admin/v1/risk/rule/RULE1');
      // 只改了 action —— 但 fill() 每个字段都从请求体读，缺 name/type 直接 422，
      // 缺 status 后端落成 1（启用）⇒ 必须全量
      expect(put.request.body).toEqual({
        name: '夜间提现激增',
        type: 'frequency',
        action: 'block',
        scope: 'all',
        priority: 100,
        status: 0,
        config: '{"max_count":10,"window_minutes":720}',
      });
      put.flush({ code: 0, message: 'ok', data: [] });
      await tick();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/rule/list')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
    });

    /**
     * DOM → Row 那条路也要走一遍：直接 submit(values) 会**绕过 FormModal.fire()**，
     * 组件层（select 预选、switch 未勾落 0、textarea 的 JSON 文本）改坏了它照样绿
     * —— 上一批的实拍教训。
     */
    it('DOM→Row→PUT：模板驱动点「编辑」，只改处置方式，提交的仍是全量', async () => {
      const f: ComponentFixture<Risk> = TestBed.createComponent(Risk);
      const p = f.componentInstance as unknown as P;

      f.detectChanges(); // ngOnInit → 总览
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/overview')
        .flush({ code: 0, message: 'ok', data: {} });
      await tick();

      p.tab.set('rules');
      const done = (p as unknown as { load(): Promise<void> }).load();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/rule/list')
        .flush({ code: 0, message: 'ok', data: { list: [RULE], total: 1 } });
      await done;
      f.detectChanges();

      // 点真的那个「编辑」按钮（不是直接调 run()）
      const edit = Array.from(f.nativeElement.querySelectorAll('.acts button') as NodeListOf<HTMLButtonElement>).find(
        (b) => b.textContent?.trim() === '编辑',
      )!;
      edit.click();
      f.detectChanges();

      const el = <T extends Element>(sel: string): T => {
        const node = f.nativeElement.querySelector(sel) as T | null;
        if (!node) throw new Error(`未找到 ${sel}`);
        return node;
      };
      // 预填：停用中的规则开关是**未勾**的（on() 只认 1）
      expect(el<HTMLInputElement>('input[name="status"]').checked).toBe(false);
      expect(el<HTMLTextAreaElement>('textarea[name="config"]').value).toBe(
        '{"max_count":10,"window_minutes":720}',
      );
      el<HTMLSelectElement>('select[name="action"]').value = 'block';

      const form = el<HTMLFormElement>('form');
      // 必须冒泡：宿主上若另有一条原生 submit 监听，会把 output 的正确值覆盖掉（见 form-modal.ts:112）
      form.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true }));
      await f.whenStable();

      const put = http.expectOne((r) => r.method === 'PUT');
      expect(url(put)).toBe('/admin/v1/risk/rule/RULE1');
      // config 原样回写（列表回的是字符串，model 对 config 没有 cast）、status 保住 0
      expect(put.request.body).toEqual({
        name: '夜间提现激增',
        type: 'frequency',
        action: 'block',
        scope: 'all',
        priority: 100,
        status: 0,
        config: '{"max_count":10,"window_minutes":720}',
      });
      put.flush({ code: 0, message: 'ok', data: [] });
      await tick();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/rule/list')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await tick();
      expect(p.formError()).toBe('');
    });

    /**
     * scope 下拉的注记（2026-10-02 追加）。前提是后端事实，不是文案偏好：
     * `RiskService::check()` 全仓只有两个调用点 —— deposit（PaymentController:124）与
     * withdraw（WithdrawController:173），评估器里 exchange / login 那两条分支没有调用方
     * ⇒ 选这两项建的规则**永不命中**，而界面上它俩和 all/deposit/withdraw 长得一模一样。
     * 两项**保留**（产品接上调用点后直接可用），所以钉子钉两头：
     * ① 选项还在（删了两项产品就没得选）；② 注记真渲染出来，而不是「词条没跟上」露出的原键串。
     * ⚠ 「hint 没渲染出来」时 `.hint` 查不到 ⇒ 必须显式判空，否则 not.toBe(key) 会恒真。
     */
    it('scope：exchange/login 两项保留，且下拉格子里有「暂未接入」的注记（不是原键串）', async () => {
      const f: ComponentFixture<Risk> = TestBed.createComponent(Risk);
      const p = f.componentInstance as unknown as P;

      f.detectChanges(); // ngOnInit → 总览
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/overview')
        .flush({ code: 0, message: 'ok', data: {} });
      await tick();
      await loadRules(p);
      f.detectChanges();

      const edit = Array.from(f.nativeElement.querySelectorAll('.acts button') as NodeListOf<HTMLButtonElement>).find(
        (b) => b.textContent?.trim() === '编辑',
      )!;
      edit.click();
      f.detectChanges();

      const scope = f.nativeElement.querySelector('select[name="scope"]') as HTMLSelectElement | null;
      if (!scope) throw new Error('未找到 select[name="scope"]');

      const values = Array.from(scope.options).map((o) => o.value);
      expect(values).toContain('exchange'); // 别删：产品要做时直接用
      expect(values).toContain('login');

      const hint = scope.closest('div')?.querySelector('.hint') as HTMLElement | null;
      if (!hint) throw new Error('scope 字段的格子里没有 .hint —— 注记没渲染出来');
      expect(hint.textContent?.trim()).not.toBe('risk.rule.scope_hint'); // 原键串 = 词条没跟上
      expect(hint.textContent).toContain('exchange');
      expect(hint.textContent).toContain('login');
    });

    it('试算：POST /risk/rule/test {rule_id,user_id,check_type,context}，结构化结果进抽屉', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadRules(p);
      promptSpy.mockReturnValueOnce('UHASH1').mockReturnValueOnce('withdraw').mockReturnValueOnce('{"amount":"1000"}');
      const done = p.run(p.rows()[0]!, 'test');
      await tick();
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/risk/rule/test');
      expect(req.request.body).toEqual({
        rule_id: 'RULE1',
        user_id: 'UHASH1',
        check_type: 'withdraw',
        context: { amount: '1000' },
      });
      const result = { matched: true, message: '窗口内 withdraw 次数 12 ≥ 10', severity: 'high', action: 'warn' };
      req.flush({ code: 0, message: 'ok', data: result });
      await done;
      // 结果整个进抽屉（只显示 message 会把 matched/severity/action 抹掉）
      expect(p.result()).toEqual(result);
      expect(p.panel()).toBe('result');
    });

    it('试算：context 不是 JSON 对象时挡在前端（服务端会静默换成 []）', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadRules(p);
      promptSpy.mockReturnValueOnce('').mockReturnValueOnce('login').mockReturnValueOnce('[1,2]');
      await p.run(p.rows()[0]!, 'test');
      await tick();
      expect(p.noteErr()).toBe(true);
      expect(p.note()).toContain('JSON 对象');
      http.expectNone(() => true);
    });
  });

  describe('风险事件', () => {
    const EVENT: Row = {
      id: 'EV1',
      user_id: 'UHASH9',
      rule_id: 'RULE1',
      rule_name: '夜间提现激增',
      type: 'withdraw',
      action: 'block',
      created_at: '2026-09-30 10:00:00',
    };

    it('驳回：二次确认文案带规则名与用户 → handle {decision,note}', async () => {
      const p = build(() => new Risk()) as unknown as P;
      p.tab.set('events');
      const done = (p as unknown as { load(): Promise<void> }).load();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/event/list')
        .flush({ code: 0, message: 'ok', data: { list: [EVENT], total: 1 } });
      await done;

      promptSpy.mockReturnValue('误报，已人工核对');
      const acted = p.run(p.rows()[0]!, 'reject');
      expect(confirmSpy).toHaveBeenCalled();
      const text = String(confirmSpy.mock.calls[0]![0]);
      expect(text).toContain('夜间提现激增');
      expect(text).toContain('UHASH9');

      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/risk/event/EV1/handle');
      expect(req.request.body).toEqual({ decision: 'reject', note: '误报，已人工核对' });
      req.flush({ code: 0, message: 'ok', data: { message: '已记录人工处置（操作审计可查）' } });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await acted;
      // 回执是服务端那句话（自己编一句「操作成功」就把它盖掉了）
      expect(p.note()).toContain('已记录人工处置');
    });

    it('确认：不弹二次确认；note 取消输入则一个请求都不发', async () => {
      const p = build(() => new Risk()) as unknown as P;
      p.tab.set('events');
      const done = (p as unknown as { load(): Promise<void> }).load();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [EVENT], total: 1 } });
      await done;

      promptSpy.mockReturnValue(null);
      await p.run(p.rows()[0]!, 'approve');
      await tick();
      expect(confirmSpy).not.toHaveBeenCalled();
      http.expectNone(() => true);
    });
  });

  describe('风险用户（钱路）', () => {
    const USER: Row = {
      user_id: 'UHASH7',
      username: 'bob',
      score: 30,
      band: 'risk',
      hit_count: 3,
      whitelisted: 0,
    };

    const loadUsers = async (p: P): Promise<void> => {
      p.tab.set('users');
      const done = (p as unknown as { load(): Promise<void> }).load();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/users')
        .flush({ code: 0, message: 'ok', data: { list: [USER], total: 1 } });
      await done;
    };

    /**
     * 时间轴是**只读**的，路径里的 id 取自行上的 `user_id`（这一页的行**没有** id 字段），
     * 结果摊平成行进抽屉；顺带钉住「一个写请求都不发」。
     */
    it('时间轴：GET /risk/users/{user_id}/timeline → 事件摊平成行进抽屉（不发写请求）', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadUsers(p);
      const done = p.run(p.rows()[0]!, 'timeline');
      const req = http.expectOne(
        (r) => r.method === 'GET' && url(r) === '/admin/v1/risk/users/UHASH7/timeline',
      );
      req.flush({
        code: 0,
        message: 'ok',
        data: {
          user_id: 'UHASH7',
          total: 2,
          events: [
            { time: '2026-10-01 10:00:00', source: 'risk', type: 'frequency', action: 'block', result: 'blocked', detail: '' },
            { time: '2026-09-30 22:00:00', source: 'play', type: 'bet', action: 'win', result: '', detail: 'bet=10 win=0' },
          ],
        },
      });
      await done;
      expect(p.panel()).toBe('timeline');
      expect(p.timelineRows().map((r) => r['source'])).toEqual(['risk', 'play']);
    });

    /**
     * 关联图谱与时间轴同源：都认行上的 `user_id`（这一页的行**没有** id 字段），都只读。
     * 结果**不摊平**（nodes/edges 与 device_clusters 形状差得远）⇒ 原样进 result()，由 risk-graph 按 mode 呈现。
     */
    it('关联图谱：GET /risk/graph/{user_id} → 原样进抽屉、面板是 graph（不发写请求）', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadUsers(p);
      const done = p.run(p.rows()[0]!, 'graph');
      const req = http.expectOne(
        (r) => r.method === 'GET' && url(r) === '/admin/v1/risk/graph/UHASH7',
      );
      req.flush({
        code: 0,
        message: 'ok',
        data: { root: 'UHASH7', nodes: [], edges: [], cluster_size: 1, hops: 0, risk_verdict: 'normal' },
      });
      await done;
      expect(p.panel()).toBe('graph');
      expect((p.result() as Row)['cluster_size']).toBe(1);
      expect(http.match((r) => r.method !== 'GET').length).toBe(0);
    });

    it('冻结：二次确认能认出是谁 → POST hold **无请求体** → 回执带服务端算出的 frozen_amount', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadUsers(p);
      const done = p.run(p.rows()[0]!, 'hold');
      expect(String(confirmSpy.mock.calls[0]![0])).toContain('bob');
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/risk/users/UHASH7/hold');
      expect(req.request.body).toBeNull();
      req.flush({ code: 0, message: 'success', data: { user_id: 'UHASH7', frozen_amount: '250.00000000' } });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
      expect(p.note()).toContain('250.00000000');
    });

    it('解冻：金额是字符串原样上送（过 Number 会吃掉小数位）；留空 = 不带 amount（全额）', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadUsers(p);
      // 行必须先抓住：第一次 run 成功后会刷新列表，第二次 p.rows()[0] 已经是 undefined
      const row = p.rows()[0]!;
      promptSpy.mockReturnValue('100.00000000');
      const done = p.run(row, 'release');
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/risk/users/UHASH7/release');
      expect(req.request.body).toEqual({ amount: '100.00000000' });
      req.flush({ code: 0, message: 'success', data: { released_amount: '100.00000000' } });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
      expect(p.note()).toContain('100.00000000');

      // 留空 = 服务端按全额解冻（不发明细，也不发空串）
      promptSpy.mockReturnValue('   ');
      const done2 = p.run(row, 'release');
      const req2 = http.expectOne((r) => r.method === 'POST');
      expect(req2.request.body).toBeNull();
      req2.flush({ code: 0, message: 'success', data: { released_amount: '250.00000000' } });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done2;
    });

    it('解冻：取消金额输入（prompt=null）＝ 放弃，不发请求', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadUsers(p);
      promptSpy.mockReturnValue(null);
      await p.run(p.rows()[0]!, 'release');
      await tick();
      http.expectNone(() => true);
    });
  });

  // 关联团伙 / 反作弊 / IP 信誉 / 设备的钉子拆在 risk-actions.spec.ts
  // —— 与本文件同一套测法（同一个页面的别几个标签页），拆开只为满足「文件 <500 行」。
});
