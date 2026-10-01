/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { apiService } from './ApiService';
import {
  ItemsPage,
  RiskSandboxRequest,
  RiskSandboxResult,
  RiskDeviceItem,
  RiskDeviceBlockResult,
  RiskIpItem,
  RiskIpWriteResult,
  RiskUserItem,
  RiskHoldResult,
  RiskReleaseResult,
  RiskTimelineResult,
  RiskClusterItem,
  RiskClusterDetectResult,
  RiskClusterConfirmRequest,
  RiskClusterMembersResult
} from '../model/RiskModels';
import { pageQuery } from '../model/DataModels';
// 本文件全是**请求期**求值的函数（不是模块顶层常量）⇒ 直接调 t() 安全，语言切换后下一次请求即用新语言
import { t, t1 } from '../i18n/Translations';

/**
 * 风控域的服务端接口（批次 5）。
 *
 * 分工：**配置驱动那三个模块**（风控规则 / 风控事件 / 反作弊事件）的列表与增改走
 * ResourceService + common/RiskModules.ets 的模块描述；本文件收的是它们的**动作端点**
 * （沙箱试算 / 人工处置 / 反作弊审核）以及四个独立页（设备/IP/异常用户/团伙）的读写。
 *
 * 三条贯穿本域的口径：
 *  1. **分页参数是 `size`**（不是 limit），响应形状是 `{total, items}`（不是 `{list}`）。
 *     查询串统一走 `pageQuery`（DataModels），它一次扇出 limit/size/per_page 三个别名 ——
 *     本域读 `size`，配置驱动那三个模块走 ResourceSchema 的 `/risk/rule|event` 也是这一套。
 *  2. **金额（冻结/解冻）原样收发**：DECIMAL 的 string，经 bcmath 字符串比较；
 *     这里只做 `String()` 与拼接，不做任何 Number()/算术 —— 前端算金额必丢精度。
 *  3. 信封 `message` 为空话术（框架缺省 'success'）时，**回执在 `data` 里**
 *     （hold/release/fp_masked/ip_masked），由本文件拼成一句给运营看的话，不另编成功文案；
 *     端点自己有话术的（`data.message`）一律**原样用服务端那句**。
 */

/** `data.message` 型回执（风控事件处置 / 反作弊审核 / IP 重查都是这个形状） */
interface DataMessageResult {
  message?: string;
}

/**
 * 试算的上下文文本 → 请求体里的 context 对象（空串 = 空对象）。
 * ArkTS 禁「裸对象字面量赋给未声明类型」（`let c: Object = {}` 报 arkts-no-untyped-obj-literals），
 * 故用 Record 收口；解析失败抛出，错误原文留在框内让运营改。
 */
function parseContext(text: string): Record<string, Object> {
  const trimmed = text.trim();
  if (trimmed.length === 0) {
    const empty: Record<string, Object> = {};
    return empty;
  }
  try {
    return JSON.parse(trimmed) as Record<string, Object>;
  } catch (_) {
    throw new Error(t('risk.svc.context_bad_json'));
  }
}

export class RiskService {
  /* ------------------------------ 风控规则动作 ------------------------------ */

