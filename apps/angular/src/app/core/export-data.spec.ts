/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ExportData } from './api.service';
import { exportBlob, exportCounts, exportName, saveBlob } from './export-data';

/**
 * 「导出我的数据」落盘半截的钉子。三条语义与 react 树 `lib/exportData.ts` 同源
 * （同一端点两个客户端，判据不许漂移）：
 * ① 文件名只认服务端 `exported_at`，取不出数字回 `unknown` —— **绝不退回本机时钟**；
 * ② 序列化缩进两格（压缩成一行的 JSON 等于没导出）；
 * ③ 四类计数各自报，不相加。
 * 外加 `saveBlob` 的 DOM 半截：react 树无 DOM 底座测不到这段，本树在 jsdom 里补上。
 *
 * ⚠ 固定装置里的日期是**故意的过去时刻**（`2025-03-04`），不是今天：
 * 否则「退回本机时钟」的实现会产出同样的文件名，用例恒绿、咬不住任何东西。
 */
const EXPORTED_AT = '2025-03-04 10:00:00';

const data = (over: Partial<ExportData> = {}): ExportData => ({
  profile: {
    username: 'bob',
    nickname: null,
    email: null,
    phone: null,
    country: null,
    language: null,
    created_at: null,
  },
  wallet: null,
  transactions: [],
  exchange_records: [],
  deposit_orders: [],
  withdraw_orders: [],
  oauth_accounts: [],
  exported_at: EXPORTED_AT,
  ...over,
});

/** 本机今天（`YYYYMMDD`）—— 用来证明文件名里没有本机时刻的成分 */
const todayDigits = (): string => {
  const d = new Date();
  const p = (n: number): string => String(n).padStart(2, '0');
  return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}`;
};

const realCreate = URL.createObjectURL;
const realRevoke = URL.revokeObjectURL;

/** jsdom 不实现 Blob URL（`createObjectURL` 可能整个缺席）⇒ 直接接管两个全局，用例自己记账 */
const interceptBlobUrls = (): { created: string[]; revoked: string[] } => {
  const created: string[] = [];
  const revoked: string[] = [];
  URL.createObjectURL = ((_b: Blob) => {
    const u = `blob:test-${created.length}`;
    created.push(u);
    return u;
  }) as typeof URL.createObjectURL;
  URL.revokeObjectURL = ((u: string) => {
    revoked.push(String(u));
  }) as typeof URL.revokeObjectURL;
  return { created, revoked };
};

afterEach(() => {
  URL.createObjectURL = realCreate;
  URL.revokeObjectURL = realRevoke;
  vi.useRealTimers();
  vi.restoreAllMocks();
});

describe('exportName', () => {
  it('只用服务端 exported_at 的数字，一个本机时刻都不掺', () => {
    expect(exportName(EXPORTED_AT)).toBe('game-platform-export-20250304100000.json');
    // 阳性对照：实现若退回本机时钟，文件名里会出现今天的 8 位日期 ⇒ 这行变红
    expect(exportName(EXPORTED_AT)).not.toContain(todayDigits());
  });

  it('字段缺失/格式认不出时回 unknown，不退回本机时间', () => {
    expect(exportName('')).toBe('game-platform-export-unknown.json');
    expect(exportName(undefined as unknown as string)).toBe('game-platform-export-unknown.json');
    // 「unknown」里没有任何 8 位日期成分 —— 退回本机时钟会立刻违反这条
    expect(exportName('')).not.toMatch(/\d{8}/);
  });
});

describe('exportBlob', () => {
  it('是缩进两格的 JSON，且内容原样可解析回来', async () => {
    const payload = data({ transactions: [{ id: 'T1', amount: '1.00' }] });
    const b = exportBlob(payload);

    expect(b.type).toBe('application/json');
    const text = await b.text();
    // 缩进两格：压成一行就没有可读性可言了
    expect(text).toContain('\n  "profile"');
    expect(text).toContain('\n  "exported_at": "2025-03-04 10:00:00"');
    // 原样落盘：不该在序列化这一层丢字段或改值
    expect(JSON.parse(text)).toEqual(payload);
  });
});

describe('exportCounts', () => {
  it('四类明细各自计数，不相加成一个数', () => {
    const s = exportCounts(
      data({
        transactions: [{ id: 1 }, { id: 2 }],
        exchange_records: [{ id: 1 }],
        deposit_orders: [{ id: 1 }, { id: 2 }, { id: 3 }],
        withdraw_orders: [{ id: 1 }, { id: 2 }, { id: 3 }, { id: 4 }],
      }),
    );
    expect(s).toBe('流水 2 · 兑换 1 · 充值 3 · 提现 4');
    // 四类相加是 10：出现「共 10」即说明被合并成一个数了
    expect(s).not.toContain('10');
    expect(s).not.toContain('共');
  });
});

describe('saveBlob', () => {
  it('用隐藏的 <a download> 落盘，点完即摘除，且延迟一轮才 revoke', () => {
    vi.useFakeTimers();
    const { created, revoked } = interceptBlobUrls();

    // 记下 click 发生的那一刻锚点是否已在文档里（先 append 再 click 才是有效点击）
    const attachedAtClick: boolean[] = [];
    const downloads: string[] = [];
    const click = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(function (this: HTMLAnchorElement) {
        attachedAtClick.push(document.body.contains(this));
        downloads.push(this.getAttribute('download') ?? '');
      });

    const name = 'game-platform-export-20250304100000.json';
    saveBlob(new Blob(['{}'], { type: 'application/json' }), name);

    expect(created).toEqual(['blob:test-0']);
    expect(click).toHaveBeenCalledTimes(1);
    expect(downloads).toEqual([name]);
    expect(attachedAtClick).toEqual([true]);
    // 点完就摘：不在页面里留悬空节点（隐藏了也一样）
    expect(document.querySelector('a[download]')).toBeNull();
    // revoke 不能和 click 同一轮同步发生 —— 立刻 revoke 会让部分浏览器取消下载
    expect(revoked).toEqual([]);
    vi.runAllTimers();
    expect(revoked).toEqual(['blob:test-0']);
  });
});
