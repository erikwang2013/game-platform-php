/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * `Api` 的**扩展域**方法块：优惠券 / 全局搜索 / 赛事 / 分享（社交与聊天后续也加这里）。
 *
 * 为什么又拆一个文件：`api.service.ts` 顶到 500 行上限（仓库硬约束）而它还要继续长。
 * 手法与拆 `api.base.ts` 时**完全一样** —— 插一层中间基类：
 * `Api extends ApiDomains extends ApiBase`。域方法搬进来后照旧用 `this.request()`，
 * **20 多个页面的调用点一行都不用改**（拆成第二个可注入服务的收益一样，但要改所有调用点）。
 *
 * 新域一律加在这个文件；`api.service.ts` 只留公开接口 + 会话/钱包/游戏等既有块。
 *
 * 本文件的响应类型**就近声明**（不走 api.types.ts 的"契约类型集中"）：那个文件也已经
 * 贴着 500 行了，域类型跟着域方法走更好读。页面照旧从 `'../core/api.service'` 导入
 * —— api.service.ts 里 `export * from './api.domains'` 把它们透出来。
 */
import { Observable } from 'rxjs';
import type { SearchResult } from './api.types';
import { ApiBase, BASE } from './api.base';

/* ---------------- 赛事（需登录；FeatureFlag: tournament） ---------------- */

export interface TournamentGame {
  id: string;
  name: string;
}

/** `/tournament/list` 的一条。`prize_pool` / `entry_fee` 是模型 cast 成 string 的金额（bcmath 纪律）。 */
export interface Tournament {
  id: string;
  name: string;
  slug: string;
  type: string;
  description: string | null;
  game: TournamentGame | null;
  prize_pool: string;
  entry_fee: string;
  player_count: number;
  max_players: number;
  start_at: string;
  end_at: string;
}

/** 详情页排行榜的一行。`user` 服务端已经在算不出昵称时兜成字符串 `'N/A'`。 */
export interface TournamentRankRow {
  rank: number | null;
  user: string;
  score: string;
}

export interface MyTournamentEntry {
  id: string;
  score: string;
  rank: number;
}

export interface TournamentDetail extends Tournament {
  /** 没报名就是 null（服务端按 `user_id` 查的当人那一条） */
  my_entry: MyTournamentEntry | null;
  /** 服务端取的是 score desc 的前 100 条 */
  leaderboard: TournamentRankRow[];
}

/** `/tournament/list` 的 status 取值。**服务端只认这三个**，其它值等于不过滤（会连下架的一起返回）。 */
export type TournamentStatus = 'active' | 'upcoming' | 'ended';

/** `/tournament/list` 的分页回包（**有** `last_page`，与 `/search` 不同）。 */
export interface PagedTournaments {
  items: Tournament[];
  total: number;
  page: number;
  last_page: number;
}

/* ---------------- 分享短码 ---------------- */

export interface ShareCreated {
  short_code: string;
  expires_at: string | null;
}

/* ---------------- 好友 ---------------- */

/** 用户摘要。`/friend/*` 与 `/chat/*` 共用一个形状（服务端各处都只 encode id/username/nickname/avatar）。 */
export interface FriendUser {
  id: string;
  username: string;
  nickname: string | null;
  avatar: string | null;
}

/**
 * `/friend/requests` 的一条。⚠ **`id` 是好友关系（`friend` 表那行）的 hashid，不是用户 id** ——
 * accept/reject 要的是它；而 `user.id` 才是发起人的用户 hashid（`/friend/remove` 要的是后者）。
 * 两个 id 混用必 404：服务端按 `id = request_id` **且 `friend_id = 我`** 且 `status='pending'` 查。
 */
export interface FriendRequest {
  id: string;
  user: FriendUser;
  created_at: string;
}

/* ---------------- 聊天 ---------------- */

export interface Conversation {
  peer: FriendUser;
  /** 服务端已 `mb_substr` 截到 100 字，不是完整正文 */
  last_message: string;
  unread_count: number;
  updated_at: string;
}

