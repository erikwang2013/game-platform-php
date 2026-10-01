/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

/**
 * 常用功能入口格 —— 放在「我的」页顶部。
 *
 * 单独成一个组件而不是写进 MePage：MePage 已经贴着 500 行上限，再加这一块会越线。
 * 入口本身全是静态链接（公告/排行榜是公开页，其余在路由上有 authGuard 兜着）。
 */
@Component({
  selector: 'app-me-tiles',
  imports: [RouterLink],
  template: `
    <nav class="tiles" aria-label="常用功能">
      <a class="tile" routerLink="/kyc">实名认证</a>
      <a class="tile" routerLink="/security">账号安全</a>
      <a class="tile" routerLink="/wallet/records">游戏流水</a>
      <a class="tile" routerLink="/activities">运营活动</a>
      <a class="tile" routerLink="/tournaments">赛事</a>
      <a class="tile" routerLink="/tickets">客服工单</a>
      <a class="tile" routerLink="/chat">消息</a>
      <a class="tile" routerLink="/friends">好友</a>
      <a class="tile" routerLink="/invite">邀请好友</a>
      <a class="tile" routerLink="/announcements">平台公告</a>
      <a class="tile" routerLink="/leaderboard">排行榜</a>
    </nav>
  `,
  styles: [
    `
      /* 窄屏 3 列（一个拇指够得着），桌面靠 auto-fill 长到 5–6 列 ——
         写死 3 列的话，1100px 宽下每个磁贴会被拉到 360px 只剩四个字。 */
      .tiles {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 16px;
      }
      @media (min-width: 768px) {
        .tiles {
          grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        }
      }
      /* 磁贴是**导航**不是主操作：≥44px 高度保证点得到，但不发光、
         不抢主按钮的品牌紫。悬停只抬一档 + 换描边。 */
      .tile {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 56px;
        padding: 14px 10px;
        border-radius: var(--r-md);
        border: 1px solid var(--line);
        background: var(--surface-2);
        color: var(--text);
        text-decoration: none;
        font-size: 13px;
        font-weight: 650;
        text-align: center;
        transition:
          background var(--t-fast) var(--ease),
          border-color var(--t-fast) var(--ease),
          transform var(--t-fast) var(--ease);
      }
      .tile:hover {
        color: var(--text);
        background: var(--surface-3);
        border-color: var(--line-2);
        transform: translateY(-2px);
      }
    `,
  ],
})
export class MeTiles {}
