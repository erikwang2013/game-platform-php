/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { apiService } from './ApiService';
import { FormPayload, ToggleRequest, ResourceListResult, ApiResponse } from '../model/DataModels';
import { ResourceModule, ExtraResult } from '../common/ResourceSchema';

/**
 * 资源模块的通用读写服务（字段与路径描述见 common/ResourceModules.ets）。
 * 一处实现 18 个模块的增删改 + 状态变更 + 行内额外动作，页面里没有模块名分支。
 *
 * 从 DataService.ets 搬出来单独成文件：那份已 498 行，本批要加的 toggleBare 会把它顶过 500 行
 * （项目规矩），而 ResourceService 与认证/仪表盘/用户那几个服务本就没有耦合。**纯搬移，无行为改动**，
 * 只多了一个 toggleBare。`ExtraResult` 仍从 ResourceSchema 取（类型定义在那里）。
 */

/**
 * extra 的 no-op 回执里 data 的占位：ArkTS 不给 `Object` 字段接裸 `{}` 字面量
 * （arkts-no-untyped-obj-literals），而 `FormPayload` 这种 Record 别名接空字面量是树里已验证合法的。
 */
const NO_EXTRA_DATA: FormPayload = {};

/** 无体 toggle 的回执：控制器回 `{status: 0|1}`（字段缺省 -1 = 服务端没给，调用方别据此改本地状态） */
interface ToggleBareResult {
  status?: number;
}

export class ResourceService {
  /** 列表：响应形状差异由 m.parseList 收口，故泛型给 Object */
  static async list(m: ResourceModule, page: number, limit: number, gameId: string): Promise<ResourceListResult> {
    const response = await apiService.get<Object>(m.listPath(page, limit, gameId));
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return m.parseList(response.data);
  }

  static async create(m: ResourceModule, data: FormPayload): Promise<void> {
    // 系统配置的建没有 /create 段（模块描述里覆盖）
    const path = m.createPath ?? `${m.base}/create`;
    const response = await apiService.post<void>(path, data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /** 局部更新：body 只放表单管的字段（validator 多为 sometimes，未传字段后端不动） */
  static async update(m: ResourceModule, id: string, data: FormPayload): Promise<void> {
    const response = await apiService.put<void>(`${m.base}/${id}`, data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /**
   * 删除。系统配置必须带管理员密码（控制器 confirmPassword 会 422）；
   * 其余模块传空对象 —— extraData 是 '{}'，等于没带 body。
   */
  static async remove(m: ResourceModule, id: string, password: string): Promise<void> {
    const body: FormPayload = {};
    if (m.deletePassword) {
      body.password = password;
    }
    const response = await apiService.delete<void>(`${m.base}/${id}`, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /** 状态切换走独立端点（body 里 id 是 hashid），与 update 传 status 不是同一条路 */
  static async toggle(path: string, id: string, status: number): Promise<void> {
    const body: ToggleRequest = { id: id, status: status };
    const response = await apiService.post<void>(path, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /**
   * 无体 toggle（statusMode='toggle_bare'）：id 在**路径**里、**不带任何 body**，
   * 服务端自己读当前值翻转（风控规则的 toggle 就是这个形状，见 RiskRuleController::toggle）。
   * 返回服务端回执里的 status（目标态的真值）；回执没给就返回 -1，由调用方决定怎么提示。
   */
  static async toggleBare(path: string, id: string): Promise<number> {
    const response = await apiService.post<ToggleBareResult>(path.replace('{id}', id));
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data.status ?? -1;
  }

  /**
   * 行内额外动作（排行榜刷新缓存 / 优惠券统计 / CDN 连通测试）；模块没配 extraPath 时是 no-op。
   * 三条路的差异都在模块描述里（extraMethod / extraBody），这里只做分派：
   * 统计是 GET、连通测试的 id 在 body 里、刷新是 POST 无 body。
   * 返回服务端 message（调用方按原文提示，不替服务端编成功文案）。
   */
  static async extra(m: ResourceModule, id: string): Promise<ExtraResult> {
    const path = m.extraPath;
    if (path === undefined) {
      const nothing: ExtraResult = { message: '', data: NO_EXTRA_DATA };
      return nothing;
    }
    // extraBody 收的是**行 id**（不是拼好的路径）：CDN 连通测试把 id 放 body 里
    const response = await ResourceService.extraRequest(m, path(id), id);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    const result: ExtraResult = { message: response.message, data: response.data };
    return result;
  }

  private static extraRequest(m: ResourceModule, path: string, id: string): Promise<ApiResponse<Object>> {
    if (m.extraMethod === 'GET') {
      return apiService.get<Object>(path);
    }
    if (m.extraBody !== undefined) {
      return apiService.post<Object>(path, m.extraBody(id));
    }
    return apiService.post<Object>(path);
  }
}
