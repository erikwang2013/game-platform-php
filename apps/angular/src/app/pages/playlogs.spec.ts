/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { PlayLog, PlayLogDetail, money } from '../core/api.service';
import { PlaylogsPage } from './playlogs';

type Probe = {
  sign(v: number | string): string;
  abs(v: number | string): string;
  loadAssets(): void;
  games(): Array<{ game_id: string; name: string; currencies: Array<{ balance: string }> }>;
  cur: { (): PlayLogDetail | null; set(v: PlayLogDetail | null): void };
  detailLoading: { (): boolean; set(v: boolean): void };
  detailErr: { (): string; set(v: string): void };
  open(l: PlayLog): void;
  close(): void;
  load(p: number): void;
};

/**
 * 负号**字符**的钉子。
 *
 * 钉它的理由：`sign()` 此前回的是排版减号 `−`(U+2212)，而钱包流水那一屏走的是
 * `money()` —— 它只归一、不产符号，透传的是后端 bcmath 串里本来就有的 ASCII
 * `-`(U+002D)。于是同一个应用里「−12.34」（游戏流水页）与「-12.34」（钱包页）
 * 是**两个不同的字符**：肉眼在等宽字体下几乎分不出来，真机脚本也不会因此报错，
 * 而字体缺 U+2212 字形时还会掉成豆腐块。现在统一成 ASCII `-`。
 *
 * 断言读的是**码点**而不是把字符写进期望值：`toBe('-')` 在复制粘贴与查找替换里
 * 会被无声带偏，`codePointAt` 不会 —— 这也正是当初它能藏到现在的原因。
 *
 * 特意**不渲染整页**（那要造一堆服务桩，钉子会退化成测桩）：只钉这两个纯函数，
 * 它们正是模板表达式 `{{ sign(v) }}{{ money(abs(v)) }}` 的全部零件。
 * 渲染那一层由 /tmp 的真机脚本管，两层分工见交付说明。
 */
