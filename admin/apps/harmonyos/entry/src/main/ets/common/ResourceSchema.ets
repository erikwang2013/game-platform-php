/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 资源模块的**描述契约**与共用小工具（批次 2 起，现共 18 个模块）。
 *
 * 一份描述同时喂给 ResourceManagePage 的列表与 FormDialog 的表单 —— 18 个模块共用一套增删改页面，
 * 不复制表单、也不复制列表骨架。值域一律以控制器 validator 与列定义为真值：
 *   admin/app/admin/v1/controller/{GameCategory,GameServer,Activity,Achievement,VipLevel,
 *   Leaderboard,CountryConfig,Config,PlatformUser,Role,Permission,
 *   Payment,Coupon,CdnProvider,Withdraw,RiskRule,RiskEvent}Controller.php 与 AntiCheatController.php
 *
 * 三条贯穿全批的口径：
 *  1. 列表返回的 id 直接当路径段用（18 个模块的 {hashid} 都是 hashid；系统配置的路由占位虽写 {id}，
 *     控制器仍 decodeId ⇒ 值同样是 hashid）。
 *  2. 没有独立 toggle 端点的模块，行内切换走局部 PUT {status}；状态多于两态的（区服 0..3、活动 0..2）
 *     不给行内切换按钮，改由表单里的下拉改 —— 一个二态按钮表达不了三态。
 *  3. 表单只管 getter/setter 齐全的列：create-only 的字段在编辑态置 disabled（FormDialog 会跳过它），
 *     没把握回填的字段（activity.game_id 是裸 BIGINT、start_at/end_at 要日期控件）一律不进表单。
 *
 * 模块描述分五处：ContentModules.ets（分类/区服/活动/成就）、ConfigModules.ets（VIP/排行榜/国家/系统配置）、
 * UserModules.ets（平台用户/角色/权限）、FundModules.ets（支付方式/优惠券/CDN/提现限额）、
 * RiskModules.ets（风控规则/风控事件/反作弊事件），注册表在 ResourceModules.ets。
 * 提现订单不是 CRUD（行内挂多个资金动作）⇒ 独立页 WithdrawManagePage，不进本契约；
 * 风控的设备/IP/异常用户/团伙四块同理（行无 id 或一行挂多个动作）⇒ 独立页，见 RiskIndexPage。
 */

import { FormField } from './FormDialog';
import { FormValues, FormPayload, ResourceRow, ResourceListResult } from '../model/DataModels';

/**
 * 模块描述 —— ResourceManagePage 只认这一个接口。
 * 路径拼接、列表解析、编辑快照这些都留在模块描述里，页面里没有一处模块名分支。
 */
