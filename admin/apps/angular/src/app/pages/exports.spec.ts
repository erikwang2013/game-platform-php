/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Api, Row } from '../core/api.service';
import { buildPdfTable } from '../core/export';
import { use } from '../core/i18n/i18n';
import { colsOf } from '../core/util';
import { Admins } from './admins';
import { Finance } from './finance';
import { Settings } from './settings';
import { Support } from './support';
import { Users } from './users';

/**
 * 「导出下载」这条链路：按钮 → Api.download → 浏览器落盘。
 *
 * 为什么 Api 层的用例和页面级的挤在一个文件里：**jsdom 没有 URL.createObjectURL**（实测 undefined），
 * 而 saveBlob 一定会调它 ⇒ 任何走到落盘的用例都得先把这两个 API 打上桩。桩只写一份，
 * 顺带把三个调用点（用户 / 提现收据 / 日报）钉在同一处，免得三份桩各自漂移。
 *
 * 这里钉的是**分流**：附件就落盘、信封就当错误抛。不做分流的话，「导出失败」会静默变成
 * 下载一个内容是错误 JSON 的 .xlsx —— 用户打开文件才发现。
 */
describe('导出下载', () => {
  let http: HttpTestingController;
  let api: Api;
  /** 落盘时 anchor 上的 download 属性（文件真名） */
  let saved: string | null;
  let createSpy: ReturnType<typeof vi.spyOn>;

  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));
  /** 请求 URL 去掉查询串，只比路径 */
  const path = (r: { url: string }): string => r.url.split('?')[0]!;

  beforeEach(() => {
    localStorage.clear();
    use('zh');
    // jsdom 缺这两个 API（不是本应用的问题）⇒ 先补上可写实现，再交给 spy 观察
    const u = URL as unknown as Record<string, unknown>;
    u['createObjectURL'] ??= (): string => 'blob:1';
    u['revokeObjectURL'] ??= (): void => {};
    createSpy = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:1');
    // jsdom 的 a.click() 会尝试导航（控制台刷 "Not implemented: navigation"）⇒ 只记下 download 属性
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (
      this: HTMLAnchorElement,
    ) {
      saved = this.download;
    });
    TestBed.configureTestingModule({
      // Auth 注入了 Router（signOut 要跳登录页）⇒ 少了这条 inject(Api) 就 NG0201
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
    api = TestBed.inject(Api);
    saved = null;
  });

  afterEach(() => {
    vi.restoreAllMocks();
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  /** 二进制附件响应（带 Content-Disposition，就像 response()->download 那样） */
  const attachment = { 'content-disposition': 'attachment; filename="export_users_20261001.xlsx"' };

  describe('Api.download 的分流', () => {
    it('附件 ⇒ 落盘，文件名取 Content-Disposition（不是自己拼的那个 fallback）', async () => {
      const done = api.download('POST', '/admin/v1/export/users');
      const req = http.expectOne((r) => path(r) === '/admin/v1/export/users');
      expect(req.request.method).toBe('POST');
      req.flush(new Blob(['xlsx-bytes']), {
        status: 200,
        statusText: 'OK',
        headers: { 'content-type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', ...attachment },
      });

      await expect(done).resolves.toBe('export_users_20261001.xlsx');
      expect(createSpy).toHaveBeenCalled();
      expect(saved).toBe('export_users_20261001.xlsx');
    });

    it('HTTP 200 + JSON = 信封式失败 ⇒ 抛错**不落盘**（本应用的业务失败一律 200）', async () => {
      const done = api.download('POST', '/admin/v1/export/users');
      http
        .expectOne((r) => path(r) === '/admin/v1/export/users')
        .flush(new Blob([JSON.stringify({ code: 403, message: '无导出权限', data: null })]), {
          status: 200,
          statusText: 'OK',
          headers: { 'content-type': 'application/json' },
        });

      await expect(done).rejects.toThrow('无导出权限');
      // 关键：错误 JSON 不能被存成 .xlsx
      expect(createSpy).not.toHaveBeenCalled();
      expect(saved).toBeNull();
    });

    it('HTTP 422 + 信封体 ⇒ 抛服务端那句（收据端点校验失败走的是这条路）', async () => {
      const done = api.download('POST', '/admin/v1/export/receipt');
      http
        .expectOne((r) => path(r) === '/admin/v1/export/receipt')
        .flush(new Blob([JSON.stringify({ code: 422, message: '订单不存在', data: null })]), {
          status: 422,
          statusText: 'Unprocessable Entity',
          headers: { 'content-type': 'application/json' },
        });

      await expect(done).rejects.toThrow('订单不存在');
      expect(createSpy).not.toHaveBeenCalled();
    });

    /**
     * /report/export 是 **GET**：body 传给 XHR 会被直接丢掉（fetch 后端更会抛
     * 「Request with GET/HEAD method cannot have body」）⇒ 参数必须进查询串，
     * 否则服务端按缺省值导一份别的日期范围，界面还显示成功。
     */
    it('GET 的参数进查询串，body 不发（/report/export 是 GET）', async () => {
      const done = api.download('GET', '/admin/v1/report/export', { format: 'xlsx' });
      const req = http.expectOne((r) => path(r) === '/admin/v1/report/export');
      expect(req.request.method).toBe('GET');
      expect(req.request.url).toBe('/admin/v1/report/export?format=xlsx');
      expect(req.request.body).toBeNull();
      req.flush(new Blob(['x']), {
        status: 200,
        statusText: 'OK',
        headers: { 'content-type': 'text/csv; charset=utf-8' },
      });
      // 拿不到 Content-Disposition 就退回调用方给的 fallback
      await expect(done).resolves.toBe('export');
    });

    it('401 ⇒ 刷新一次后重放（与 envelope 同一条路）', async () => {
      localStorage.setItem('ga_access_token', 'OLD');
      localStorage.setItem('ga_refresh_token', 'REFRESH');
      const done = api.download('POST', '/admin/v1/export/users');

      http
        .expectOne((r) => path(r) === '/admin/v1/export/users')
        .flush(new Blob([JSON.stringify({ code: 401, message: '登录状态已失效' })]), {
          status: 200,
          statusText: 'OK',
          headers: { 'content-type': 'application/json' },
        });

      await tick();
      // 刷新端点在**公开**组 /api/v1 下（不是 /admin/v1）
      const refresh = http.expectOne((r) => path(r) === '/api/v1/auth/refresh');
      refresh.flush({
        code: 0,
        message: 'ok',
        data: { access_token: 'NEW', refresh_token: 'REFRESH2' },
      });

      await tick();
      const replay = http.expectOne((r) => path(r) === '/admin/v1/export/users');
      expect(replay.request.headers.get('Authorization')).toBe('Bearer NEW');
      replay.flush(new Blob(['x']), {
        status: 200,
        statusText: 'OK',
        headers: { 'content-type': 'application/pdf', ...attachment },
      });

      await expect(done).resolves.toBe('export_users_20261001.xlsx');
    });
  });

  describe('页面接线', () => {
    const build = <T>(make: () => T): T => TestBed.runInInjectionContext(make);

    it('用户列表：导出按钮打的是 /export/users，且不带 status（端点不认搜索词）', async () => {
      const p = build(() => new Users()) as unknown as {
        exportUsers(): Promise<void>;
        exporting(): boolean;
        error(): string;
      };
      const done = p.exportUsers();
      expect(p.exporting()).toBe(true);

      const req = http.expectOne((r) => path(r) === '/admin/v1/export/users');
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toBeNull();
      req.flush(new Blob(['x']), {
        status: 200,
        statusText: 'OK',
        headers: { 'content-type': 'application/pdf', ...attachment },
      });
      await done;
      expect(p.exporting()).toBe(false);
      expect(p.error()).toBe('');
    });

    it('用户列表：导出失败把服务端原因放进 error()，不会静默下载一个坏文件', async () => {
      const p = build(() => new Users()) as unknown as {
        exportUsers(): Promise<void>;
        exporting(): boolean;
        error(): string;
      };
      const done = p.exportUsers();
      http
        .expectOne((r) => path(r) === '/admin/v1/export/users')
        .flush(new Blob([JSON.stringify({ code: 500, message: '导出队列已满' })]), {
          status: 200,
          statusText: 'OK',
          headers: { 'content-type': 'application/json' },
        });
      await done;
      expect(p.error()).toContain('导出队列已满');
      expect(p.exporting()).toBe(false);
      expect(createSpy).not.toHaveBeenCalled();
    });

    it('提现订单：收据是只读导出 —— 打 /export/receipt {type,order_id}，**不弹二次确认**', async () => {
      const confirmSpy = vi.spyOn(globalThis, 'confirm').mockReturnValue(true);
      const f = build(() => new Finance()) as unknown as {
        actions(): { key: string }[];
        extra(row: Row, key: string): Promise<void>;
        note(): string;
        noteErr(): boolean;
      };
      // 收据必须出现在动作行里，否则运营根本点不到
      expect(f.actions().map((a) => a.key)).toContain('receipt');

      const row: Row = { id: 'OHASH1', order_no: 'WD20260101' };
      const done = f.extra(row, 'receipt');
      const req = http.expectOne((r) => path(r) === '/admin/v1/export/receipt');
      expect(req.request.method).toBe('POST');
      // order_id 是 hashid（收据端点按它查单），type 只认 deposit|withdraw
      expect(req.request.body).toEqual({ type: 'withdraw', order_id: 'OHASH1' });
      // 只读文档：不该拦一道「确认执行打款」那种二次确认（那五个动作才需要）
      expect(confirmSpy).not.toHaveBeenCalled();
      req.flush(new Blob(['%PDF-1.4']), {
        status: 200,
        statusText: 'OK',
        headers: { 'content-type': 'application/pdf', 'content-disposition': 'attachment; filename="receipt_WD20260101.pdf"' },
      });
      await done;
      // 落盘即回执（浏览器自己有下载提示），横幅保持干净
      expect(f.note()).toBe('');
      expect(f.noteErr()).toBe(false);
      expect(saved).toBe('receipt_WD20260101.pdf');
    });

    it('提现订单：收据失败进横幅并带上订单号（运营要知道是哪一笔）', async () => {
      const f = build(() => new Finance()) as unknown as {
        extra(row: Row, key: string): Promise<void>;
        note(): string;
        noteErr(): boolean;
      };
      const done = f.extra({ id: 'OHASH1', order_no: 'WD20260101' }, 'receipt');
      http
        .expectOne((r) => path(r) === '/admin/v1/export/receipt')
        .flush(new Blob([JSON.stringify({ code: 422, message: '订单不存在' })]), {
          status: 200,
          statusText: 'OK',
          headers: { 'content-type': 'application/json' },
        });
      await done;
      expect(f.noteErr()).toBe(true);
      expect(f.note()).toContain('WD20260101');
      expect(f.note()).toContain('订单不存在');
    });

    it('报表页：导出日报打 GET /report/export 并显式带 format=xlsx', async () => {
      const s = build(() => new Support()) as unknown as {
        exportReport(): Promise<void>;
        exporting(): boolean;
        error(): string;
      };
      const done = s.exportReport();
      const req = http.expectOne((r) => path(r) === '/admin/v1/report/export');
      expect(req.request.method).toBe('GET');
      // 缺省值是 'excel' 而分支只认 'xlsx' ⇒ 不显式传就会拿到一个 .csv
      expect(req.request.url).toContain('format=xlsx');
      req.flush(new Blob(['x']), {
        status: 200,
        statusText: 'OK',
        headers: { 'content-type': 'application/pdf', ...attachment },
      });
      await done;
      expect(s.exporting()).toBe(false);
      expect(s.error()).toBe('');
    });

    it('报表页：日期范围超 90 天被后端拒（400 信封）时，原因落在 error() 上', async () => {
      const s = build(() => new Support()) as unknown as {
        exportReport(): Promise<void>;
        error(): string;
      };
      const done = s.exportReport();
      http
        .expectOne((r) => path(r) === '/admin/v1/report/export')
        .flush(
          new Blob([
            JSON.stringify({ code: 400, message: 'Date must be in Y-m-d format and the range must not exceed 90 days' }),
          ]),
          { status: 400, statusText: 'Bad Request', headers: { 'content-type': 'application/json' } },
        );
      await done;
      expect(s.error()).toContain('90 days');
    });
  });

  /**
   * 补的这三个端点（/export/pdf、/export/excel、/export/transactions）各自的坑都不一样：
   * PDF **不取数**（导的就是传进去的那几行，列还得与屏幕一致，否则看不见的字段会从这条路漏出去）；
   * excel **是整张服务端表**（不认屏幕上的筛选，表名错了服务端不报错、回一个空文件）；
   * transactions 只认可选的 type 而值域无处可列（多发一个参数就是假控件）。
   */
  describe('导出补口', () => {
    const build = <T>(make: () => T): T => TestBed.runInInjectionContext(make);

    /** 二进制附件响应；文件名走 Content-Disposition */
    const flushFile = (req: { flush: (b: Blob, o: object) => void }, name: string): void =>
      req.flush(new Blob(['x']), {
        status: 200,
        statusText: 'OK',
        headers: {
          'content-type': 'application/pdf',
          'content-disposition': `attachment; filename="${name}"`,
        },
      });

    it('PDF 载荷：列照屏幕那张表（heads 的键序 + 已查表的表头），行里多出来的字段一个都不进 PDF', () => {
      const heads = { order_no: 'withdraw.order_no', status: 'withdraw.status' };
      // 行的键序与 heads 相反，且多一个 id（hashid）—— 屏幕上不显示它，导出也不许带上
      const rows: Row[] = [{ status: 'pending', id: 'OHASH1', order_no: 'WD1' }];

      expect(buildPdfTable('提现订单', colsOf(heads, rows), rows)).toEqual({
        type: 'table',
        title: '提现订单',
        data: { columns: ['订单号', '状态'], rows: [['WD1', 'pending']] },
      });
    });

    it('PDF 单元格走 dash：空值 —、对象 {…}、数组逗号连接（对象直接 String 会印成 [object Object]）', () => {
      const heads = { a: 'withdraw.order_no', b: 'withdraw.status', c: 'withdraw.user' };
      const rows: Row[] = [{ a: null, b: { x: 1 }, c: ['U1', 'U2'] }];
      expect(buildPdfTable('T', colsOf(heads, rows), rows).data.rows).toEqual([['—', '{…}', 'U1,U2']]);
    });

    it('提现订单：导出的是**当前这一页**（列与行都照屏幕），落盘用服务端文件名', async () => {
      const f = build(() => new Finance()) as unknown as {
        heads(): Record<string, string>;
        busyExport(): boolean;
        error(): string;
        exportPdf(titleKey: string, heads: Record<string, string>, rows: Row[]): Promise<void>;
      };
      const rows: Row[] = [{ order_no: 'WD1', user_name: 'alice', id: 'OHASH1' }];
      const done = f.exportPdf('withdraw.orders', f.heads(), rows);
      expect(f.busyExport()).toBe(true);

      const req = http.expectOne((r) => path(r) === '/admin/v1/export/pdf');
      expect(req.request.method).toBe('POST');
      const body = req.request.body as { type: string; title: string; data: { columns: string[]; rows: string[][] } };
      expect(body.type).toBe('table');
      expect(body.title).toBe('提现订单');
      // 前三列 = 屏幕上那三列（顺序与译文都照表头）
      expect(body.data.columns.slice(0, 3)).toEqual(['订单号', '用户', '平台币']);
      // 一行一格不缺（缺的字段由 dash 补 —），hashid 不在这条路上
      expect(body.data.rows[0]!.length).toBe(body.data.columns.length);
      expect(body.data.rows[0]![0]).toBe('WD1');
      expect(JSON.stringify(body)).not.toContain('OHASH1');

      flushFile(req, 'export_pdf_20261001.pdf');
      await done;
      expect(saved).toBe('export_pdf_20261001.pdf');
      expect(f.busyExport()).toBe(false);
      expect(f.error()).toBe('');
    });

    it('PDF 导出失败（200 信封）⇒ 原因进 error()，不落盘', async () => {
      const f = build(() => new Finance()) as unknown as {
        heads(): Record<string, string>;
        busyExport(): boolean;
        error(): string;
        exportPdf(titleKey: string, heads: Record<string, string>, rows: Row[]): Promise<void>;
      };
      const done = f.exportPdf('withdraw.orders', f.heads(), []);
      http
        .expectOne((r) => path(r) === '/admin/v1/export/pdf')
        .flush(new Blob([JSON.stringify({ code: 403, message: '无导出权限' })]), {
          status: 200,
          statusText: 'OK',
          headers: { 'content-type': 'application/json' },
        });
      await done;
      expect(f.error()).toContain('无导出权限');
      expect(f.busyExport()).toBe(false);
      expect(createSpy).not.toHaveBeenCalled();
    });

    it('管理员列表：导出 Excel 打的是**整表**端点（表名 + 空列 + 空条件），并先弹二次确认说清范围', async () => {
      const confirmSpy = vi.spyOn(globalThis, 'confirm').mockReturnValue(true);
      const a = build(() => new Admins()) as unknown as {
        busyExport(): boolean;
        exportXlsx(table: string): Promise<void>;
      };
      const done = a.exportXlsx('admin_user');
      // 名词取当前模块的 crud().noun（与「新建管理员」同一个词）
      expect(confirmSpy.mock.calls[0]![0]).toContain('管理员');

      const req = http.expectOne((r) => path(r) === '/admin/v1/export/excel');
      expect(req.request.method).toBe('POST');
      // columns 空 = 用服务端自己的字段映射；conditions 空 = 屏幕上的搜索词不参与（端点也不认）
      expect(req.request.body).toEqual({ table: 'admin_user', columns: [], conditions: {} });
      flushFile(req, 'export_admin_user_20261001.xlsx');
      await done;
      expect(a.busyExport()).toBe(false);
    });

    it('取消二次确认 ⇒ 一个请求都不发（不会「取消了还照导」）', async () => {
      vi.spyOn(globalThis, 'confirm').mockReturnValue(false);
      const a = build(() => new Admins()) as unknown as {
        busyExport(): boolean;
        exportXlsx(table: string): Promise<void>;
      };
      await a.exportXlsx('admin_user');
      http.expectNone(() => true);
      expect(a.busyExport()).toBe(false);
    });

    it('系统配置 / 角色：同一个按钮按当前页签换表名（表名不在白名单 ⇒ 服务端回一个空文件且不报错）', async () => {
      vi.spyOn(globalThis, 'confirm').mockReturnValue(true);
      const s = build(() => new Settings()) as unknown as {
        tab: { set(v: string): void };
        exportTable(): Promise<void>;
      };
      let done = s.exportTable();
      const cfg = http.expectOne((r) => path(r) === '/admin/v1/export/excel');
      expect((cfg.request.body as { table: string }).table).toBe('system_config');
      flushFile(cfg, 'export_system_config_20261001.xlsx');
      await done;

      s.tab.set('role');
      done = s.exportTable();
      const role = http.expectOne((r) => path(r) === '/admin/v1/export/excel');
      expect((role.request.body as { table: string }).table).toBe('admin_role');
      flushFile(role, 'export_admin_role_20261001.xlsx');
      await done;
    });

    it('报表页：导出全部流水打 POST /export/transactions 且**不带任何参数**（type 没有值域可列）', async () => {
      const s = build(() => new Support()) as unknown as {
        exporting(): boolean;
        error(): string;
        exportTransactions(): Promise<void>;
      };
      const done = s.exportTransactions();
      expect(s.exporting()).toBe(true);
      const req = http.expectOne((r) => path(r) === '/admin/v1/export/transactions');
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toBeNull();
      flushFile(req, 'transactions_20261001.xlsx');
      await done;
      expect(s.exporting()).toBe(false);
      expect(s.error()).toBe('');
    });
  });
});
