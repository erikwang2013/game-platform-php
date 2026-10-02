/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { Api, Row } from '../core/api.service';
import { bounds, linePoints } from '../core/chart';
import { json, rowsAny, scalarsOf } from '../core/render';
import { errText, num } from '../core/util';
import { T, t } from '../core/i18n/i18n';
import { StatCard } from '../components/ui';
import { Table } from '../components/table';

const R = '/admin/v1/';

/**
 * 动作名 → 词条键。服务端 risk_log.action 是 log|warn|block 三值（RiskDashboardController::actionDistribution
 * 直接 group by 出来）；查不到就原样显示动作名 —— 后端将来加一个动作，这里显示英文原文而不是空白。
 */
const ACTION_KEYS: Record<string, string> = {
  log: 'risk.chart.act.log',
  warn: 'risk.chart.act.warn',
  block: 'risk.chart.act.block',
};

/**
 * /risk/overview 的 `total`（hits/blocked/warned/logged）→ 词条键。**复用图表那组**，不新造词：
 * 这四个词在图例与动作分布条里已经在用，另起一套只会让同屏出现两种说法。
 *
 * 为什么要单独拎出来：`total` 是个**嵌套对象**，`scalarsOf()` 只认标量 ⇒ 它一直没被渲染，
 * 于是「这一段共命中多少次、拦了多少」这两个全屏最该看到的数只能点开原始响应找。
 */
const TOTAL_KEYS: Record<string, string> = {
  hits: 'risk.chart.hits',
  blocked: 'risk.chart.blocked',
  warned: 'risk.chart.act.warn',
  logged: 'risk.chart.act.log',
};

/**
 * /risk/dashboard 的 24h 快照 → 词条键。前五个**复用**已在用的词（命中/阻断/告警/仅记录/阻断率），
 * 只有后四个是新词：规则规模、黑名单 IP 数、设备簇数在总览的其它任何地方都没有出现过。
 *
 * 为什么单拎出来：这个端点是 `/risk/overview` 的**近 24 小时**切片（overview 默认 7 天），
 * 运营打开风控页第一个要看的数就在这儿，而它此前压根没被调用。
 */
const DASH_KEYS: Record<string, string> = {
  total_events_24h: 'risk.chart.hits',
  blocked_24h: 'risk.chart.blocked',
  warned_24h: 'risk.chart.act.warn',
  logged_24h: 'risk.chart.act.log',
  block_rate_24h: 'risk.head.block_rate',
  enabled_rules: 'risk.dash.enabled_rules',
  total_rules: 'risk.dash.total_rules',
  blacklist_ips: 'risk.dash.blacklist_ips',
  device_clusters: 'risk.dash.device_clusters',
};

/** 唯一一个百分比量：`BcMath::percent` 回的是 float（12.34），不补 `%` 会被读成"12 次" */
const DASH_PCT = 'block_rate_24h';

/** 24h 最近事件表的列（detail 已在服务端截到 100 字符） */
const DASH_HEADS: Record<string, string> = {
  user_id: 'risk.head.user',
  type: 'risk.head.type',
  action: 'risk.head.action',
  detail: 'risk.head.detail',
  created_at: 'risk.head.time',
};

/** 规则效果表的列（值 = 词条键，ui-table 会过 `t()`） */
const PERF_HEADS: Record<string, string> = {
  name: 'risk.head.rule_name',
  action: 'risk.head.action',
  hits: 'risk.head.hits',
  block_rate: 'risk.head.block_rate',
  manual_review_rate: 'risk.head.manual_review_rate',
};

