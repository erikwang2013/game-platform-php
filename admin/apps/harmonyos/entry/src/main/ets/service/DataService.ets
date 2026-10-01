/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { apiService } from './ApiService';
import {
  LoginRequest,
  LoginResponse,
  DashboardData,
  User,
  UserListResult,
  PageParams,
  PageResult,
  FormPayload,
  ToggleRequest,
  GameItem,
  AnnouncementItem,
  ReportSummary,
  IdentityItem,
  TicketItem,
  PermissionNode,
  RoleItem,
  WithdrawOrderItem,
  WithdrawSwitchState,
  pageQuery
} from '../model/DataModels';

/**
 * 认证服务
 */
export class AuthService {
  static async login(username: string, password: string): Promise<LoginResponse> {
    const body: LoginRequest = { username, password };
    const response = await apiService.post<LoginResponse>('/api/v1/auth/login', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async refreshToken(refreshToken: string): Promise<LoginResponse> {
    // 字面量实参在 ArkTS 非法（apiService 的第二参类型是 object，不是声明的接口）⇒ 先落成变量再传
    const body: FormPayload = {};
    body.refresh_token = refreshToken;
    const response = await apiService.post<LoginResponse>('/api/v1/auth/refresh', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}

/**
 * 仪表盘服务
 */
export class DashboardService {
  static async getDashboard(): Promise<DashboardData> {
    const response = await apiService.get<DashboardData>('/admin/v1/dashboard');
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}

/**
 * 用户管理服务
 */
/**
 * 新建管理员的请求体。原写法 `Partial<User> & { password: string }` 两条都犯：交叉类型、
 * 内联对象字面量当类型 —— 连带把调用点的字面量实参也拖成 untyped-obj-literals。
 * 字段与校验规则见 `UserController::store`（username/password/real_name 必填，status 缺省 1）。
 * 只有 `UserService.create` 一个消费者，故就近放这里而不塞进已超 500 行的 model/DataModels.ets。
 */
export interface CreateUserRequest {
  username: string;
  password: string;
  real_name: string;
  phone: string;
  email: string;
  status: number;
}

export class UserService {
  // 位置参数而非参数对象（同 GameService.getList）：交叉类型、解构参数、
  // `Object.entries(...).filter(([_, v]) => …)` 里的解构形参，ArkTS 一样都不收
  static async getList(page: number, limit: number, keyword: string): Promise<UserListResult> {
    let path = `/admin/v1/user?${pageQuery(page, limit)}`;
    if (keyword.length > 0) {
      path += `&keyword=${encodeURIComponent(keyword)}`;
    }
    const response = await apiService.get<UserListResult>(path);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async getDetail(id: string): Promise<User> {
    const response = await apiService.get<User>(`/admin/v1/user/${id}`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async create(data: CreateUserRequest): Promise<void> {
    const response = await apiService.post<User>('/admin/v1/user', data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  static async update(id: string, data: Partial<User>): Promise<void> {
    const response = await apiService.put<User>(`/admin/v1/user/${id}`, data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /**
   * 删除。**body 必须带当前操作者自己的密码**（`UserController::destroy` → `confirmPassword`，
   * 空密码直接 422「This sensitive operation requires password confirmation」）——
   * 不带密码的删除从来就没成功过。
   */
  static async delete(id: string, password: string): Promise<void> {
    const body: FormPayload = {};
    body.password = password;
    const response = await apiService.delete<void>(`/admin/v1/user/${id}`, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /**
   * 重置密码：走 update 端点，但**两个字段名不同、不能对调**——
   * `password` 是被改用户的**新密码**，`admin_password` 是**操作者自己的密码**（`update:194` 的
   * confirmPassword 读的是后者）。新密码规则：8-32 位且同时含大小写字母与数字。
   */
  static async resetPassword(id: string, newPassword: string, adminPassword: string): Promise<void> {
    const body: FormPayload = {};
    body.password = newPassword;
    body.admin_password = adminPassword;
    const response = await apiService.put<void>(`/admin/v1/user/${id}`, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /**
   * 分配角色：**全量替换**（后端 `roles()->sync()`）。传空数组 = 取消全部角色，
   * 且空数组**必须照发**：后端以 `$request->has('role_ids')` 判是否处理，urlencoded 下
   * `role_ids=[]` 整个键会消失、静默不生效；本树 ApiService 走 JSON body，空数组能原样送到。
   */
  static async assignRoles(id: string, roleIds: string[]): Promise<void> {
    const body: FormPayload = {};
    body.role_ids = roleIds;
    const response = await apiService.put<void>(`/admin/v1/user/${id}`, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /** 角色候选（`GET /admin/v1/role` 回 {list,total}，行里的 id 是 hashid，直接当提交值用） */
  static async getRoles(): Promise<RoleItem[]> {
    const response = await apiService.get<PageResult<RoleItem>>(`/admin/v1/role?${pageQuery(1, 100)}`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data.list;
  }

  static async batchStatus(ids: string[], status: number): Promise<void> {
    const body: FormPayload = {};
    body.ids = ids;
    body.status = status;
    const response = await apiService.post<void>('/admin/v1/user/batch/status', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }
}

/**
 * 游戏管理服务（/admin/v1/game/*）
 * 列表 id 与 {hashid} 路径、body 里的 id 一律是 hashid，直接用列表返回值即可
 */
export class GameService {
  // 位置参数而非参数对象：ArkTS 不接受交叉类型/解构参数/对象字面量实参，逐项拼 query 最少踩坑
  static async getList(page: number, limit: number, keyword: string): Promise<PageResult<GameItem>> {
    let path = `/admin/v1/game/list?${pageQuery(page, limit)}`;
    if (keyword.length > 0) {
      path += `&keyword=${encodeURIComponent(keyword)}`;
    }
    const response = await apiService.get<PageResult<GameItem>>(path);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async create(data: FormPayload): Promise<void> {
    const response = await apiService.post<void>('/admin/v1/game/create', data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /** 局部更新：body 只放表单管的字段，未传字段后端不动（validator 是 sometimes） */
  static async update(id: string, data: FormPayload): Promise<void> {
    const response = await apiService.put<void>(`/admin/v1/game/${id}`, data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  static async remove(id: string): Promise<void> {
    const response = await apiService.delete<void>(`/admin/v1/game/${id}`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }
}

/**
 * 公告管理服务（/admin/v1/announcement/*）
 */
export class AnnouncementService {
  static async getList(params: PageParams): Promise<PageResult<AnnouncementItem>> {
    const response = await apiService.get<PageResult<AnnouncementItem>>(
      `/admin/v1/announcement/list?${pageQuery(params.page, params.limit)}`
    );
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  static async create(data: FormPayload): Promise<void> {
    const response = await apiService.post<void>('/admin/v1/announcement/create', data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  static async update(id: string, data: FormPayload): Promise<void> {
    const response = await apiService.put<void>(`/admin/v1/announcement/${id}`, data);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  static async remove(id: string): Promise<void> {
    const response = await apiService.delete<void>(`/admin/v1/announcement/${id}`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /** 状态切换走独立端点（body 里 id 是 hashid），与 update 传 status 不是同一条路 */
  static async toggle(id: string, status: number): Promise<void> {
    const body: ToggleRequest = { id, status };
    const response = await apiService.post<void>('/admin/v1/announcement/toggle', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }
}

/**
 * 权限树服务（GET /admin/v1/permission）—— 角色的权限多选要拿这份候选，而角色列表端点不给树，
 * 故单独要一次。整表返回（分页参数后端不认），故没有 page/limit。
 */
export class PermissionService {
  static async tree(): Promise<PermissionNode[]> {
    const response = await apiService.get<PermissionNode[]>('/admin/v1/permission');
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}

/**
 * 身份认证（KYC）审核服务（/admin/v1/identity/*）。
 * 只有两个端点：列表 + 审核（审核是**行动作**，不是 PUT {status} 那类局部更新）。
 */
export class IdentityService {
  static async getList(page: number, limit: number): Promise<PageResult<IdentityItem>> {
    const response = await apiService.get<PageResult<IdentityItem>>(
      `/admin/v1/identity/list?${pageQuery(page, limit)}`
    );
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  /** 审核：id 在 **body** 里（不是路径段），action 只认 approve / reject，note ≤500 */
  static async review(id: string, action: string, note: string): Promise<void> {
    const body: FormPayload = {};
    body.id = id;
    body.action = action;
    body.note = note;
    const response = await apiService.put<void>('/admin/v1/identity/review', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }
}

/**
 * 工单服务（/admin/v1/ticket/*）—— 三个动作端点，没有 CRUD。
 */
export class TicketService {
  static async getList(page: number, limit: number): Promise<PageResult<TicketItem>> {
    const response = await apiService.get<PageResult<TicketItem>>(
      `/admin/v1/ticket/list?${pageQuery(page, limit)}`
    );
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  /** 回复：闭单后服务端 422（Ticket is closed），页面按 status 把按钮藏掉 */
  static async reply(id: string, content: string): Promise<void> {
    const body: FormPayload = {};
    body.content = content;
    const response = await apiService.post<void>(`/admin/v1/ticket/${id}/reply`, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /**
   * 指派：控制器是 `(int) $request->input('admin_id')` —— 收的是**管理员裸数字 ID**，不是 hashid。
   * 这里原样按字符串送出、不经过 Number()：19 位雪花 ID 转 JS number 会掉精度，
   * 掉精度后写进去的是另一个（不存在的）管理员，是静默写错；页面对该入参另有纯数字校验。
   */
  static async assign(id: string, adminId: string): Promise<void> {
    const body: FormPayload = {};
    body.admin_id = adminId;
    const response = await apiService.post<void>(`/admin/v1/ticket/${id}/assign`, body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }

  /** 关闭：置 status=closed + resolved_at（无 body；重复闭单不会报错） */
  static async close(id: string): Promise<void> {
    const response = await apiService.post<void>(`/admin/v1/ticket/${id}/close`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
  }
}

/**
 * 提现服务（/admin/v1/withdraw/*）—— 列表 + 开关 + 四种**资金动作**。
 *
 * 资金动作的方法一律**返回服务端 message**（'审核通过' / '已驳回并退款' / '打款成功' / …），
 * 由页面原样提示并**重新拉列表**（服务端才是状态真值，页面不做乐观更新）。
 * 返回类型是 string 而不是 void，就是为了让调用点非读 message 不可 —— 想让调用方自己编一句
 * 成功文案时，它得先把这个返回值丢掉。
 */
export class WithdrawService {
  /** 列表：status 传空串 = 不过滤（控制器 status 为空时不加 where） */
  static async getOrders(page: number, limit: number, status: string): Promise<PageResult<WithdrawOrderItem>> {
    let path = `/admin/v1/withdraw/orders?${pageQuery(page, limit)}`;
    if (status.length > 0) {
      path += `&status=${encodeURIComponent(status)}`;
    }
    const response = await apiService.get<PageResult<WithdrawOrderItem>>(path);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }

  /**
   * 审核：order_id 是 **hashid**，action 只认 approve / reject / confirm（validator 的 in:）。
   * confirm 是双重审核的第二票（后台未开启双审时会被 422「双重审核未启用」）。
   */
  static async review(orderId: string, action: string, note: string): Promise<string> {
    const body: FormPayload = {};
    body.order_id = orderId;
    body.action = action;
    body.note = note;
    const response = await apiService.put<Object>('/admin/v1/withdraw/review', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.message;
  }

  /** 批量审核：ids 是 hashid 数组（action 只认 approve / reject，**没有 confirm**） */
  static async batchReview(ids: string[], action: string, note: string): Promise<string> {
    const body: FormPayload = {};
    body.ids = ids;
    body.action = action;
    body.note = note;
    const response = await apiService.post<Object>('/admin/v1/withdraw/batch-review', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.message;
  }

  /** 执行打款：order_id 在 body 里。不可逆的外部副作用 ⇒ 页面必须先二次确认 */
  static async executePayout(orderId: string): Promise<string> {
    const body: FormPayload = {};
    body.order_id = orderId;
    const response = await apiService.post<Object>('/admin/v1/withdraw/execute-payout', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.message;
  }

  /** 同步打款状态：从 PayPal 查批次并落库。order_id 在 body 里 */
  static async syncPayout(orderId: string): Promise<string> {
    const body: FormPayload = {};
    body.order_id = orderId;
    const response = await apiService.post<Object>('/admin/v1/withdraw/sync-payout', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.message;
  }

  /** 全局开关读数：GET 与 PUT 共用 toggleSwitch，GET 分支回 switchState() */
  static async getSwitch(): Promise<boolean> {
    const response = await apiService.get<WithdrawSwitchState>('/admin/v1/withdraw/switch');
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return WithdrawService.enabledOf(response.data);
  }

  /** 开关写入：enabled 只认 0/1（validator `in:0,1`），返回写入后的真实状态 */
  static async setSwitch(enabled: boolean): Promise<boolean> {
    const body: FormPayload = {};
    body.enabled = enabled ? 1 : 0;
    const response = await apiService.put<WithdrawSwitchState>('/admin/v1/withdraw/switch', body);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return WithdrawService.enabledOf(response.data);
  }

  /** 三个键是同一个布尔值（global_switch/enabled/status），任一缺失就按 false 收 */
  private static enabledOf(state: WithdrawSwitchState): boolean {
    return state.enabled === true || state.global_switch === true;
  }
}

/**
 * 报表服务
 */
export class ReportService {
  static async getSummary(start: string, end: string): Promise<ReportSummary> {
    const response = await apiService.get<ReportSummary>(`/admin/v1/report/summary?start=${start}&end=${end}`);
    if (response.code !== 0) {
      throw new Error(response.message);
    }
    return response.data;
  }
}
