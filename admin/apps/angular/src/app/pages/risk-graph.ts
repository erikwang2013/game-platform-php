/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, input } from '@angular/core';
import { Row } from '../core/api.service';
import { T, t } from '../core/i18n/i18n';
import { rowsAny } from '../core/render';
import { num } from '../core/util';
import { StatCard } from '../components/ui';
import { Table } from '../components/table';

/**
 * RiskGraphController::graph 对「用户行已被删」的哨兵值（`$user ? (int) $user->status : -1`）：
 * 节点的 status 只有 0/1 是平台用户状态，-1 不是状态而是「查无此人」。
 */
const GONE = -1;

/** risk_verdict 是服务端枚举（RiskGraphController::CLUSTER_THRESHOLD 比较出来的），查不到就原样显示 */
const VERDICTS: Record<string, string> = {
  suspicious: 'risk.graph.suspicious',
  normal: 'risk.graph.normal',
};

/** 节点/边表的列（值 = 词条键，ui-table 会过 `t()`） */
const NODE_HEADS: Record<string, string> = {
  username: 'risk.head.username',
  status_label: 'risk.head.status',
  root: 'risk.graph.is_root',
};

const EDGE_HEADS: Record<string, string> = {
  from_name: 'risk.head.from',
  to_name: 'risk.head.to',
  type: 'risk.head.type',
};

/**
 * 两个**只读**图谱端点（`GET /risk/graph/{hashid}` 与 `GET /risk/graph/clusters`）的呈现。
 * 单独一个组件：risk.ts 已贴着 500 行，且这里只需要 raw 一个入口（与 risk-charts.ts 同一个理由）。
 *
 * 已知取舍（**没画连线图**）：graph 回的 nodes/edges 足够画一个力导向图，但两跳闭包上限 50 个节点，
 * 摆上去就是一团糊 —— 而且没有布局算法时「谁在中心」这件唯一要看的事反而丢了。所以摆两张表：
 * 节点表（含「起始账号」标记）+ 边表（两端回填成用户名），配上簇大小/跳数/判定三个数。
 * ponytail: 要连线图就上 d3-force（本仓没装，也是个新依赖）；表读不出形状时再说。
 *
 * ⚠ 边**画不满整张图**：服务端只把「根用户的设备」上的账号两两连边，二跳账号是靠一跳账号的**别的**
 * 设备扯进来的，那些设备不在边集里 ⇒ 存在没有任何边的二跳节点。这是端点本身的形状，不是漏渲染，
 * 所以**不按边推导「第几跳」**（推出来会把这类节点标成「无关联」，与事实相反）。跳数只有全局那个数。
 */
@Component({
  selector: 'app-risk-graph',
  imports: [StatCard, Table, T],
  template: `
    @if (mode() === 'graph') {
      @if (tiles().length) {
        <div class="tiles">
          @for (x of tiles(); track x.label) {
            <ui-stat [label]="x.label | t" [value]="x.value" />
          }
        </div>
      }

      @if (nodes().length) {
        <div class="card">
          <div class="card-head">{{ 'risk.graph.nodes' | t }}</div>
          <div class="card-body">
            <ui-table [rows]="nodes()" [heads]="nodeHeads" />
          </div>
        </div>
      }

      @if (edges().length) {
        <div class="card">
          <div class="card-head">{{ 'risk.graph.edges' | t }}</div>
          <div class="card-body">
            <ui-table [rows]="edges()" [heads]="edgeHeads" />
          </div>
        </div>
      }

      @if (raw() && !nodes().length) {
        <div class="state">{{ 'risk.graph.empty' | t }}</div>
      }
    } @else {
      @for (c of clusters(); track c.key) {
        <div class="card">
          <div class="card-head">{{ c.key }} · {{ c.total }} {{ 'risk.head.accounts_count' | t }}</div>
          <div class="card-body">
            @for (m of c.members; track m.id) {
              <span class="tag">{{ m.username }}</span>
            }
            <!-- 成员只取前 20 个，account_count 是全量：少给的那部分要说出来，否则「就这 20 个」是假话 -->
            @if (c.capped) {
              <div class="hint">
                {{ 'risk.graph.members_capped' | t: { shown: c.shown, total: c.total } }}
              </div>
            }
            <div class="hint">{{ 'risk.head.last_seen' | t }} {{ c.last_seen_at }}</div>
          </div>
        </div>
      } @empty {
        @if (raw()) {
          <div class="state">{{ 'risk.graph.empty' | t }}</div>
        }
      }

      @if (links().length) {
        <div class="card">
          <div class="card-head">{{ 'risk.graph.link_stats' | t }}</div>
          <div class="card-body">
            @for (l of links(); track l.type) {
              <span class="tag">{{ l.type }} × {{ l.count }}</span>
            }
          </div>
        </div>
      }
    }
  `,
})
export class RiskGraph {
  /** 两个端点的原始响应（由风控页的抽屉传入，取数在那边做） */
  readonly raw = input<unknown>(null);
  /** 两份响应形状不同（nodes/edges ↔ device_clusters/link_type_stats）⇒ 明着选，不去猜形状 */
  readonly mode = input<'graph' | 'clusters'>('graph');