/**
 * 风控总览的整块呈现：指标卡 + 原始响应，加上四个**此前一直没被调用**的只读端点
 * （/risk/action-distribution、/risk/rule-performance、/risk/dashboard、/risk/hit-trend），
 * 把 /risk/overview 已经拿到的 series 画成趋势线。
 *
 * 为什么单独一个组件：risk.ts 已经 497 行，塞进去就破 500 行的线。这里只做**呈现** —— 总览数据由
 * 父组件传入（它已经拉过 /risk/overview，不再重复请求同一个端点），自己只拉另外那两个。
 *
 * 已知取舍（不在客户端补）：服务端按 bucket 分组，**没有数据的 bucket 根本不在 series 里**
 * ⇒ 7 天里空掉 3 天会画成 4 个点连成的一条线，横轴也不是等时间距（全树的折线图都这样）。
 * 这是「形状指示器」不是精确时间轴，要精确值点开下面的原始响应。
 */
@Component({
  selector: 'app-risk-charts',
  imports: [StatCard, Table, T],
  template: `
    @if (totals().length) {
      <div class="tiles">
        @for (x of totals(); track x.label) {
          <ui-stat [label]="x.label | t" [value]="x.value" />
        }
      </div>
    }

    @if (dashTiles().length) {
      <div class="card">
        <div class="card-head">{{ 'risk.dash.24h' | t }}</div>
        <div class="card-body">
          <div class="tiles">
            @for (x of dashTiles(); track x.label) {
              <ui-stat [label]="x.label | t" [value]="x.value" />
            }
          </div>
        </div>
      </div>
    }

    @if (recent().length) {
      <div class="card">
        <div class="card-head">{{ 'risk.dash.recent' | t }}</div>
        <div class="card-body">
          <ui-table [rows]="recent()" [heads]="dashHeads" />
        </div>
      </div>
    }

    @if (trend().length) {
      <div class="card">
        <div class="card-head">{{ 'risk.trend_by_type' | t }}</div>
        <div class="card-body">
          @for (s of trend(); track s.type) {
            <div class="spark-row">
              <span class="name" [title]="s.type">{{ s.type }}</span>
              <!-- 每条一行迷你折线，**共用同一个纵轴范围**（trend() 里算好）：
                   各自归一的话，命中 3 次与 3000 次的规则会长得一模一样 -->
              <div class="chart">
                <svg viewBox="0 0 100 100" preserveAspectRatio="none">
                  <polyline class="line s1" [attr.points]="s.points" />
                </svg>
              </div>
              <span class="val">{{ s.total }}</span>
            </div>
          }
        </div>
      </div>
    }

    @if (scalars().length) {
      <div class="tiles">
        @for (s of scalars(); track s.k) {
          <ui-stat [label]="s.k" [value]="s.v" />
        }
      </div>
    } @else {
      <div class="state">{{ 'risk.overview_empty' | t }}</div>
    }

    @if (hits().length > 1) {
      <div class="card">
        <div class="card-head">{{ 'risk.trend' | t }}</div>
        <div class="card-body">
          <div class="chart">
            <svg viewBox="0 0 100 100" preserveAspectRatio="none">
              <polyline class="grid" points="0,99 100,99" />
              <polyline class="line s1" [attr.points]="hitLine()" />
              <polyline class="line s2" [attr.points]="blockLine()" />
            </svg>
            <div class="axis">
              <span>{{ first() }}</span
              ><span>{{ last() }}</span>
            </div>
          </div>
          <div class="chart-legend">
            <span><i class="s1"></i>{{ 'risk.chart.hits' | t }}</span>
            <span><i class="s2"></i>{{ 'risk.chart.blocked' | t }}</span>
          </div>
        </div>
      </div>
    }

    @if (actions().length) {
      <div class="card">
        <div class="card-head">{{ 'risk.actions' | t }}</div>
        <div class="card-body">
          @for (a of actions(); track a['action']) {
            <div class="bar-row">
              <span>{{ actionText(a['action']) }}</span>
              <!-- 条宽直接用服务端算好的 ratio（占命中总数的百分比），不按最大值归一
                   —— 分布图要读的是「占比」，按 max 归一会把 66%/33% 画成 100%/50% -->
              <div class="track"><div class="fill" [style.width.%]="num(a['ratio'])"></div></div>
              <span class="val">{{ num(a['count']) }} / {{ num(a['ratio']) }}%</span>
            </div>
          }
        </div>
      </div>
    }

    @if (perf().length) {
      <div class="card">
        <div class="card-head">{{ 'risk.rule_perf' | t }}</div>
        <div class="card-body">
          <ui-table [rows]="perf()" [heads]="perfHeads" />
        </div>
      </div>
    }

    @if (error()) {
      <div class="state error">{{ error() }}</div>
    }

    @if (raw(); as d) {
      <details class="raw-box">
        <summary>{{ 'app.raw_response' | t }}</summary>
        <pre class="raw">{{ pretty(d) }}</pre>
      </details>
    }
  `,
})
export class RiskCharts {
  private readonly api = inject(Api);