export interface ResourceModule {
  /** 路由参数值，也是 ModuleIndexPage 的行标识 */
  key: string;
  title: string;
  /** 索引页副标题 */
  desc: string;
  /** 端点前缀：create 缺省 = base + '/create'，update/delete = base + '/' + id */
  base: string;
  /** 新建端点；缺省 base + '/create'（系统配置的新建就是 POST base 本身，无 /create 段） */
  createPath?: string;
  /**
   * 有没有新建端点；缺省 true。平台用户没有 POST（C 端用户自助注册）⇒ 置 false，
   * 页面不画「+ 新增」——否则点出来的是个打到 404 的表单。
   */
  canCreate?: boolean;
  /**
   * 有没有更新端点；缺省 true。风控事件/反作弊事件是**只读流水 + 一个处置动作**：
   * 没有 PUT ⇒ 置 false，页面不画「编辑」——否则点出来的是个提交到 404 的空表单。
   */
  canEdit?: boolean;
  /** 列表是否分页（决定是否画分页条） */
  paginated: boolean;
  /** 列表路径（含 query）；gameId 只有 needsGame 的模块用得上 */
  listPath: (page: number, limit: number, gameId: string) => string;
  /** 列表响应 → 通用行（各模块响应形状不同，逐个实现） */
  parseList: (data: Object) => ResourceListResult;
  /**
   * 「新建」的按钮与表单标题文案；缺省「+ 新增 / 新建<标题>」。
   * 提现阶梯限额没有逐档新建端点（POST /withdraw/limits/set 是**全档位重置**）⇒ 借 create
   * 这条路走，但按钮不能写「新增」（点下去是覆盖所有档位），故给模块自己起名。
   */
  createLabel?: string;
  /**
   * 有没有删除端点；缺省 true。提现阶梯限额只有 list/set/update（config/route.php:156-158），
   * 没有 DELETE ⇒ 置 false，页面不画删除按钮（而不是画一个点下去必然 404/405 的）。
   */
  canDelete?: boolean;
  /** 新建字段 */
  fields: FormField[];
  /** 编辑字段（create-only 字段在这里置 disabled） */
  editFields: FormField[];
  /** 新建缺省值：逐项对齐后端 create 的缺省/列默认值（status 不在表单里，见各模块注释） */
  createInitial: () => FormValues;
  /**
   * 开框前的取数钩子（缺省 undefined = 不需要）：候选项不在本模块列表里的字段用它补齐，
   * 如角色的 permission_ids 取值域是**另一个**端点（GET /admin/v1/permission 的树）。
   * 失败即抛错，页面提示后不开框（不会出现「选框空着但能提交」）。
   */
  prepareForm?: () => Promise<void>;
  /**
   * 编辑是不是**全量语义**；缺省 false（局部更新，只发改动过的字段）。
   * 风控规则的 update 走的是与 create 同一个 fill()，name/type/action 一律从请求体读且必填 ⇒
   * 只发改动字段会 422「name/type/action 必填」⇒ 该模块置 true，编辑态把整张表单发出去。
   * 注意与 create-only 字段的交互：置 true 后 `disabled` 的字段仍会被跳过（FormDialog 的 payload 先判 disabled）。
   */
  fullEdit?: boolean;
  /**
   * 状态变更方式：
   *  'toggle'      独立端点，body 是 {id, status}（服务端按客户端给的 status 写）
   *  'toggle_bare' 独立端点，**无请求体**，id 在路径里（togglePath 用 `{id}` 占位），服务端自己翻转；
   *                行内按钮的目标态与提示文案以**回执 data.status** 为准（本地翻转可能对不上并发改动）
   *  'update'      无独立端点，走局部 PUT {status}
   *  ''            无状态列，或状态多于两态（三态及以上不给二态翻转按钮）
   */
  statusMode: string;
  /** statusMode='toggle' 时的端点；'toggle_bare' 时是含 `{id}` 占位符的路径模板 */
  togglePath: string;
  /** 需要先选游戏才有上下文（区服列表与新建都要 game_id） */
  needsGame: boolean;
  /** 删除要管理员密码二次确认（控制器 confirmPassword） */
  deletePassword: boolean;
  /** 行内额外动作文案（空串 = 无） */
  extraText: string;
  /** 行内额外动作的端点（extraText 非空时才有意义） */
  extraPath?: (id: string) => string;
  /** 额外动作的 HTTP 方法；缺省 POST。优惠券统计（GET /coupon/{hashid}/stats）是读动作 */
  extraMethod?: string;
  /**
   * 额外动作的请求体；缺省不带 body。CDN 连通测试（POST /cdn/provider/test）的 id 在 **body** 里、
   * 路径段是常量，故不能再靠 extraPath(id) 传参。
   */
  extraBody?: (id: string) => FormPayload;
  /**
   * 额外动作成功后的提示文案；缺省用服务端 message。
   * 缺省那条对「有话说的」端点够用（连通正常 / 刷新成功），而只回数据的端点（统计）message 是
   * 框架缺省 'success'，摆给运营看等于没说 ⇒ 由模块把 data 格式化成一句人话。
   */
  extraMessage?: (data: Object, message: string) => string;
  /**
   * 行内动作**需要先填参数**时给字段描述（非空 ⇒ 该动作改成「开框 → 提交」，不再直接发请求）。
   * 风控规则的沙箱试算要 user_id/check_type/context 三个入参，而行 id 之外一个都拿不到；
   * 反作弊/风控事件的处置要 decision/status + note —— 这些都不是「一个无 body 的 POST」。
   * 与 extraPath 的区别：这里由 extraSubmit 决定打到哪个端点，不需要 extraPath。
   */
  extraForm?: FormField[];
  /**
   * 行内动作（带表单那一种）的提交：成功返回**要给运营看的若干行**（页面用只读弹框展示，
   * 沙箱试算/处置回执这种「有话说」的结果都走这里），失败抛错 ⇒ 错误原文留在框内、不关框。
   * 返回值是数组而不是一句话：试算结果有 matched/severity/action/message 四项，压成一行看不清。
   */
  extraSubmit?: (id: string, body: FormPayload) => Promise<string[]>;
}