export interface ChatMessage {
  id: string;
  from_user_id: string;
  to_user_id: string;
  content: string;
  /** 0/1。⚠ `/chat/messages` 里**刚被这次请求标成已读的那批仍然是 0**，见域方法注释 */
  is_read: number;
  created_at: string;
}

/** `/chat/messages/{peer}` 的分页回包。⚠ 键是 **`items`**（不是 `list`），且**没有 `per_page`**。 */
export interface ChatMessagesPage {
  items: ChatMessage[];
  total: number;
  page: number;
  last_page: number;
}

/* ---------------- 导出我的数据（GDPR 数据可携带） ---------------- */

export interface ExportProfile {
  username: string;
  nickname: string | null;
  email: string | null;
  phone: string | null;
  country: string | null;
  language: string | null;
  created_at: string | null;
}

export interface ExportWallet {
  /** 三个都是字符串金额（模型上是 decimal cast）—— **不要 parseFloat** */
  balance: string;
  total_earned: string;
  total_spent: string;
}

export interface ExportOAuthAccount {
  provider: string;
  created_at: string | null;
}

/**
 * `GET /user/export-data` 的响应形状。
 *
 * 四类明细是控制器 `UserController::exportData` 里 `->toArray()` 的**原样行**（模型全列），
 * 所以这里只写 `Record<string, unknown>[]` —— 页面**不解读列名**，只计数并原样落盘。
 * 编一份列名清单只会与真表悄悄漂开（列变了没人会想起来改这里）。
 */
export interface ExportData {
  profile: ExportProfile;
  /** 无钱包行时服务端回 `null`（`$user->wallet ? … : null`）⇒ 页面要显示「—」而不是崩 */
  wallet: ExportWallet | null;
  /** ⚠ 以下四类**各最多 100 条**（控制器逐个 `->limit(100)`），不是全量 —— 页面上必须写明 */
  transactions: Record<string, unknown>[];
  exchange_records: Record<string, unknown>[];
  deposit_orders: Record<string, unknown>[];
  withdraw_orders: Record<string, unknown>[];
  oauth_accounts: ExportOAuthAccount[];
  /** 服务端墙钟串 `Y-m-d H:i:s`，落盘文件名的唯一来源 */
  exported_at: string;
}

/* ---------------- 国家/地区（公开） ---------------- */

/**
 * `GET /country/list` 的一项。服务端只映射这三个字段、**没有国家名**
 * （`CountryController::list:27-33`），所以 KYC 下拉的文案只能用 `country_code` 本身。
 * `min_deposit` 是 `DECIMAL(18,4)` 且模型 cast string 的原样值；本页不展示它，
 * 但回包就长这样，别按「只用到 country_code」把形状裁掉。
 */
export interface CountryOption {
  country_code: string;
  currency: string;
  min_deposit: string;
}

export abstract class ApiDomains extends ApiBase {
  /* ==================== 优惠券 ==================== */

  /* 优惠券三个端点（/coupon/available|claim|my）2026-10-01 撤下：端点本身跑得通
     （领券还强制点击验证码），但 **券可领不可核销** —— `game_user_coupon` 的
     `used_in_order` / status='used' 全仓没有写入方 ⇒ 用户永远兑不掉，页面只能提供假价值。
     「没有消费者的接线一律删，不留在树里当死码」。恢复条件：先有一条把券核销掉的使用路径
     （下单/兑换时置 used）。 */

  /* 推荐码（/referral/*）2026-10-01 一并撤下：整条链**无 bootstrap 写入路径** ——
     `ReferralController::apply()` 先按提交的码查一行、查不到就 404，**之后才创建**
     ⇒ 第一行永远建不出来；`my-code` 读 `referrer_id = 我` 同样恒空。零种子、零迁移写入、
     admin 侧零引用。连带后果：`apply()` 里派发的 `referral.applied` 事件永远跑不到，
     故 clickhouse.sql 里靠它计数的成就 `invite_1` / `invite_10` 也一并不可达。
     恢复条件：先有一条「注册时给每个用户签发推荐码」的写入路径。 */

  /* ==================== 全局搜索 ==================== */