  /** /risk/overview 的原始响应，由父组件传入。**每次父组件刷新都是新对象** ⇒ 下面那个 effect 会重跑 */
  readonly raw = input<unknown>(null);

  protected readonly actions = signal<Row[]>([]);
  /** 规则效果表：行已摊平成「直接可显示」的形状（比率在这里就配成 % 文本，表格只负责画） */
  protected readonly perf = signal<Row[]>([]);
  /** /risk/dashboard 的原始响应（近 24h 快照），下面三块呈现都从它派生 */
  protected readonly dash = signal<Row | null>(null);
  /** /risk/hit-trend 的原始响应：series 是**按规则类型分组的对象**（不是数组） */
  protected readonly trendRaw = signal<Row | null>(null);
  protected readonly error = signal('');

  protected readonly perfHeads = PERF_HEADS;
  protected readonly dashHeads = DASH_HEADS;
  protected readonly num = num;
  protected readonly pretty = json;
  protected readonly scalars = computed(() => scalarsOf(this.raw()));

  /** 24h 指标卡：键表里没登记的一律不显示（后端将来加个字段，这里不会冒出个裸字段名） */
  protected readonly dashTiles = computed(() => {
    const d = this.dash();
    if (!d) return [];
    return Object.entries(DASH_KEYS)
      .filter(([k]) => d[k] !== undefined && d[k] !== null)
      .map(([k, key]) => ({
        label: key,
        value: k === DASH_PCT ? `${num(d[k])}%` : num(d[k]),
      }));
  });

  /**
   * 最近事件的行：`action` 走与动作分布条**同一张词表**（ACTION_KEYS）—— 同屏两张卡片
   * 一个显示「拦截」一个显示 `block`，读的人会以为是两种东西（服务端回的就是枚举原文）。
   */
  protected readonly recent = computed(() =>
    rowsAny(this.dash(), 'recent_events').map((r) => ({
      ...r,
      action: this.actionText(r['action']),
    })),
  );

  /**
   * 按规则类型的趋势：服务端回的是 `{ <ruleType>: [{bucket, hits}] }` **对象**（对象键序不保证），
   * 所以先取值、算一次全局纵轴范围、再按总命中降序排 —— 命中最多的规则排在最上面。
   * 只命中过一天的规则照旧成行（`linePoints` 少于两个点回空串 ⇒ 不画线，数字仍在），
   * 直接过滤掉会让「这条规则这段时间就命中过一次」从界面上消失。
   */
  protected readonly trend = computed(() => {
    const s = this.trendRaw()?.['series'];
    if (!s || typeof s !== 'object' || Array.isArray(s)) return [];
    const entries = Object.entries(s as Record<string, unknown>);
    const values = entries.map(([, pts]) =>
      Array.isArray(pts) ? (pts as Row[]).map((p) => num(p['hits'])) : [],
    );
    const [lo, hi] = bounds(values);
    return entries
      .map(([type, pts], i) => ({
        type,
        points: linePoints(values[i]!, lo, hi),
        total: values[i]!.reduce((a, b) => a + b, 0),
      }))
      .sort((a, b) => b.total - a.total);
  });
  protected readonly totals = computed(() => {
    const total = (this.raw() as Row | null)?.['total'];
    if (!total || typeof total !== 'object' || Array.isArray(total)) return [];
    return Object.entries(total as Row)
      .filter(([k]) => TOTAL_KEYS[k])
      .map(([k, v]) => ({ label: TOTAL_KEYS[k]!, value: num(v) }));
  });

