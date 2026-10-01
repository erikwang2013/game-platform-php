/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Row } from '../core/api.service';
import { Crud } from '../core/crud';
import { t, use } from '../core/i18n/i18n';
import { Act, Table } from '../components/table';
import { CLUSTER_ACTS, DEVICE_ACTS } from './risk-fields';
import { Risk } from './risk';

/**
 * 风控页其余标签页（关联团伙 / 反作弊 / IP 信誉 / 设备）的写操作接线钉子。
 * 与 risk-writes.spec.ts 分开只为一个理由：**文件行数**（项目约定 <500 行），
 * 不是另一种测法 —— 规则与风险用户（有表单、动钱）的钉子在那边。
 *
 * 本文件单独钉：**多值状态 0/1/2 不是 0/1 翻转**、**字符串枚举状态**、
 * **状态已经等于目标的行不出按钮**、**列表掩码不可当原文用**（IP 与设备各一遍：
 * IP 只能靠运营输入的原文，设备的完整哈希由列表回传、掩码进请求体必 400）。
 */
describe('风控页：行内动作与掩码端点', () => {
  let http: HttpTestingController;
  let promptSpy: ReturnType<typeof vi.spyOn>;
  /** 设备那组用外层变量：断言失败会跳过用例里的 mockRestore ⇒ 漏到下一个用例（实测过一次），改由 afterEach 兜底 */
  let confirmSpy: ReturnType<typeof vi.spyOn> | null = null;

  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  beforeEach(() => {
    // 语言真值在模块级（模块加载时读一次偏好）⇒ 用例要显式定中文，不然界面文案是英文那一列
    use('zh');
    TestBed.configureTestingModule({
      imports: [Risk],
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
    promptSpy = vi.spyOn(globalThis, 'prompt').mockReturnValue('');
  });

  afterEach(() => {
    promptSpy.mockRestore();
    confirmSpy?.mockRestore();
    confirmSpy = null;
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  const build = <T>(make: () => T): T => TestBed.runInInjectionContext(make);
  const url = (x: { request: { url: string } } | { url: string }): string =>
    ('request' in x ? x.request.url : x.url).split('?')[0]!;

  type P = {
    tab: { set(v: string): void };
    rows(): Row[];
    crud(): Crud | null;
    actions(): Act[];
    run(row: Row, key: string): Promise<void>;
    detect(): Promise<void>;
    confirmCluster(row: Row): Promise<void>;
    ipAct(key: string): Promise<void>;
    heads(): Record<string, string>;
    candidates(): Row[];
    result(): unknown;
    panel(): string;
    panelTitle(): string;
    note(): string;
  };

  describe('关联团伙', () => {
    const CLUSTER: Row = { id: 'C1', name: '同设备 12 号', type: 'same_device', status: 1, user_count: 4 };

    const loadClusters = async (p: P, list: Row[] = [CLUSTER]): Promise<void> => {
      p.tab.set('clusters');
      const done = (p as unknown as { load(): Promise<void> }).load();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/clusters')
        .flush({ code: 0, message: 'ok', data: { list, total: list.length } });
      await done;
    };

    /**
     * 按钮的 when 过滤发生在 Table 那一层（模板 + acts()），所以在渲染层钉：
     * status=1 的行上「观察中」不该出现（点下去是把同一状态再写一遍），
     * 而只读的「成员」没有状态可言 ⇒ 一直在。
     */
    it('状态按钮按 when 过滤：当前状态的那个按钮不渲染，只读的「成员」不受影响', () => {
      const f = TestBed.createComponent(Table);
      f.componentRef.setInput('rows', [CLUSTER]);
      f.componentRef.setInput('actions', CLUSTER_ACTS);
      f.detectChanges();
      const labels = Array.from(
        f.nativeElement.querySelectorAll('.acts button') as NodeListOf<HTMLButtonElement>,
      ).map((b) => b.textContent?.trim());
      expect(labels).toEqual(['已处置', '标记误判', '成员']);
    });

    it('状态是 0/1/2 三值：PUT /risk/clusters/{hashid}/status 发数值，不是 0/1 翻转', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadClusters(p);
      const done = p.run(p.rows()[0]!, 'cl_2');
      const req = http.expectOne((r) => r.method === 'PUT');
      expect(url(req)).toBe('/admin/v1/risk/clusters/C1/status');
      expect(req.request.body).toEqual({ status: 2 });
      req.flush({ code: 0, message: 'ok', data: {} });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
      expect(p.note()).toContain('已处置');
    });

    it('列表摊平 status_label（0/1/2 的中文），原值不动', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadClusters(p, [CLUSTER, { ...CLUSTER, id: 'C2', status: 0 }]);
      // 摊平出来的是词条键，渲染时才成中文（断言走 t()，钉的仍是「这一列显示什么」）
      expect(t(String(p.rows()[0]!['status_label']))).toBe('观察中');
      expect(t(String(p.rows()[1]!['status_label']))).toBe('误判');
      expect(p.rows()[1]!['status']).toBe(0);
      expect(t(p.heads()['status_label']!)).toBe('状态');
    });

    /** 只读动作：路径里的 {hashid} 就是行上的 id，成员 id 出 API 边界也是 hashid；取完不刷新列表 */
    it('成员：GET /risk/clusters/{hashid}/members → 结果进抽屉（只读，不发写请求）', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadClusters(p);
      const done = p.run(p.rows()[0]!, 'members');
      const req = http.expectOne((r) => r.method === 'GET');
      expect(url(req)).toBe('/admin/v1/risk/clusters/C1/members');
      req.flush({
        code: 0,
        message: 'ok',
        data: {
          cluster: { id: 'C1', name: '同设备 12 号' },
          members: [
            { id: 'UHASH1', username: 'alice' },
            { id: 'UHASH2', username: 'bob' },
          ],
        },
      });
      await done;
      expect(p.panel()).toBe('members');
      expect(t(p.panelTitle())).toContain('团伙成员');
      const data = p.result() as Row;
      expect((data['members'] as Row[]).map((m) => m['id'])).toEqual(['UHASH1', 'UHASH2']);
      // 只读：不重取列表、不做任何写
      http.expectNone(() => true);
    });

    it('检测：候选进抽屉；确认只发 {type,fingerprint,name,user_count}（不发 member_ids）', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadClusters(p, []);
      const detected = p.detect();
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/risk/clusters/detect');
      req.flush({
        code: 0,
        message: 'ok',
        data: {
          window_days: 7,
          candidates: [
            { type: 'same_ip', fingerprint: 'a'.repeat(64), fingerprint_masked: 'aaaaaaaa****', user_count: 7 },
          ],
        },
      });
      await detected;
      expect(p.candidates().length).toBe(1);
      expect(p.panel()).toBe('candidates');

      // 抽屉里的候选行**不**走 run()（它不是当前标签页的行，没有 crud 模型）：
      // 模板直接 (act)="confirmCluster($event.row)" —— 钉的就是这条路
      promptSpy.mockReturnValue('团伙甲');
      const confirmed = p.confirmCluster(p.candidates()[0]!);
      const post = http.expectOne((r) => r.method === 'POST');
      expect(url(post)).toBe('/admin/v1/risk/clusters/confirm');
      // **不发 member_ids**：候选还没落库、没有 hashid，成员清单无从取得；不发才走服务端按指纹
      // 的**读时**回填（后来加入同 IP/同设备的账号会跟着出现）。理由全文见 risk.ts::confirmCluster
      expect(post.request.body).toEqual({
        type: 'same_ip',
        fingerprint: 'a'.repeat(64),
        name: '团伙甲',
        user_count: 7,
      });
      post.flush({ code: 0, message: 'ok', data: { cluster: { name: '团伙甲' } } });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await confirmed;
      expect(p.note()).toContain('团伙甲');
    });
  });

  describe('反作弊', () => {
    const AC: Row = {
      id: 'AC1',
      user_id: 'UHASH3',
      rule_type: 'win_rate',
      rule_name: '胜率异常',
      status: 'open',
    };

    const loadAc = async (p: P, list: Row[] = [AC]): Promise<void> => {
      p.tab.set('anticheat');
      const done = (p as unknown as { load(): Promise<void> }).load();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/anticheat/events')
        .flush({ code: 0, message: 'ok', data: { list, total: list.length } });
      await done;
    };

    it('待审核行上：确认/白名单/关闭 三个按钮，「重开」不出现（字符串枚举状态，不是 0/1 翻转）', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadAc(p);
      expect(p.actions().map((a) => a.key)).toEqual([
        'rv_confirmed',
        'rv_whitelisted',
        'rv_closed',
        'rv_open',
      ]);
      const shown = p
        .actions()
        .filter((a) => !a.when || a.when(p.rows()[0]!))
        .map((a) => t(a.label));
      expect(shown).toEqual(['确认作弊', '白名单', '关闭']);
    });

    it('加白：先收 note（Apidoc 要求）→ POST review {status,note}', async () => {
      const p = build(() => new Risk()) as unknown as P;
      await loadAc(p);
      promptSpy.mockReturnValue('职业选手账号');
      const done = p.run(p.rows()[0]!, 'rv_whitelisted');
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/anticheat/events/AC1/review');
      expect(req.request.body).toEqual({ status: 'whitelisted', note: '职业选手账号' });
      req.flush({ code: 0, message: 'ok', data: { message: '审核已记录' } });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
      expect(p.note()).toContain('审核已记录');
    });

    it('判定作弊要二次确认（文案能认出是谁），其余状态不问 note', async () => {
      confirmSpy = vi.spyOn(globalThis, 'confirm').mockReturnValue(true);
      const p = build(() => new Risk()) as unknown as P;
      await loadAc(p);
      const done = p.run(p.rows()[0]!, 'rv_confirmed');
      expect(String(confirmSpy.mock.calls[0]![0])).toContain('胜率异常');
      expect(promptSpy).not.toHaveBeenCalled();
      const req = http.expectOne((r) => r.method === 'POST');
      expect(req.request.body).toEqual({ status: 'confirmed', note: '' });
      req.flush({ code: 0, message: 'ok', data: {} });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
    });
  });

  describe('IP 信誉', () => {
    it('四个动作都按运营输入的**原文 IP** 走（列表只回掩码，行内无上下文）', async () => {
      const p = build(() => new Risk()) as unknown as P;
      p.tab.set('ip');
      expect(p.crud()).toBeNull(); // 没有行内动作 ⇒ 不出「操作」列
      promptSpy.mockReturnValue('1.2.3.4');
      const done = p.ipAct('block');
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/risk/ip/block');
      expect(req.request.body).toEqual({ ip: '1.2.3.4' });
      req.flush({
        code: 0,
        message: 'success',
        data: { ip_masked: '9f64a747****', source: 'internal_blacklist' },
      });
      await tick();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/ip/list')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
      // 回执里的掩码是服务端 hash 过的那个 IP（不是前端以为的那个）
      expect(p.note()).toContain('9f64a747****');
    });

    it('重查：POST /risk/ip/recheck（同一形状，服务端只删缓存）', async () => {
      const p = build(() => new Risk()) as unknown as P;
      promptSpy.mockReturnValue('5.6.7.8');
      const done = p.ipAct('recheck');
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/risk/ip/recheck');
      expect(req.request.body).toEqual({ ip: '5.6.7.8' });
      req.flush({
        code: 0,
        message: 'success',
        data: { message: '已刷新信誉缓存（外部检测服务未接入）' },
      });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
      expect(p.note()).toContain('已刷新信誉缓存');
    });

    it('IP 留空/取消 ⇒ 不发请求', async () => {
      const p = build(() => new Risk()) as unknown as P;
      p.tab.set('ip'); // 只用到 tab，不发请求（免得多一次 load 要 flush）
      promptSpy.mockReturnValue('  ');
      await p.ipAct('whitelist');
      await tick();
      http.expectNone(() => true);
    });
  });

  describe('设备', () => {
    const FP = 'a1b2c3d4'.repeat(8); // 64 位十六进制，与服务端 fpHash() 的形状一致
    const DEV: Row = {
      fp_hash: FP,
      fp_masked: 'a1b2c3d4****',
      ip_c_segment: '203.0.113',
      account_count: 3,
      blocked: false,
    };

    const loadDevices = async (p: P, list: Row[] = [DEV]): Promise<void> => {
      p.tab.set('devices');
      const done = (p as unknown as { load(): Promise<void> }).load();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/risk/device/list')
        .flush({ code: 0, message: 'ok', data: { list, total: list.length } });
      await done;
    };

    /**
     * blocked 决定出哪个按钮。when 是 DEVICE_ACTS 上的谓词、过滤发生在 Table 那一层，
     * 所以钉在渲染层（两行：未拉黑/已拉黑，各自只该有一个按钮）。
     */
    it('未拉黑的行只出「拉黑」，已拉黑的行只出「解封」', () => {
      const f = TestBed.createComponent(Table);
      f.componentRef.setInput('rows', [DEV, { ...DEV, blocked: true }]);
      f.componentRef.setInput('actions', DEVICE_ACTS);
      f.detectChanges();
      const rows = Array.from(
        f.nativeElement.querySelectorAll('tbody tr') as NodeListOf<HTMLTableRowElement>,
      );
      const labels = (i: number): (string | undefined)[] =>
        Array.from(rows[i]!.querySelectorAll('.acts button') as NodeListOf<HTMLButtonElement>).map(
          (b) => b.textContent?.trim(),
        );
      expect(labels(0)).toEqual(['拉黑']);
      expect(labels(1)).toEqual(['解封']);
    });

    it('拉黑：二次确认文案带 fp_masked；POST /risk/device/block 的请求体是完整哈希', async () => {
      confirmSpy = vi.spyOn(globalThis, 'confirm').mockReturnValue(true);
      const p = build(() => new Risk()) as unknown as P;
      await loadDevices(p);
      expect(p.actions().map((a) => a.key)).toEqual(['block', 'unblock']);
      const done = p.run(p.rows()[0]!, 'block');
      expect(String(confirmSpy.mock.calls[0]![0])).toContain('a1b2c3d4****');
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/risk/device/block');
      // 掩码提交必 400（服务端只认 /^[0-9a-f]{64}$/）⇒ 请求体必须是列表回传的完整 fp_hash
      expect(req.request.body).toEqual({ fp_hash: FP });
      req.flush({ code: 0, message: 'ok', data: { fp_masked: 'a1b2c3d4****' } });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
      expect(p.note()).toContain('已拉黑设备');
    });

    it('解封：POST /risk/device/unblock（同一形状），安全方向不弹确认框', async () => {
      confirmSpy = vi.spyOn(globalThis, 'confirm');
      const p = build(() => new Risk()) as unknown as P;
      await loadDevices(p, [{ ...DEV, blocked: true }]);
      const done = p.run(p.rows()[0]!, 'unblock');
      expect(confirmSpy).not.toHaveBeenCalled();
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/risk/device/unblock');
      expect(req.request.body).toEqual({ fp_hash: FP });
      // unblock 只回空 data ⇒ 回执的掩码退回行上那个
      req.flush({ code: 0, message: 'success', data: {} });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
      expect(p.note()).toContain('已解封设备 a1b2c3d4****');
    });

    it('二次确认里点取消 / 行上没有完整哈希 ⇒ 都不发请求（后者连确认框都不弹）', async () => {
      confirmSpy = vi.spyOn(globalThis, 'confirm').mockReturnValue(false);
      const p = build(() => new Risk()) as unknown as P;
      await loadDevices(p);
      await p.run(p.rows()[0]!, 'block');
      await tick();
      http.expectNone(() => true);

      // 只有掩码的行（旧列表形状）：请求体只能装完整哈希，拿掩码去请求必吃 400
      // ⇒ 守卫挡在 confirm **之前**：不弹框、也不发。这里刻意让 confirm 返回 true，
      // 否则「不发请求」是被取消掉的、不是因为守卫 —— 那样删掉守卫用例照样绿（实测逃逸过一次）
      confirmSpy.mockReturnValue(true);
      confirmSpy.mockClear(); // 上半场那次「点取消」的调用要清掉，否则这里的「没弹过」恒假
      const q = build(() => new Risk()) as unknown as P;
      await loadDevices(q, [{ fp_masked: 'a1b2c3d4****', blocked: false }]);
      await q.run(q.rows()[0]!, 'block');
      await tick();
      expect(confirmSpy).not.toHaveBeenCalled();
      http.expectNone(() => true);
    });
  });

  describe('没有行内写能力的模块', () => {
    it('总览：crud() === null ⇒ 一个动作都不出（只读汇总，没有对应端点）', () => {
      const p = build(() => new Risk()) as unknown as P;
      p.tab.set('overview');
      expect(p.crud()).toBeNull();
      expect(p.actions()).toEqual([]);
    });

    it('事件表头显式给列：created_at 这种关键列不被自动推导截掉', () => {
      const p = build(() => new Risk()) as unknown as P;
      p.tab.set('events');
      const heads = p.heads();
      expect(Object.keys(heads).length).toBe(9);
      expect(t(heads['created_at']!)).toBe('时间');
      expect(t(heads['rule_name']!)).toBe('规则');
    });
  });
});