  protected readonly nodeHeads = NODE_HEADS;
  protected readonly edgeHeads = EDGE_HEADS;

  /** 簇规模 / 跳数 / 判定。判定查不到词条就显示枚举原文（不吞成空白）。 */
  protected readonly tiles = computed(() => {
    if (this.mode() !== 'graph') return [];
    const d = this.raw() as Row | null;
    if (!d) return [];
    const verdict = String(d['risk_verdict'] ?? '');
    const key = VERDICTS[verdict];
    return [
      { label: 'risk.graph.size', value: String(num(d['cluster_size'])) },
      { label: 'risk.graph.hops', value: String(num(d['hops'])) },
      { label: 'risk.graph.verdict', value: verdict ? t(key ?? verdict) : '' },
    ].filter((x) => x.value !== '');
  });

  /** 节点行：status -1 是「用户行已被删」的哨兵（GONE），不是禁用 ⇒ 单独一句话 */
  protected readonly nodes = computed(() =>
    rowsAny(this.raw(), 'nodes').map((n) => {
      const s = num(n['status']);
      return {
        username: String(n['username'] ?? ''),
        status_label: s === GONE ? t('risk.graph.gone') : t(s === 1 ? 'app.enabled' : 'app.disabled'),
        root: n['is_root'] ? '✓' : '',
      };
    }),
  );

  /** 边表：两端是 hashid，直接显示等于给人两串不认得的码 ⇒ 用节点表里的用户名回填 */
  protected readonly edges = computed(() => {
    const name = new Map(
      rowsAny(this.raw(), 'nodes').map((n) => [String(n['id'] ?? ''), String(n['username'] ?? '')]),
    );
    return rowsAny(this.raw(), 'edges').map((e) => {
      const from = String(e['from'] ?? '');
      const to = String(e['to'] ?? '');
      return {
        from_name: name.get(from) ?? from,
        to_name: name.get(to) ?? to,
        type: String(e['type'] ?? ''),
      };
    });
  });

  /**
   * 设备簇：`members` 服务端只取前 20 个而 `account_count` 是全量 ⇒ 两者不等就说清「这是前 N 个」。
   * `capped` 用列表长度与账号数比，**不写死 20**（服务端换个上限这里跟着变）。
   */
  protected readonly clusters = computed(() =>
    rowsAny(this.raw(), 'device_clusters').map((c) => {
      const members = Array.isArray(c['members']) ? (c['members'] as Row[]) : [];
      const total = num(c['account_count']);
      return {
        key: String(c['fp_masked'] ?? ''),
        total,
        shown: members.length,
        capped: members.length < total,
        last_seen_at: String(c['last_seen_at'] ?? ''),
        members: members.map((m) => ({
          id: String(m['id'] ?? ''),
          username: String(m['username'] ?? ''),
        })),
      };
    }),
  );

  /** 关联类型分布：`{link_type: 条数}` 对象（键是后端枚举原文，与规则 type 一样不译） */
  protected readonly links = computed(() => {
    const s = (this.raw() as Row | null)?.['link_type_stats'];
    if (!s || typeof s !== 'object' || Array.isArray(s)) return [];
    return Object.entries(s as Row).map(([type, count]) => ({ type, count: num(count) }));
  });
}