/** 行内额外动作的响应：message 原样来自服务端（**不替服务端编成功文案**），data 给格式化用 */
export interface ExtraResult {
  message: string;
  data: Object;
}

/** 列表响应形状：{list, total?}（区服的裸数组不走这里，单独解析） */
export interface ListPayload<T> {
  list?: T[];
  total?: number;
}

/** 风控族列表响应形状：{items, total?}（见 pagedItems） */
export interface ItemsPayload<T> {
  items?: T[];
  total?: number;
}

export function buildRow(id: string, title: string, subtitle: string, badge: string,
  badgeOk: boolean, status: number, values: FormValues): ResourceRow {
  const row: ResourceRow = {
    id: id,
    title: title,
    subtitle: subtitle,
    badge: badge,
    badgeOk: badgeOk,
    status: status,
    values: values
  };
  return row;
}

/** 只认「整表返回」的列表（{list} 无 total）：total 用行数，分页条不画 */
export function unpaged<T>(data: Object, toRow: (item: T) => ResourceRow): ResourceListResult {
  const payload = data as ListPayload<T>;
  const list = payload.list ?? [];
  const rows: ResourceRow[] = [];
  for (let i = 0; i < list.length; i++) {
    rows.push(toRow(list[i]));
  }
  const result: ResourceListResult = { rows: rows, total: rows.length };
  return result;
}

/** 分页列表：total 缺失时退化为行数（分页条仍画，只是页数按当前页算） */
export function paged<T>(data: Object, toRow: (item: T) => ResourceRow): ResourceListResult {
  const payload = data as ListPayload<T>;
  const list = payload.list ?? [];
  const rows: ResourceRow[] = [];
  for (let i = 0; i < list.length; i++) {
    rows.push(toRow(list[i]));
  }
  const result: ResourceListResult = { rows: rows, total: payload.total ?? rows.length };
  return result;
}

/**
 * 分页列表的**风控族形状**：`{total, items}` —— 与 paged 的 `{list}` 不是同一套。
 * 风控六个列表端点（rule/event/device/ip/clusters/anticheat）都是 `items`，且分页参数是 `size` 不是 `limit`。
 */
export function pagedItems<T>(data: Object, toRow: (item: T) => ResourceRow): ResourceListResult {
  const payload = data as ItemsPayload<T>;
  const items = payload.items ?? [];
  const rows: ResourceRow[] = [];
  for (let i = 0; i < items.length; i++) {
    rows.push(toRow(items[i]));
  }
  const result: ResourceListResult = { rows: rows, total: payload.total ?? rows.length };
  return result;
}

/** JSON 列回填成表单文本：null/缺省给空串（空串提交 = 用后端默认配置，不是「清空」） */
export function jsonText(value: Object | null | undefined): string {
  if (value === null || value === undefined) {
    return '';
  }
  return JSON.stringify(value);
}
