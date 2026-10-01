/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage } from '../core/crud';
import { T, t } from '../core/i18n/i18n';
import { idOf, json } from '../core/render';
import { errText, num, rowsOf } from '../core/util';
import { Drawer, Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';
import { FormModal } from '../components/form-modal';
import { RiskCharts } from './risk-charts';
import { RiskGraph } from './risk-graph';
import {
  ANTICHEAT_ACTS,
  ANTICHEAT_STATUS,
  CANDIDATE_ACTS,
  CANDIDATE_HEADS,
  CLUSTER_ACTS,
  CLUSTER_STATUS,
  clusterCreated,
  clusterMarked,
  DEVICE_ACTS,
  EVENT_ACTS,
  IP_VERBS,
  PanelKind,
  PANEL_TITLES,
  RISK_HEADS,
  RISK_PATHS,
  RISK_TABS,
  READ_PANELS,
  RULE_ACTS,
  RULE_FIELDS,
  TIMELINE_HEADS,
  USER_ACTS,
  deviceBody,
  deviceConfirmText,
  deviceNote,
  fundsNote,
  parseContext,
  whoAnti,
  whoEvent,
  whoUser,
} from './risk-fields';

const R = '/admin/v1/';

@Component({
  selector: 'app-risk',
  imports: [StateBlock, RiskCharts, RiskGraph, Table, Pager, Tabs, Drawer, FormModal, T],
  template: `
    <div class="page-head">
      <h1>{{ 'risk.title' | t }}</h1>
      <span class="sub">{{ 'risk.subtitle' | t }}</span>
      <div class="spacer"></div>
      @if (tab() !== 'overview') {
        <input
          class="input"
          [placeholder]="'risk.search_placeholder' | t"
          [value]="keyword()"
          (input)="keyword.set($any($event.target).value)"
          (keyup.enter)="search()"
        />
        <button class="btn" (click)="search()">{{ 'app.search' | t }}</button>
      }
      @if (tab() === 'clusters') {
        <!-- 两个全局动作、都无行上下文（候选按窗口算、设备簇按账号数 TOP10）⇒ 摆页头，不做行内动作 -->
        <button class="btn" (click)="detect()">{{ 'risk.cluster.detect' | t }}</button>
        <button class="btn" (click)="graphClusters()">{{ 'risk.graph.clusters' | t }}</button>
      }
      @if (tab() === 'ip') {
        <!-- 四个动作按**运营输入的原文 IP** 操作（列表只回 ip_hash 掩码，不可逆）⇒ 摆页头 -->
        <button class="btn danger" (click)="ipAct('block')">{{ 'risk.ip.block' | t }}</button>
        <button class="btn" (click)="ipAct('whitelist')">{{ 'risk.ip.whitelist' | t }}</button>
        <button class="btn" (click)="ipAct('appeal')">{{ 'risk.ip.appeal' | t }}</button>
        <button class="btn" (click)="ipAct('recheck')">{{ 'risk.ip.recheck' | t }}</button>
      }
      @if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ {{ 'app.create' | t }}</button>
      }
      <button class="btn" (click)="load()">{{ 'app.refresh' | t }}</button>
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    @if (note()) {
      <div [class]="noteErr() ? 'alert' : 'notice'">{{ note() }}</div>
    }

    <ui-state [loading]="loading()" [error]="error()" [empty]="tab() !== 'overview' && !rows().length">
      @if (tab() === 'overview') {
        <!-- 总览整块（指标卡 + 趋势 + 动作分布 + 规则效果 + 原始响应）在 risk-charts.ts：
             本文件已贴着 500 行，且那部分只需要 raw() 一个入口 -->
        <app-risk-charts [raw]="raw()" />
      } @else {
        <div class="card">
          <div class="card-body">
            <ui-table [rows]="rows()" [heads]="heads()" [actions]="actions()" (act)="run($event.row, $event.key)" />
          </div>
        </div>
      }
    </ui-state>

    @if (tab() !== 'overview' && rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }

    <ui-form
      [open]="formOpen()"
      [title]="formTitle()"
      [fields]="formFields()"
      [value]="formValue()"
      [error]="formError()"
      [saving]="saving()"
      (save)="submit($event)"
      (close)="closeForm()"
    />

    <!-- 试算 / 聚类候选 / 团伙成员共用这一个抽屉：panel() 决定内容与标题（PANEL_TITLES），不会同时开 -->
    <ui-drawer [open]="panel() !== ''" [title]="panelTitle()" (close)="closePanel()">
      @if (panel() === 'candidates') {
        <ui-table
          [rows]="candidates()"
          [heads]="candidateHeads"
          [actions]="candidateActs"
          (act)="confirmCluster($event.row)"
        />
      } @else if (panel() === 'timeline') {
        <ui-table [rows]="timelineRows()" [heads]="timelineHeads" />
      } @else if (panel() === 'graph' || panel() === 'graphclusters') {
        <!-- 两份响应形状不同 ⇒ 明着传 mode，让组件别去猜（见 risk-graph.ts） -->
        <app-risk-graph [raw]="result()" [mode]="panel() === 'graph' ? 'graph' : 'clusters'" />
      } @else if (result(); as d) {
        <pre class="raw">{{ pretty(d) }}</pre>
      }
    </ui-drawer>
  `,
})
export class Risk extends CrudPage {
  protected readonly tabs = RISK_TABS;
  protected readonly tab = signal('overview');
  protected readonly raw = signal<unknown>(null);
  /** 动作回执（服务端 message / 服务端算出的金额）：**就地**显示，不把列表打成错误态 */
  protected readonly note = signal('');
  protected readonly noteErr = signal(false);
  /** 右侧抽屉的内容类型（见 risk-fields 的 PanelKind），'' = 关着；标题查 PANEL_TITLES */
  protected readonly panel = signal<'' | PanelKind>('');
  /** 抽屉数据（四种面板共用这一个槽：结构各异，只有候选与时间轴要再摊平成行） */
  protected readonly result = signal<unknown>(null);

  protected readonly CLUSTER_STATUS = CLUSTER_STATUS;
  protected readonly candidateHeads = CANDIDATE_HEADS;
  protected readonly candidateActs = CANDIDATE_ACTS;
  protected readonly timelineHeads = TIMELINE_HEADS;
  /** 时间轴：三源合并后的 `events`（服务端已按时间倒序并截到 200 条） */
  protected readonly timelineRows = computed(() => rowsOf(this.result(), 'events'));

  protected readonly heads = computed((): Record<string, string> => RISK_HEADS[this.tab()] ?? {});
  /** 抽屉标题与候选行都从 panel()/result() 派生（不另存一份，免得两处对不上） */
  protected readonly panelTitle = computed(() => PANEL_TITLES[this.panel()] ?? '');
  protected readonly candidates = computed(() => rowsOf(this.result(), 'candidates'));

  /**
   * 当前标签页的写能力，缺省即没有该能力：
   *  - 规则：唯一有表单的模块（ends.create/update + 专用 toggle 端点，statused）
   *  - 事件/风险用户/团伙/反作弊/设备：只有行内动作（ends 一个都不给 ⇒ 不出编辑/删除/新建）
   *  - 总览/IP：null ⇒ 不出「操作」列。IP 那四个端点要的是**运营输入的原文 IP**（列表只回 sha256
   *    前 8 位掩码，不可逆，行里取不到能提交的值）⇒ 动作摆页头，不摆点了必 400 的死按钮。
   */
  protected crud(): Crud | null {
    switch (this.tab()) {
      case 'rules':
        return {
          noun: 'risk.noun.rule',
          fields: RULE_FIELDS,
          ends: {
            create: R + 'risk/rule/create',
            update: (id) => R + 'risk/rule/' + id,
            // 函数形态：hashid 在路径里、请求体为空 —— 服务端自己翻转，不认客户端给的 status
            toggle: (id) => R + 'risk/rule/' + id + '/toggle',
          },
          statused: true,
          // update 与 create 走同一个 fill()：只发改动字段会 422，缺 status 会被落成 1
          fullEdit: true,
          label: (row) => String(row['name'] ?? ''),
          extra: RULE_ACTS,
        };
      case 'events':
        return { noun: 'risk.noun.event', fields: [], ends: {}, extra: EVENT_ACTS };
      case 'users':
        return { noun: 'risk.noun.user', fields: [], ends: {}, extra: USER_ACTS };
      case 'clusters':
        return { noun: 'risk.noun.cluster', fields: [], ends: {}, extra: CLUSTER_ACTS };
      case 'anticheat':
        return { noun: 'risk.noun.anticheat', fields: [], ends: {}, extra: ANTICHEAT_ACTS };
      case 'devices':
        return { noun: 'risk.noun.device', fields: [], ends: {}, extra: DEVICE_ACTS };
      default:
        return null;
    }
  }

  /** 模板作用域只认类成员，模块 import 不可见 ⇒ 挂成字段供 `{{ pretty(x) }}` 用 */
  protected readonly pretty = json;

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.closeForm();
    this.closePanel();
    this.note.set('');
    void this.load();
  }

  protected closePanel(): void {
    this.panel.set('');
    this.result.set(null);
  }

  protected override async fetch(): Promise<Page<Row>> {
    if (this.tab() === 'overview') {
      try {
        this.raw.set(await this.api.get<unknown>(R + 'risk/overview'));
      } catch (e) {
        this.error.set(errText(e));
      }
      return { list: [], total: 0, page: 1, limit: this.pageSize };
    }
    this.raw.set(null);
    const url = R + (RISK_PATHS[this.tab()] ?? RISK_PATHS['events']!);
    const res = await this.api.list<Row>(url, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
    // 团伙状态是 0/1/2 三值（不是启用/停用）⇒ 摊平一列中文；原值原样留着，别改写后端结构
    if (this.tab() !== 'clusters') return res;
    return {
      ...res,
      list: res.list.map((r) => ({
        ...r,
        status_label: CLUSTER_STATUS[String(r['status'])] ?? String(r['status'] ?? ''),
      })),
    };
  }

  /** 行内动作分流：各模块的语义/请求体/回执来源不同，都放本页；只有规则的编辑与启停借基类入口。 */
  protected override async run(row: Row, key: string): Promise<void> {
    if (key === 'test') return this.sandbox(row);
    if (key === 'approve' || key === 'reject') return this.handleEvent(row, key);
    if (key === 'hold' || key === 'release') return this.funds(row, key);
    if (key.startsWith('cl_')) return this.setCluster(row, num(key.slice(3)));
    if (key.startsWith('rv_')) return this.review(row, key.slice(3));
    if (key === 'block' || key === 'unblock') return this.deviceAct(row, key);
    // 三个只读抽屉（团伙成员 / 风险时间轴 / 反作弊详情）：面板类型与路径见 risk-fields.READ_PANELS
    const read = READ_PANELS[key];
    if (read) return this.openPanel(read.panel, () => this.api.get(R + read.path(idOf(row))));
    return super.run(row, key);
  }

  // ---------- 内部：回执与对象标识 ----------

  /**
   * 动作回执：优先服务端 data.message（专门写的那句），其次信封 message，都是样板（success）时用 fallback；
   * 失败原样透出服务端 message（不吞成「操作失败」）。成功后刷新列表 —— 金额/状态以服务端为准，不做乐观改行。
   */
  private async act(
    fn: () => Promise<{ data: unknown; message: string }>,
    fallback: (d: Row) => string,
  ): Promise<void> {
    this.note.set('');
    this.noteErr.set(false);
    try {
      const env = await fn();
      const d = (env.data && typeof env.data === 'object' ? env.data : {}) as Row;
      const msg = String(d['message'] ?? '') || (env.message !== 'success' ? env.message : '');
      this.note.set(fallback(d) + (msg ? '｜' + msg : ''));
      await this.load();
    } catch (e) {
      this.noteErr.set(true);
      this.note.set(errText(e));
    }
  }

  /**
   * 抽屉装载（试算 / 聚类候选 / 团伙成员共用）：清回执 → 取数 → 开面板；失败就地报错，
   * 不动列表也不开一个空抽屉。三种面板的数据都进 result()，标题由 panel() 查表。
   */
  private async openPanel(which: PanelKind, load: () => Promise<unknown>): Promise<void> {
    this.note.set('');
    this.noteErr.set(false);
    try {
      this.result.set(await load());
      this.panel.set(which);
    } catch (e) {
      this.noteErr.set(true);
      this.note.set(errText(e));
    }
  }

  // ---------- 风控规则 ----------

  /**
   * 沙箱试算（POST /risk/rule/test，只读：不写库、不落日志、不触发处置）。rule_id/user_id 是 hashid，
   * check_type 走 RiskSandboxService::test 的场景分支，context 是评估器读的原文（ip/user_agent/amount/fp_hash）。
   * 返回的 data 是结构化的（matched/message/severity/action）⇒ **原样**进抽屉：只显示 message
   * 会把「命中与否、什么处置」抹掉。
   */
  protected async sandbox(row: Row): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    const user = prompt(t('risk.sandbox.user_prompt'));
    if (user === null) return;
    const checkType = prompt(t('risk.sandbox.check_type_prompt'), 'login');
    if (checkType === null) return;
    const rawCtx = prompt(t('risk.sandbox.context_prompt'), '{}');
    if (rawCtx === null) return;
    const context = parseContext(rawCtx);
    if (context === null) {
      this.noteErr.set(true);
      this.note.set(t('risk.sandbox.context_invalid'));
      return;
    }
    await this.openPanel('result', () =>
      this.api.post<unknown>(R + 'risk/rule/test', {
        rule_id: id,
        user_id: user.trim(),
        check_type: checkType.trim() || 'login',
        context,
      }),
    );
  }

  // ---------- 风险事件 ----------

  /**
   * 人工处置（POST /risk/event/{hashid}/handle `{decision, note≤500}`）。驳回 = 不认可该命中 ⇒ 先二次
   * 确认（文案带规则名与用户）；note 取消输入即放弃。后端只把 decision+note 记进回执与操作审计
   * （risk_log 没有审核状态列），行不变。
   */
  protected async handleEvent(row: Row, key: string): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    if (key === 'reject' && !confirm(t('risk.event.reject_confirm', { name: whoEvent(row) }))) {
      return;
    }
    const input = prompt(t('risk.event.note_prompt'));
    if (input === null) return;
    await this.act(
      () =>
        this.api.envelope('POST', R + 'risk/event/' + id + '/handle', {
          decision: key,
          note: input.trim(),
        }),
      () => t('risk.event.marked'),
    );
  }

  // ---------- 风险用户（钱路） ----------

  /**
   * 冻结 / 解冻 —— 都是钱路，一律二次确认（文案能认出是谁）。hold **无请求体**，金额由服务端按可用
   * 余额全额算（回执 data.frozen_amount）；release `{amount?}`，缺省全额。金额是 bcmath 十进制**字符串**，
   * 前端原样上送、不做任何加减/浮点转换（过一趟 Number，DECIMAL 的小数位就没了）。
   */
  protected async funds(row: Row, key: string): Promise<void> {
    const id = String(row['user_id'] ?? '') || idOf(row);
    if (!id) return;
    const who = whoUser(row);
    if (key === 'hold') {
      if (!confirm(t('risk.user.hold_confirm', { name: who }))) return;
    } else if (!confirm(t('risk.user.release_confirm', { name: who }))) {
      return;
    }
    let body: Row | undefined;
    if (key === 'release') {
      const amount = prompt(t('risk.user.amount_prompt'));
      if (amount === null) return;
      body = amount.trim() === '' ? undefined : { amount: amount.trim() };
    }
    await this.act(
      () => this.api.envelope<Row>('POST', R + 'risk/users/' + id + '/' + key, body),
      (d) => fundsNote(key, d),
    );
  }

  // ---------- 关联团伙 ----------

  /**
   * 团伙状态（PUT /risk/clusters/{hashid}/status `{status}`，0=误判 1=观察中 2=已处置）。
   * 三个按钮各自只在自己不是当前状态时出现（CLUSTER_ACTS 的 when）—— 多值状态不能按 0/1 翻转（会把 2 压成 0）。
   */
  protected async setCluster(row: Row, status: number): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    await this.act(
      () => this.api.envelope('PUT', R + 'risk/clusters/' + id + '/status', { status }),
      () => clusterMarked(row, id, status),
    );
  }

  /** 聚类检测（POST /risk/clusters/detect）：只出候选、不落库。全局动作无行上下文 ⇒ 页头触发。 */
  protected async detect(): Promise<void> {
    await this.openPanel('candidates', () => this.api.post<unknown>(R + 'risk/clusters/detect'));
  }

  /** 设备关联簇（GET /risk/graph/clusters）：全站账号数 ≥2 的设备 TOP10 + 关联类型分布。
   *  与上面的「聚类检测」不是一回事：检测按窗口算**候选**（可能查出新团伙），这个是**当前设备现状**。 */
  protected async graphClusters(): Promise<void> {
    await this.openPanel('graphclusters', () => this.api.get<unknown>(R + 'risk/graph/clusters'));
  }

  /**
   * 人工确认团伙（POST /risk/clusters/confirm `{type, fingerprint, name, user_count?}`）。
   * ⚠ **不发 member_ids**。后端已按 hashid 解码（decodeId，非法值 400），理由不是"发不上去"而是：
   * ① detect 出的候选没落库、没有 hashid，`/clusters/{hashid}/members` 对它无从调用 ⇒ 这个弹框里
   *    根本没有可勾选的成员清单；② 不发才走 resolveMemberIds() 的**读时**指纹回填（same_device→
   *    device_account_map、same_ip→risk_log 去重），后来加入的账号会跟着出现，而 confirm 是唯一写入口
   *    （status 只改状态）⇒ 发快照等于把成员冻在那一刻、冻错了只能另建团伙。成员看只读的「成员」动作。
   */
  protected async confirmCluster(row: Row): Promise<void> {
    const type = String(row['type'] ?? '');
    const fingerprint = String(row['fingerprint'] ?? '');
    if (!type || !fingerprint) return;
    const name = prompt(
      t('risk.cluster.name_prompt'),
      `${type} ${String(row['fingerprint_masked'] ?? '')}`,
    );
    if (name === null || name.trim() === '') return;
    await this.act(
      () =>
        this.api.envelope('POST', R + 'risk/clusters/confirm', {
          type,
          fingerprint,
          name: name.trim(),
          user_count: num(row['user_count']),
        }),
      (d) => clusterCreated(d, name.trim()),
    );
    this.panel.set('');
  }

  // ---------- 设备 ----------

  /**
   * 拉黑 / 解封设备（POST /risk/device/{block|unblock}，**路径不带 id**，请求体 `{fp_hash}`）。入参必须是
   * **完整 64 位哈希**（服务端 fpHash() 只认 `/^[0-9a-f]{64}$/`，掩码提交必 400），列表已回传；掩码只进
   * 确认文案。拉黑由 RiskService::check() 短路成真拦截（不看 device_fingerprint 规则是否启用）
   * ⇒ 二次确认；解封是安全方向，不问。
   */
  protected async deviceAct(row: Row, key: string): Promise<void> {
    if (!row['fp_hash']) return;
    if (key === 'block' && !confirm(deviceConfirmText(row))) return;
    await this.act(
      () => this.api.envelope<Row>('POST', R + 'risk/device/' + key, deviceBody(row)),
      (d) => deviceNote(row, key, d),
    );
  }

  // ---------- 反作弊 ----------

  /**
   * 人工审核（POST /anticheat/events/{hashid}/review `{status, note≤255}`）。status 是**字符串枚举**
   * open/confirmed/whitelisted/closed（不是 0/1 翻转）；confirmed 是「判定作弊」⇒ 二次确认；
   * whitelisted 按 Apidoc 需附 note（取消输入 = 放弃）。
   */
  protected async review(row: Row, status: string): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    if (status === 'confirmed' && !confirm(t('risk.ac.confirm', { name: whoAnti(row) }))) return;
    let note = '';
    if (status === 'whitelisted') {
      const input = prompt(t('risk.ac.note_prompt'));
      if (input === null) return;
      note = input.trim();
    }
    await this.act(
      () => this.api.envelope('POST', R + 'anticheat/events/' + id + '/review', { status, note }),
      () => t('risk.ac.marked', { status: t(ANTICHEAT_STATUS[status] ?? status) }),
    );
  }

  // ---------- IP 信誉 ----------

  /**
   * IP 动作（POST /risk/ip/{block|whitelist|appeal|recheck}）：四个都收**原文 IP** 放进请求体，服务端
   * hash('sha256') 之后落库 / 删信誉缓存；没有 unblock 端点（白名单即放行）。列表只回 ip_hash 前 8 位
   * 掩码（不可逆）⇒ 没有行上下文，只能按运营输入的原文 IP 走。
   */
  protected async ipAct(key: string): Promise<void> {
    const ip = prompt(t(key === 'recheck' ? 'risk.ip.recheck_prompt' : 'risk.ip.prompt'));
    if (ip === null || ip.trim() === '') return;
    await this.act(
      () => this.api.envelope<Row>('POST', R + 'risk/ip/' + key, { ip: ip.trim() }),
      // 回执里的掩码 = 服务端 hash 过的那个 IP（不是我们以为的那个），有就用它
      (d) => `${t(IP_VERBS[key] ?? 'risk.ip.verb.done')} ${String(d['ip_masked'] ?? ip.trim())}`,
    );
  }
}