  private readonly series = computed(() => {
    const s = (this.raw() as Row | null)?.['series'];
    return Array.isArray(s) ? (s as Row[]) : [];
  });
  protected readonly hits = computed(() => this.series().map((r) => num(r['hits'])));
  private readonly blocked = computed(() => this.series().map((r) => num(r['blocked'])));
  /**
   * 两条线共用一套纵轴范围：各自归一的话，命中 3 次与阻断 3000 次会画成一样高的两条线，
   * 一眼看不出「阻断率其实很低」。
   */
  private readonly scale = computed(() => bounds([this.hits(), this.blocked()]));
  protected readonly hitLine = computed(() => linePoints(this.hits(), ...this.scale()));
  protected readonly blockLine = computed(() => linePoints(this.blocked(), ...this.scale()));
  protected readonly first = computed(() => String(this.series()[0]?.['bucket'] ?? ''));
  protected readonly last = computed(() => {
    const s = this.series();
    return String(s[s.length - 1]?.['bucket'] ?? '');
  });

  /**
   * 补块跟着总览数据走：父组件每刷新一次 /risk/overview 就换一个新对象，这里就跟一次。
   * 只认非空（切到别的标签页时父组件把 raw 置回 null，那一下不请求）。
   * effect 在创建后至少跑一次 ⇒ 首次挂载也会拉，不需要构造函数里再补一次。
   *
   * `untracked`：Api 造请求头时会**同步**读语言信号（`api.service.ts` 的 `X-Language`）⇒
   * 不挡住的话「切一次语言」= 重拉这两个端点（真机实测：切 ja / 切回 zh 各多打一对请求）。
   * 本 effect 只该认 `raw()` 一个入参。
   */
  private readonly follow = effect(() => {
    if (this.raw()) untracked(() => void this.reload());
  });

  private async reload(): Promise<void> {
    this.error.set('');
    try {
      const [dist, rule, dash, trend] = await Promise.all([
        this.api.get<Row>(R + 'risk/action-distribution'),
        this.api.get<Row>(R + 'risk/rule-performance'),
        this.api.get<Row>(R + 'risk/dashboard'),
        this.api.get<Row>(R + 'risk/hit-trend'),
      ]);
      const items = dist['items'];
      this.actions.set(Array.isArray(items) ? (items as Row[]) : []);
      this.perf.set(this.perfRows(rule['items']));
      this.dash.set(dash);
      this.trendRaw.set(trend);
    } catch (e) {
      // 补块拉不到只影响这几张卡片：就地报，不把父组件的总览/列表打成错误态
      this.error.set(errText(e));
    }
  }

  /**
   * 规则效果的行：只留要显示的列（后端还给 id/type/priority/status，列多了这张表就成流水账）。
   *
   * `hits === 0` 时比率显示 `—` 而不是 `0%`：后端零命中回的就是 0（`$hits > 0 ? percent : 0`），
   * 而「一次都没命中」与「命中了但一次没拦」是两回事，画成 0% 会被读成「这条规则不拦人」。
   */
  private perfRows(items: unknown): Row[] {
    if (!Array.isArray(items)) return [];
    return (items as Row[]).map((r) => {
      const hits = num(r['hits']);
      const pct = (v: unknown): string => (hits > 0 ? `${num(v)}%` : '—');
      return {
        name: String(r['name'] ?? ''),
        action: this.actionText(r['action']),
        hits,
        block_rate: pct(r['block_rate']),
        manual_review_rate: pct(r['manual_review_rate']),
      };
    });
  }

  /** 模板里要用 ⇒ 不能是 private（Angular 模板作用域只认 public/protected 成员） */
  protected actionText(v: unknown): string {
    const a = String(v ?? '');
    const key = ACTION_KEYS[a];
    return key ? t(key) : a;
  }
}
