/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { NgTemplateOutlet } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Observable, catchError, forkJoin, of } from 'rxjs';
import {
  Api,
  ApiError,
  FriendRequest,
  FriendUser,
  dt,
} from '../core/api.service';
import { Avatars } from '../core/avatar';

/**
 * 好友 —— 好友列表 / 收到的申请 / 添加（搜人）。
 *
 * 服务端契约（FriendController）里四条**必须在页面上体现**的，逐条对应下面四处实现：
 *  1. **两个 id 不能混用**：`/friend/requests` 的 `id` 是**关系** hashid，accept/reject 要它；
 *     `/friend/remove`、`/friend/request` 要的是**用户** hashid。混用必 404。（见 accept()）
 *  2. **接受只有收件人做得成**：服务端按 `id = request_id` **且 `friend_id = 我`** 且 pending 查，
 *     查不到回 404 'Request not found'（与"根本不存在"同码 ⇒ 客户端分辨不出，也没必要分辨）。
 *     所以「接受/拒绝」只摆在**收到的申请**里：本树没有"我发出的申请"端点，发出去的查不回来。
 *  3. **拒绝是硬删**（`$f->delete()`，不是置 rejected）⇒ 拒完对方能**立刻再发**。
 *     页面上不写"不会再次收到"这种承诺。
 *  4. **删好友幂等且静默**：按双向 + accepted 删，**一行没删到也回成功** ⇒ 客户端不自己先判
 *     "他是不是我好友"，失败必是网络/鉴权层面的真失败。
 *
 * 还有一条是**设计**不是 bug：A 申请 B 之后 B 再申请 A，会被「任一方向已有记录」挡下回 422
 * 「Already friends or request pending」。B 的出路是去「申请」里**接受**，不是再点一次。
 * 这正是「互相加不自动接受」——页面把 422 原文透出，不替服务端自动转成接受。
 */
