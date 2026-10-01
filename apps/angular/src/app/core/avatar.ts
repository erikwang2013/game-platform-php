/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { HttpClient } from '@angular/common/http';
import { Injectable, Signal, inject, signal } from '@angular/core';
import { fileBlob } from './upload';

/**
 * 用户头像 → 可直接喂 `<img src>` 的地址。好友/申请/搜索/会话四个列表共用。
 *
 * 为什么不能直接绑落库值：上传物是 `/api/v1/user/file/{savedPath}`，那个端点**只认
 * `Authorization: Bearer` 头**（`UserAuth::extractToken` 不看 cookie），`<img src>` 带不上头
 * ⇒ 直接绑就是一张碎图。所以带 token 取字节再转 objectURL。
 * **头像虽然是"任何登录用户可读"（`UserFileController::readable` 最后一条），但"可读"的前提仍是
 * 带 token** —— 匿名读不了，这一步省不掉。
 * （第三方 OAuth 的历史值是绝对地址，那类本来就能直接显示，不拉。）
 *
 * 同一张图多处用只拉一次：缓存按**落库值**做键，命中就复用同一个 objectURL。
 *
 * 单个头像（「我的」页）没走这里：那边只有一张、且在 `ngOnDestroy` 里 revoke，
 * 用共享缓存反而会把它 revoke 掉。见 pages/me.ts 的 avatarSrc。
 *
 * ponytail: 进程内缓存不回收（同一会话里出现过的不同头像数量有限、单张几十 KB）。
 * 要回收得做引用计数或 LRU + revokeObjectURL，等真有长列表再说。
 */
@Injectable({ providedIn: 'root' })
export class Avatars {
  private readonly http = inject(HttpClient);
  private readonly map = new Map<string, Signal<string>>();

  /** 没有头像 / 取失败时是空串 —— 模板据此回落到首字母兜底，不留碎图 */
  of(stored: string | null | undefined): Signal<string> {
    const key = (stored ?? '').trim();
    const hit = this.map.get(key);
    if (hit) return hit;

    const abs = /^https?:/i.test(key);
    const s = signal(abs ? key : '');
    const ro = s.asReadonly();
    this.map.set(key, ro);
    if (key && !abs) {
      fileBlob(this.http, key).subscribe({
        next: (b) => s.set(URL.createObjectURL(b)),
        error: () => undefined,
      });
    }
    return ro;
  }
}