  /**
   * 全局搜索（**只搜游戏**），公开端点，未登录可用。
   *
   * ⚠ **别传 `type=user`**：该端点在公开组（`route.php:66`，无 UserAuth），而 user 分支会把
   * 用户行原样吐出去（`email`/`phone` 随行返回）⇒ 那是未鉴权的用户批量导出。
   * 控制器入口已把 `$type` 强制成 `'game'`（`SearchController::search`），传什么都进不来
   * —— 这是**故意的**，客户端这边也别去试。
   *
   * 与 `/game/list?keyword=` 的区别：这里匹配 name **或 description**，那边只匹配 name。
   */
  searchGames(q: string, page = 1, perPage = 20): Observable<SearchResult> {
    return this.request<SearchResult>('GET', `${BASE}/search`, { q, page, per_page: perPage });
  }

  /* ==================== 赛事 ==================== */

  /**
   * 赛事列表。**FeatureFlag 关掉时服务端回 code=503**（不是 HTTP 503 —— 信封里，
   * message 是「Tournaments not available」，由页面原样透出）。
   * 注意 `detail()` **不查这个开关**，所以别拿它当"赛事功能开着"的证据。
   */
  tournaments(status: TournamentStatus = 'active', page = 1, perPage = 20): Observable<PagedTournaments> {
    return this.request<PagedTournaments>('GET', `${BASE}/tournament/list`, {
      status,
      page,
      per_page: perPage,
    });
  }

  /** 赛事详情：当人的报名 + 前 100 名排行榜。**不带 FeatureFlag 判断**（同 list 的注释）。 */
  tournamentDetail(hashid: string): Observable<TournamentDetail> {
    return this.request<TournamentDetail>(
      'GET',
      `${BASE}/tournament/${encodeURIComponent(hashid)}`,
    );
  }

  /**
   * 报名。服务端有三道闸：赛事不存在/未启用 → 404；**已开赛**（`start_at <= now`）→ 400；
   * 已报名 → 422；满员 → 400。也就是说**只有还没开赛且 status=1 的赛事报得进去**，
   * 页面据此决定要不要摆报名按钮（见 tournaments.ts 的 canJoin）。
   */
  tournamentJoin(hashid: string): Observable<MyTournamentEntry> {
    return this.request<MyTournamentEntry>(
      'POST',
      `${BASE}/tournament/${encodeURIComponent(hashid)}/join`,
    );
  }

  /* ==================== 分享短码 ==================== */

  /**
   * 生成分享短码（**需登录**）。拼成 `${location.origin}/login?code={code}` 就是邀请链接
   * —— 落地页由**本树的登录页**充当（读 `?code=` 预填注册、并上报一次点击），
   * 后端没有、也不需要单独的落地路由。链接的拼法见 pages/invite.ts。
   */
  shareCreate(activityId?: string): Observable<ShareCreated> {
    return this.request<ShareCreated>(
      'POST',
      `${BASE}/shares`,
      undefined,
      activityId ? { activity_id: activityId } : {},
    );
  }

  /**
   * 落地页点击上报（**公开**，匿名可访问，`route.php:72`）。
   * 服务端原子 `clicks+1` 且**不回分享者任何信息**；短码无效/过期 → 404。
   * 回包 data 是空数组，没有可用的内容。
   */
  shareVisit(shortCode: string): Observable<unknown[]> {
    return this.request<unknown[]>('POST', `${BASE}/shares/visit`, undefined, {
      short_code: shortCode,
    });
  }

  /* ==================== 好友 ==================== */

  /**
   * 好友列表。服务端双向查（`user_id=我` 或 `friend_id=我`）后**取对方那一侧**，
   * 所以元素就是对方本人的摘要，不需要客户端再按 id 挑一遍。
   */
  friends(): Observable<{ list: FriendUser[] }> {
    return this.request<{ list: FriendUser[] }>('GET', `${BASE}/friend/list`);
  }

  /**
   * **收到**的待处理申请（`friend_id = 我` 且 status='pending'）。
   * ⚠ 没有「我发出的申请」端点 ⇒ 申请发出去之后在本树里**查不回来**（只有对方接受/拒绝才会变化）。
   * 所以「已发送」这类反馈只能是本次会话的即时提示，不要落成缓存（缓存＝第二真值源）。
   */
  friendRequests(): Observable<{ list: FriendRequest[] }> {
    return this.request<{ list: FriendRequest[] }>('GET', `${BASE}/friend/requests`);
  }