describe('PlaylogsPage 负号字符', () => {
  let http: HttpTestingController;
  let page: PlaylogsPage;

  const probe = (): Probe => page as unknown as Probe;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', children: [] }]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    // inject(Api) 写在字段初始化器里 ⇒ 必须在注入上下文里构造
    page = TestBed.runInInjectionContext(() => new PlaylogsPage());
    // 构造函数里 loadAssets() + load(1) 各发一发，先冲掉，后续用例各管各的
    http
      .expectOne((r) => r.url === '/api/v1/game/balance')
      .flush({ code: 0, message: 'ok', data: { games: [] } });
    http
      .expectOne((r) => r.url === '/api/v1/game/play-logs')
      .flush({ code: 0, message: 'ok', data: { items: [], page: 1, last_page: 1, total: 0 } });
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('负号是 ASCII U+002D，且与 money() 产出的符号同码点', () => {
    // 与钱包页同字符：那边渲染的是 money(v) 的结果，这边是 sign(v) + money(abs(v))
    expect(probe().sign('-12.34').codePointAt(0)).toBe(0x2d);
    expect(money('-12.34').codePointAt(0)).toBe(0x2d);
    expect(probe().sign('-12.34').codePointAt(0)).toBe(money('-12.34').codePointAt(0));

    // 模板里真正拼出来的那个串：一个 ASCII 负号 + 千分位，整个串都是 ASCII
    const rendered = probe().sign('-1234.5678') + money(probe().abs('-1234.5678'));
    expect(rendered).toBe('-1,234.5678');
    expect([...rendered].every((c) => c.codePointAt(0)! < 0x80)).toBe(true);
  });

  it('正数与空值仍是加号（这一支的语义与改前一致，未被顺手改掉）', () => {
    expect(probe().sign('12.34')).toBe('+');
    expect(probe().sign('0.00')).toBe('+');
    // 现状：空值走 `v ?? ''` ⇒ 不以 '-' 开头 ⇒ 回 '+'（本次刻意不改，只锁行为）
    expect(probe().sign('')).toBe('+');
    expect(probe().sign(null as unknown as string)).toBe('+');
  });

  /**
   * 游戏币余额（本页上半屏）。此前本页只有流水，`/game/balance` 在本树**零消费方** ——
   * 用户看得到每一次游戏币变动，却看不到现在还剩多少。这条钉的是「余额真的被读进来了」。
   */
  it('游戏资产读 /api/v1/game/balance，games 落进信号', () => {
    probe().loadAssets();
    const req = http.expectOne((r) => r.url === '/api/v1/game/balance');
    req.flush({
      code: 0,
      message: 'ok',
      data: {
        games: [
          {
            game_id: 'G1',
            name: '幸运骰子',
            slug: 'dice',
            type: 'slot',
            currencies: [
              { currency_id: 'C1', name: '金币', symbol: '◈', balance: '12.50000000', frozen_balance: '0.00000000' },
            ],
          },
        ],
      },
    });
    const games = probe().games();
    expect(games.length).toBe(1);
    expect(games[0]!.name).toBe('幸运骰子');
    expect(games[0]!.currencies[0]!.balance).toBe('12.50000000');
  });

  it('余额接口挂了不影响流水：两边各自报错，games 保持空数组', () => {
    probe().loadAssets();
    http
      .expectOne((r) => r.url === '/api/v1/game/balance')
      .flush({ code: 500, message: 'boom' }, { status: 500, statusText: 'Server Error' });
    expect(probe().games()).toEqual([]);
  });

  /**
   * 弹框的竞态（**能红**：删掉 `open()` 里那两行 `if (this.loadingId !== l.id) return;` 即变红）。
   *
   * 现场：点开一条流水 → 框里「加载中…」→ 用户点遮罩关掉 → 回包这才落地 ⇒ 框自己又弹回来。
   * 本页弹框的显示条件是 `cur() || detailErr() || detailLoading()`，三个信号里 `cur` 归回包写，
   * 所以判据必须挂在**回包落地那一刻**，而不是挂在 `close()` 上（`close()` 本来就清干净了）。
   */
  it('关框后落地的详情回包不再把框弹回来（在途请求按代号作废）', () => {
    const log = { id: 'L1' } as PlayLog;
    probe().open(log);
    probe().close(); // 用户没等回包就关了
    http.expectOne('/api/v1/game/play-log/L1').flush({
      code: 0,
      message: 'ok',
      data: { ...log, game_amount_change: '-12.34000000', game_amount_after: '0.00000000' },
    });

    expect(probe().cur()).toBeNull(); // 不挂判据时这里会被写回 ⇒ 框弹回来
    expect(probe().detailErr()).toBe('');
    expect(probe().detailLoading()).toBe(false);
  });

  /**
   * 渲染层的钉子（本页唯一渲染整页的用例）：**键在渲染期真的解析成了中文**。
   *
   * 为什么非有不可：静态那条键引用钉子（`source-nails.spec.ts` ②）只证明「源码里写到的键在表里」，
   * 而 `t()` 对认不出的键、以及**忘写 `| t`** 的写法都是**原样吐键名** —— 页面上会大摇大摆地印着
   * `mygames.action_start`，键集/表那几条钉子却全是绿的。动作列走的是 `actionLabel` 那张
   * **数据表**（键不在模板字面量里），正是静态钉子覆盖不到的那一路。
   *
   * 只断言「一个键名都没漏出来」+ 几个具体中文串：不钉 DOM 结构，样式层改动不会打红它。
   * 切语言那一半（`| t` 是否 `pure:false`）由 `i18n.spec.ts` 的管道用例管，这里不重复。
   */
  it('渲染出的文本里没有漏出的键名（数据表路的键也在渲染期解析）', () => {
    const fixture = TestBed.createComponent(PlaylogsPage);
    const cmp = fixture.componentInstance as unknown as Probe;
    // 构造器里 loadAssets() + load(1)：余额空、流水一条 `start`
    http
      .expectOne((r) => r.url === '/api/v1/game/balance')
      .flush({ code: 0, message: 'ok', data: { games: [] } });
    const log = {
      id: 'L1',
      action: 'start',
      created_at: '2026-10-01 12:00:00',
      session_id: 'S1',
      game_amount_change: '-12.34000000',
    } as PlayLog;
    http
      .expectOne((r) => r.url === '/api/v1/game/play-logs')
      .flush({ code: 0, message: 'ok', data: { items: [log], page: 1, last_page: 1, total: 1 } });
    fixture.detectChanges();

    let text = fixture.nativeElement.textContent as string;
    expect(text).toContain('游戏资产');
    expect(text).toContain('刷新');
    expect(text).toContain('开局'); // actionLabel('start') → mygames.action_start
    expect(text).not.toMatch(/\b(?:mygames|common|wallet|tourney)\./);

    // 详情框：`mygames.col_*` 那一组只在框里出现，得开一次框才看得到
    cmp.open(log);
    http.expectOne('/api/v1/game/play-log/L1').flush({
      code: 0,
      message: 'ok',
      data: { ...log, game_amount_before: '-1.00000000', game_amount_after: '-13.34000000' },
    });
    fixture.detectChanges();

    text = fixture.nativeElement.textContent as string;
    for (const label of ['动作', '时间', '会话', '变动前', '变动', '变动后', '平台币变动', '流水详情']) {
      expect(text).toContain(label);
    }
    expect(text).not.toMatch(/\b(?:mygames|common|wallet|tourney)\./);
  });
});
