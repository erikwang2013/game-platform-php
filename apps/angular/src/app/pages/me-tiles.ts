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
      .tiles {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-top: 16px;
      }
      .tile {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px 8px;
        border-radius: 14px;
        border: 1px solid var(--stroke);
        background: var(--panel);
        color: var(--text);
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
      }
      .tile:hover {
        border-color: var(--accent);
        box-shadow: var(--glow);
      }
    `,
  ],
})
export class MeTiles {}