  /**
   * 发起好友申请。`friendId` 是**对方用户**的 hashid。
   *
   * 三道闸（`FriendController::request`）：自己/解不出的 hashid → 422 'Invalid friend'；
   * 查无此人 → 404；**任一方向已有记录**（含对方先申请、仍 pending）→ 422
   * 'Already friends or request pending'。
   *
   * 第三条就是「**互相加不自动接受**」：A 申请 B 之后，B 再申请 A 会被这条挡下，
   * B 只能去「申请」列表里**接受** A 的那条。客户端不要替服务端"聪明"地把它变成接受。
   */
  friendRequest(friendId: string): Observable<{ id: string }> {
    return this.request<{ id: string }>('POST', `${BASE}/friend/request`, undefined, {
      friend_id: friendId,
    });
  }

  /**
   * 接受申请。`requestId` 是**关系** hashid（`/friend/requests` 的 `id`），**不是用户 id**。
   * 服务端要求 `friend_id = 我` 且 status='pending' ⇒ **只有收件人接受得了**，别人拿这条 id 也是 404
   * （回的是 'Request not found'，与「不存在」同码，别指望从这里分辨「不是我的申请」）。
   */
  friendAccept(requestId: string): Observable<unknown[]> {
    return this.request<unknown[]>('POST', `${BASE}/friend/accept`, undefined, {
      request_id: requestId,
    });
  }

  /**
   * 拒绝申请。服务端是**硬删**（`$f->delete()`，不是置成 rejected）⇒ 拒完这行就没了，
   * 对方**可以立刻再发一次**。别在页面上承诺"已拉黑/不会再收到"。
   */
  friendReject(requestId: string): Observable<unknown[]> {
    return this.request<unknown[]>('POST', `${BASE}/friend/reject`, undefined, {
      request_id: requestId,
    });
  }

  /**
   * 删好友。`friendId` 是**对方用户**的 hashid（不是关系 id）。
   * **幂等且静默**：按双向 + status='accepted' 删，**一行都没删到也照样回成功**
   * ⇒ 客户端不该自己先判"他是不是我好友"，也别把「本来就没有」当失败。
   */
  friendRemove(friendId: string): Observable<unknown[]> {
    return this.request<unknown[]>('POST', `${BASE}/friend/remove`, undefined, {
      friend_id: friendId,
    });
  }

  /**
   * 按用户名/昵称搜人（`like %q%`，`limit 20`，**不分页**，排除自己与非 status=1）。
   * `q` 为空/全空白时服务端直接回空列表（客户端也据此不发请求）。
   * 回包**不含「是不是已经好友/有没有待处理申请」** ⇒ 页面得自己拿好友列表比对，
   * 比对不到的（例如已发过申请仍 pending）点了会吃到 422，原样透出即可。
   */
  friendSearch(q: string): Observable<{ list: FriendUser[] }> {
    return this.request<{ list: FriendUser[] }>('GET', `${BASE}/friend/search`, { q });
  }

  /* ==================== 聊天 ==================== */

  /**
   * 会话列表。**没有独立的会话表**：服务端把「我发出的」与「我收到的」两组合并、各取每组最大 id
   * 再回表查最后一条，所以**只有真正有消息往来的人才会出现**（是好友但没聊过 = 不在列表里）。
   *
   * 服务端**有** WS：`ChatController::send` 把帧 `lpush` 进 `chat:delivery_queue`（`ChatController.php:170`），
   * 由 `app/process/ChatWebSocket.php` 的定时器 brpop 后投给该用户的在线连接。
   * （曾同时 `publish` 到 `chat:channel`，那条旁路全仓零订阅者、每条私信白付一次 Redis 往返，已删。）
   * **没有 WS 客户端的是本树**（C 端 angular）⇒ 新消息不会自己出现，
   * 页面顶部如实写「新消息到达后刷新」。（接 WS 客户端是单独立项，本次不做。）
   */
  conversations(): Observable<{ list: Conversation[] }> {
    return this.request<{ list: Conversation[] }>('GET', `${BASE}/chat/conversations`);
  }