@Component({
  selector: 'app-friends',
  imports: [NgTemplateOutlet, RouterLink],
  template: `
    <div class="between sect">
      <h2>好友</h2>
      <button class="btn ghost" type="button" [disabled]="loading()" (click)="load()">刷新</button>
    </div>

    <div class="chips tabs">
      <button type="button" class="chip" [class.on]="tab() === 'list'" (click)="pick('list')">
        好友{{ fList().length ? ' ' + fList().length : '' }}
      </button>
      <button type="button" class="chip" [class.on]="tab() === 'req'" (click)="pick('req')">
        申请{{ rList().length ? ' ' + rList().length : '' }}
      </button>
      <button type="button" class="chip" [class.on]="tab() === 'add'" (click)="pick('add')">
        添加
      </button>
    </div>

    @if (ok(); as m) {
      <div class="alert ok">{{ m }}</div>
    }
    @if (actErr(); as m) {
      <div class="alert">{{ m }}</div>
    }

    <!-- 头像 + 名字 + @用户名，三个列表共用 -->
    <ng-template #who let-u>
      <span class="av" aria-hidden="true">
        @if (av.of(u.avatar)(); as src) {
          <img [src]="src" alt="" />
        } @else {
          {{ name(u).slice(0, 1) }}
        }
      </span>
      <div class="grow">
        <div class="t">{{ name(u) }}</div>
        <div class="s">@{{ u.username }}</div>
      </div>
    </ng-template>

    @if (tab() === 'list') {
      <div class="card">
        @if (loading()) {
          <div class="rows">
            @for (i of [1, 2, 3]; track i) {
              <div class="row"><div class="skeleton sk-row"></div></div>
            }
          </div>
        } @else if (fErr()) {
          <div class="state">
            <strong>加载失败</strong>
            <span>{{ fErr() }}</span>
            <button class="btn" type="button" (click)="load()">重试</button>
          </div>
        } @else if (!fList().length) {
          <div class="state">
            <img class="state-art" src="mascot.svg" alt="" aria-hidden="true" />
            <strong>还没有好友</strong>
            <span>去「添加」按用户名或昵称搜人</span>
          </div>
        } @else {
          <div class="rows">
            @for (f of fList(); track f.id) {
              <div class="row">
                <ng-container *ngTemplateOutlet="who; context: { $implicit: f }"></ng-container>
                <!-- 发消息走 /chat/{对方用户 hashid}。只摆在好友列表里：
                     服务端 send 只放行 accepted 好友（非好友 403），
                     搜索结果/申请人身上摆这个按钮就是摆一个必然失败的按钮 -->
                <a class="btn ghost" [routerLink]="['/chat', f.id]">发消息</a>
                <button
                  class="btn ghost"
                  type="button"
                  [disabled]="busy() === f.id"
                  (click)="remove(f)"
                >
                  {{ busy() === f.id ? '…' : '删除' }}
                </button>
              </div>
            }
          </div>
        }
      </div>
    } @else if (tab() === 'req') {
      <div class="card">
        @if (loading()) {
          <div class="rows">
            @for (i of [1, 2]; track i) {
              <div class="row"><div class="skeleton sk-row"></div></div>
            }
          </div>
        } @else if (rErr()) {
          <div class="state">
            <strong>加载失败</strong>
            <span>{{ rErr() }}</span>
            <button class="btn" type="button" (click)="load()">重试</button>
          </div>
        } @else if (!rList().length) {
          <div class="state">
            <strong>没有待处理的申请</strong>
            <span>别人加你时会出现在这里</span>
          </div>
        } @else {
          <div class="rows">
            @for (r of rList(); track r.id) {
              <div class="row">
                <ng-container *ngTemplateOutlet="who; context: { $implicit: r.user }"></ng-container>
                <div class="s when">{{ dt(r.created_at) }}</div>
                <button
                  class="btn primary"
                  type="button"
                  [disabled]="busy() === 'a' + r.id"
                  (click)="accept(r)"
                >
                  {{ busy() === 'a' + r.id ? '…' : '接受' }}
                </button>
                <button
                  class="btn ghost"
                  type="button"
                  [disabled]="busy() === 'r' + r.id"
                  (click)="reject(r)"
                >
                  {{ busy() === 'r' + r.id ? '…' : '拒绝' }}
                </button>
              </div>
            }
          </div>
        }
      </div>
    } @else {
      <!-- 无 [formGroup] 的表单一律 (submit) + preventDefault：ngSubmit 由 NgForm 提供，
           只有 FormsModule 导出，本树不引它 -->
      <form class="sbar" (submit)="doSearch($event)" novalidate>
        <input
          class="input"
          type="search"
          name="q"
          placeholder="按用户名或昵称搜索"
          autocomplete="off"
          [value]="q()"
          (input)="q.set(val($event))"
        />
        <button class="btn primary" type="submit" [disabled]="searching() || !q().trim()">
          {{ searching() ? '搜索中…' : '搜索' }}
        </button>
      </form>

      <div class="card">
        @if (searching()) {
          <div class="rows">
            @for (i of [1, 2]; track i) {
              <div class="row"><div class="skeleton sk-row"></div></div>
            }
          </div>
        } @else if (hits(); as list) {
          @if (!list.length) {
            <div class="state">
              <strong>没有找到匹配的用户</strong>
              <span>换个用户名或昵称试试</span>
            </div>
          } @else {
            <div class="rows">
              @for (u of list; track u.id) {
                <div class="row">
                  <ng-container *ngTemplateOutlet="who; context: { $implicit: u }"></ng-container>
                  <!-- 服务端不回"是否已好友/是否已有待处理申请"，只能拿好友列表比对。
                       比不中的（例如已发过申请仍 pending）点了会吃 422，原样透出即可 ——
                       别自己维护一张"我发过谁"的表，那是第二真值源 -->
                  @if (friendIds().has(u.id)) {
                    <span class="chip">已是好友</span>
                  } @else {
                    <button
                      class="btn primary"
                      type="button"
                      [disabled]="busy() === u.id"
                      (click)="add(u)"
                    >
                      {{ busy() === u.id ? '…' : '加好友' }}
                    </button>
                  }
                </div>
              }
            </div>
          }
        } @else {
          <div class="state">
            <strong>搜人加好友</strong>
            <span>输入用户名或昵称后点搜索</span>
          </div>
        }
      </div>
    }
  `,
  styles: [
    `
      .sect {
        margin: 0 0 14px;
      }
      .sect h2 {
        margin: 0;
        font-size: 17px;
      }
      .tabs {
        margin: 18px 0 14px;
      }
      .av {
        flex: 0 0 auto;
        width: 40px;
        height: 40px;
        border-radius: 13px;
        background: var(--grad);
        color: #0b0d17;
        font-size: 16px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
      }
      .av img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      .sk-row {
        height: 16px;
        width: 70%;
      }
      .when {
        flex: 0 0 auto;
      }
      .sbar {
        display: flex;
        gap: 8px;
        margin-bottom: 12px;
      }
      .sbar .input {
        flex: 1;
        min-width: 0;
      }
    `,
  ],
})
export class FriendsPage {
  private readonly api = inject(Api);
  protected readonly av = inject(Avatars);