  /**
   * 沙箱试算（POST /admin/v1/risk/rule/test）—— 只读：不写库、不落日志、不触发处置。
   * context 必须是**对象**：控制器 `is_array($context) ?: []`，发字符串会被静默当成空上下文
   * （试算结果看着像「没命中」，实际是参数没进去）⇒ 这里把表单里的 JSON 文本解析成对象再上送，
   * 解析失败直接抛（FormDialog 会把错误留在框内，用户改完可重试）。
   */
  static async testRule(ruleId: string, userId: string, checkType: string, contextText: string): Promise<RiskSandboxResult> {
    const body: RiskSandboxRequest = {
      rule_id: ruleId,
      user_id: userId,
      check_type: checkType,
      context: parseContext(contextText)
    };
    const response = await apiService.post<RiskSandboxResult>('/admin/v1/risk/rule/test', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  /* ------------------------------ 风控事件动作 ------------------------------ */

  /**
   * 人工处置（POST /admin/v1/risk/event/{hashid}/handle）。
   * 端点**不写事件本身**（risk_log 没有审核状态列），只把处置记进操作审计 ——
   * 所以列表刷新后行不会变，提示也必须说清「只是留痕」，不能让运营以为事件状态被改了。
   */
  static async handleEvent(id: string, decision: string, note: string): Promise<string[]> {
    const body: Record<string, string> = {};
    body.decision = decision;
    body.note = note;
    const response = await apiService.post<DataMessageResult>(`/admin/v1/risk/event/${id}/handle`, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    const said = response.data.message ?? '';
    const lines: string[] = [];
    lines.push(said.length > 0 ? said : t('risk.svc.event_handled'));
    lines.push(t('risk.svc.event_audit_only'));
    return lines;
  }

  /* ---------------------------- 反作弊事件动作 ---------------------------- */

  /** 人工审核（POST /admin/v1/anticheat/events/{hashid}/review）—— status 是四值字符串枚举 */
  static async reviewAntiCheat(id: string, status: string, note: string): Promise<string[]> {
    const body: Record<string, string> = {};
    body.status = status;
    body.note = note;
    const response = await apiService.post<DataMessageResult>(`/admin/v1/anticheat/events/${id}/review`, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    const said = response.data.message ?? '';
    const lines: string[] = [];
    lines.push(said.length > 0 ? said : t('risk.svc.cheat_reviewed'));
    lines.push(note.length > 0
      ? t('risk.svc.cheat_verdict_note').replace('{status}', status).replace('{note}', note)
      : t1('risk.svc.cheat_verdict', 'status', status));
    return lines;
  }

  /* -------------------------------- 设备指纹 --------------------------------
   * 列表每行同时回 `fp_hash`（完整，64 位十六进制）与 `fp_masked`（展示用）⇒ 拉黑/解封
   * 直接挂在行上，请求体 `{fp_hash}`（id 在 body，不在路径）。
   * ------------------------------------------------------------------------ */

  static async listDevices(page: number, size: number): Promise<ItemsPage<RiskDeviceItem>> {
    const response = await apiService.get<ItemsPage<RiskDeviceItem>>(
      `/admin/v1/risk/device/list?${pageQuery(page, size)}`
    );
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  /**
   * 拉黑设备（POST /admin/v1/risk/device/block）—— 管理端 Redis 标记，**TTL 30 天**。
   * 这是**真阻断**：`RiskService::check()` 对人工拉黑短路成 `block`，与任何规则是否启用无关
   * （install.sql 那条 device 规则 status=0）⇒ 该设备上的账号登录/充值/提现都会被拒；
   * Redis 不可用时按未拉黑处理（fail-open）。回执里写清这两点与到期时间，别让运营以为是永久封禁。
   */
  static async blockDevice(fpHash: string): Promise<string[]> {
    const body: Record<string, string> = {};
    body.fp_hash = fpHash;
    const response = await apiService.post<RiskDeviceBlockResult>('/admin/v1/risk/device/block', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    const lines: string[] = [];
    lines.push(t1('risk.svc.device_blocked', 'fp', response.data.fp_masked));
    lines.push(t('risk.svc.device_block_effect'));
    lines.push(t('risk.svc.device_block_ttl'));
    return lines;
  }

  static async unblockDevice(fpHash: string): Promise<string[]> {
    const body: Record<string, string> = {};
    body.fp_hash = fpHash;
    // 端点成功时不带任何 data（success() 的空信封），故这句话只能由本地写
    const response = await apiService.post<void>('/admin/v1/risk/device/unblock', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    const lines: string[] = [];
    lines.push(t('risk.svc.device_unblocked'));
    return lines;
  }

  /* --------------------------------- IP 信誉 ---------------------------------
   * 四个端点都收**原文 IP**（`filter_var(..., FILTER_VALIDATE_IP)`），列表只回掩码；
   * 没有解封端点 —— 黑名单写入（source=internal_blacklist score=0）之后只能靠
   * 白名单/申诉（同一对 source/score）覆盖，写不回「中性」态。
   * ------------------------------------------------------------------------ */

  static async listIps(page: number, size: number): Promise<ItemsPage<RiskIpItem>> {
    const response = await apiService.get<ItemsPage<RiskIpItem>>(
      `/admin/v1/risk/ip/list?${pageQuery(page, size)}`
    );
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  /**
   * 写信誉：kind 只认 block / whitelist / appeal（三个端点，两个语义）。
   * appeal 与 whitelist 在服务端是**同一件事**（同 source 同 score、同一段代码），
   * 这里保留两条路径只为不吞掉端点，文案上如实说明。
   */
  static async writeIpReputation(ip: string, kind: string): Promise<string[]> {
    const body: Record<string, string> = {};
    body.ip = ip;
    const response = await apiService.post<RiskIpWriteResult>(`/admin/v1/risk/ip/${kind}`, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    const lines: string[] = [];
    const source = response.data.source ?? '';
    const score = source === 'internal_whitelist' ? t('risk.svc.score_whitelist') : t('risk.svc.score_blacklist');
    lines.push(t('risk.svc.ip_written')
      .replace('{ip}', response.data.ip_masked).replace('{source}', source).replace('{score}', score));
    lines.push(t('risk.svc.ip_cache_cleared'));
    if (kind === 'appeal') {
      lines.push(t('risk.svc.appeal_same_as_whitelist'));
    }
    return lines;
  }

  /** 重查：删本地信誉缓存后重新读 DB（外部代理/VPN 检测服务未接入，控制器原话） */
  static async recheckIp(ip: string): Promise<string[]> {
    const body: Record<string, string> = {};
    body.ip = ip;
    const response = await apiService.post<DataMessageResult>('/admin/v1/risk/ip/recheck', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    const lines: string[] = [];
    const said = response.data.message ?? '';
    lines.push(said.length > 0 ? said : t('risk.svc.ip_rechecked'));
    return lines;
  }

  /* ------------------------------- 异常用户 -------------------------------
   * 行标识是 `user_id`（hashid）。冻结/解冻是**资金动作**：金额由服务端算（冻结全额、
   * 解冻按冻结子台账 FIFO 消费），客户端只原样收发展示，不做任何换算。
   * ---------------------------------------------------------------------- */

  static async listRiskUsers(page: number, size: number): Promise<ItemsPage<RiskUserItem>> {
    const response = await apiService.get<ItemsPage<RiskUserItem>>(
      `/admin/v1/risk/users?${pageQuery(page, size)}`
    );
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  /** 冻结账户：**无请求体**，金额由服务端取全额（POST /admin/v1/risk/users/{hashid}/hold） */
  static async holdUser(userId: string): Promise<string[]> {
    const response = await apiService.post<RiskHoldResult>(`/admin/v1/risk/users/${userId}/hold`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    const lines: string[] = [];
    lines.push(t1('risk.svc.hold_done', 'amount', response.data.frozen_amount));
    return lines;
  }

  /**
   * 解除冻结：amount 传**十进制字符串**原样上送；空串 = 不带该参数 = 服务端按全额释放。
   * 实际释放量以回执 released_amount 为准（可能小于请求量：受冻结子台账剩余量约束）。
   */
  static async releaseUser(userId: string, amount: string): Promise<string[]> {
    const body: Record<string, string> = {};
    body.amount = amount;
    const response = await apiService.post<RiskReleaseResult>(`/admin/v1/risk/users/${userId}/release`, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    const lines: string[] = [];
    lines.push(t1('risk.svc.release_done', 'amount', response.data.released_amount));
    return lines;
  }

  /** 时间线：合并 risk_log / play_log / anticheat_event，最多 200 条（只读） */
  static async timeline(userId: string): Promise<RiskTimelineResult> {
    const response = await apiService.get<RiskTimelineResult>(`/admin/v1/risk/users/${userId}/timeline`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  /* --------------------------------- 团伙 ---------------------------------
   * 路由直挂 /risk/clusters（**没有 /list 段**）；status 是三态（1 观察中 / 2 已处置 / 0 误判），
   * 用 0/1 翻转控件会把 2 静默压成 0。
   * ---------------------------------------------------------------------- */

  static async listClusters(page: number, size: number): Promise<ItemsPage<RiskClusterItem>> {
    const response = await apiService.get<ItemsPage<RiskClusterItem>>(
      `/admin/v1/risk/clusters?${pageQuery(page, size)}`
    );
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  /** 聚类检测：**全局动作、无行上下文**，只出候选不落库（近 7 天 IP≥5 账户 / 设备≥3 账户） */
  static async detectClusters(): Promise<RiskClusterDetectResult> {
    const response = await apiService.post<RiskClusterDetectResult>('/admin/v1/risk/clusters/detect');
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  /**
   * 人工确认团伙（POST /admin/v1/risk/clusters/confirm）。
   * **member_ids 不上送**（控制器现在收 hashid、非法值 400，不是「发了会被丢成 0」那回事）：
   * confirm 是创建动作，此时还没有 cluster id，成员 hashid 只能从 `GET /clusters/{hashid}/members`
   * 取（已存在团伙的端点），detect 候选也只带 type/fingerprint ⇒ 没有可发的成员 id；
   * 服务端 `resolveMemberIds()` 会按指纹现查，与 members 端点同一份名单，确认前后一致。
   */
  static async confirmCluster(req: RiskClusterConfirmRequest): Promise<void> {
    const response = await apiService.post<Object>('/admin/v1/risk/clusters/confirm', req);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /** 团伙状态：0 误判 / 1 观察中 / 2 已处置（PUT，body 里是数字） */
  static async setClusterStatus(id: string, status: number): Promise<void> {
    const body: Record<string, number> = {};
    body.status = status;
    const response = await apiService.put<Object>(`/admin/v1/risk/clusters/${id}/status`, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /** 团伙成员（只读）：member_ids 为空时按 type+fingerprint 现查（same_device 查设备表、same_ip 查 risk_log） */
  static async clusterMembers(id: string): Promise<RiskClusterMembersResult> {
    const response = await apiService.get<RiskClusterMembersResult>(`/admin/v1/risk/clusters/${id}/members`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

}