  /**
   * 与某人的聊天记录。`peerHashid` 是**对方用户**的 hashid；items 已由服务端 `array_reverse`
   * 成时间正序（旧→新）。
   *
   * ⚠⚠ **这是个有副作用的 GET**，两件事一起记：
   *  1. 服务端在**拼完 items 之后**才把「对方发给我的未读」全置 1（`ChatController.php:98-100`）
   *     ⇒ **打开会话本身就完成了已读回执**，不需要也不该再调 `/chat/read`（那个端点本树刻意不接）。
   *  2. 正因为它排在 map **之后**，**同一次响应里刚被标成已读的那批 `is_read` 仍然是 0**
   *     ⇒ 别把首屏那批 0 当成"未读"渲染成未读标记（会自己骗自己），要再进一次才是新值。
   *     这也是本树**不渲染已读状态**的原因：唯一能拿到的快照天生是旧的。
   */
  chatMessages(peerHashid: string, page = 1): Observable<ChatMessagesPage> {
    return this.request<ChatMessagesPage>(
      'GET',
      `${BASE}/chat/messages/${encodeURIComponent(peerHashid)}`,
      { page },
    );
  }

  /**
   * 发消息。**只有好友能发** —— 非好友服务端回 **403 'Only friends can send messages'**，
   * 页面原样透出（别吞成「发送失败」，那会让用户不知道要先加好友）。
   * 自己/非法收件人 → 422；内容空或 >5000 字 → 422。
   *
   * 回包**只有 id/created_at，没有正文** ⇒ 想让刚发的那条出现在列表里必须重拉 messages，
   * 客户端自己 push 一条就是第二真值源（id 与时间都是服务端生成的）。
   */
  sendChat(toUserId: string, content: string): Observable<{ id: string; created_at: string }> {
    return this.request<{ id: string; created_at: string }>('POST', `${BASE}/chat/send`, undefined, {
      to_user_id: toUserId,
      content,
    });
  }

  /* ==================== 导出我的数据 ==================== */

  /**
   * 导出我的数据（GDPR 数据可携带）。**成功也回普通信封**（`UserController::exportData` 的
   * `$this->success($data, 'Data export ready')`，不是 `response()->download()`）⇒ 别照搬
   * admin 树 `lib/download.ts` 那条「按 content-type 分流附件」的链路，那会给信封凭空加一条分支。
   * 取到 JSON 后由 `core/export-data.ts` 自己捏 Blob 落盘。
   *
   * 四类明细各**最多 100 条**（控制器逐个 `->limit(100)`）—— 页面上的计数与说明都要带上这句，
   * 否则用户会以为这份文件是全部历史。
   */
  exportData(): Observable<ExportData> {
    return this.request<ExportData>('GET', `${BASE}/user/export-data`);
  }

  /* ==================== 国家/地区（公开） ==================== */

  /**
   * 国家/地区选项。**公开端点**（`route.php` 的「公开接口」组，无鉴权中间件），只列 `status=1`
   * 的国家。⚠ 回包**没有国家名**（见 `CountryOption`）⇒ 下拉文案只能用代码本身。
   * KYC 的 `country` 在服务端是 `nullable|string|max:50`、**不校验在册**（`IdentityController:61`），
   * 所以历史值可能不在本列表里 —— 补一条的逻辑在 `kyc.ts` 的 `countryChoices`。
   */
  countries(): Observable<{ list: CountryOption[] }> {
    return this.request<{ list: CountryOption[] }>('GET', `${BASE}/country/list`);
  }

  /* 刻意**不接**两个聊天端点（各自零消费者，接上就是死码）：
     - `POST /chat/read`：`chatMessages` 的 GET 已经代劳（且那次响应里的 is_read 还是旧值），
       再接一个「手动补标已读」按钮＝点了没有任何可见效果的按钮；
     - `GET /chat/unread-total`：会话列表**每行**已经带 `unread_count`，页面没有第二个消费点。
     哪天做了底部「消息」角标，再接 unread-total。 */
}