  protected readonly dt = dt;
  protected readonly tab = signal<'list' | 'req' | 'add'>('list');

  protected readonly fList = signal<FriendUser[]>([]);
  protected readonly rList = signal<FriendRequest[]>([]);
  protected readonly fErr = signal('');
  protected readonly rErr = signal('');
  protected readonly loading = signal(true);

  protected readonly busy = signal('');
  protected readonly ok = signal('');
  protected readonly actErr = signal('');

  protected readonly q = signal('');
  /** null = 还没搜过（与"搜了但没结果"要分开显示） */
  protected readonly hits = signal<FriendUser[] | null>(null);
  protected readonly searching = signal(false);

  /** 已经是好友的 id 集合：搜索结果里据此把「加好友」换成「已是好友」 */
  protected readonly friendIds = computed(() => new Set(this.fList().map((f) => f.id)));

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.fErr.set('');
    this.rErr.set('');
    // 两个列表**各自降级**：好友挂了不该让「申请」跟着空（forkJoin 是全有全无），
    // 故每条先 catchError 兜成 null，再把非 null 的装上
    forkJoin({
      f: this.api.friends().pipe(
        catchError((e: ApiError) => {
          this.fErr.set(e.message);
          return of(null);
        }),
      ),
      r: this.api.friendRequests().pipe(
        catchError((e: ApiError) => {
          this.rErr.set(e.message);
          return of(null);
        }),
      ),
    }).subscribe(({ f, r }) => {
      if (f) this.fList.set(f.list ?? []);
      if (r) this.rList.set(r.list ?? []);
      this.loading.set(false);
    });
  }

  protected pick(t: 'list' | 'req' | 'add'): void {
    this.tab.set(t);
    this.ok.set('');
    this.actErr.set('');
  }

  protected name(u: { nickname: string | null; username: string }): string {
    return u.nickname || u.username;
  }

  protected val(ev: Event): string {
    return (ev.target as HTMLInputElement).value;
  }

  /**
   * 三个动作的公共壳：**成功后两个列表都重拉** —— 接受一条申请同时改变「好友」和「申请」，
   * 只刷一边必漏。失败把服务端文案原样透出（404/422/403 都是写给人看的）。
   */
  private act(key: string, fn: () => Observable<unknown>, okText: string): void {
    if (this.busy()) return;
    this.busy.set(key);
    this.ok.set('');
    this.actErr.set('');
    fn().subscribe({
      next: () => {
        this.busy.set('');
        this.ok.set(okText);
        this.load();
      },
      error: (e: ApiError) => {
        this.busy.set('');
        this.actErr.set(e.message);
      },
    });
  }

  /** ⚠ 传的是**关系** id（`r.id`），不是 `r.user.id` —— 混用服务端 404 */
  protected accept(r: FriendRequest): void {
    this.act('a' + r.id, () => this.api.friendAccept(r.id), `已接受 ${this.name(r.user)}`);
  }

  /** 拒绝是**硬删**，对方可以立刻再发；文案里不承诺"不会再收到" */
  protected reject(r: FriendRequest): void {
    this.act('r' + r.id, () => this.api.friendReject(r.id), `已拒绝 ${this.name(r.user)}`);
  }

  /** 删好友：`f.id` 是**用户** hashid（不是关系 id）。服务端幂等，删不到也回成功 */
  protected remove(f: FriendUser): void {
    this.act(f.id, () => this.api.friendRemove(f.id), `已删除好友 ${this.name(f)}`);
  }

  protected add(u: FriendUser): void {
    this.act(u.id, () => this.api.friendRequest(u.id), `已向 ${this.name(u)} 发送申请`);
  }

  /** 搜索是**显式触发**的（服务端没有防抖/限流余量，逐字打请求会白撞一堆） */
  protected doSearch(ev: Event): void {
    ev.preventDefault();
    const q = this.q().trim();
    if (this.searching() || !q) return;
    this.searching.set(true);
    this.ok.set('');
    this.actErr.set('');
    this.api.friendSearch(q).subscribe({
      next: (r) => {
        this.hits.set(r.list ?? []);
        this.searching.set(false);
      },
      error: (e: ApiError) => {
        this.hits.set(null);
        this.actErr.set(e.message);
        this.searching.set(false);
      },
    });
  }
}
