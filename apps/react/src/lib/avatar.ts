/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useState } from 'react';
import { fileBlob } from './upload.ts';

/**
 * 头像落库值 → 可直接喂 `<img src>` 的地址（好友 / 申请 / 搜索 / 会话四个列表共用）。
 *
 * 为什么不能直接 `<img src={avatar}>`：上传物落库值是相对地址 `/api/v1/user/file/{savedPath}`，
 * 那个端点**只认 `Authorization: Bearer` 头**（`UserAuth::extractToken` 不看 cookie），
 * 而 `<img>` 带不上自定义头 ⇒ 直接绑就是一张碎图。
 * **头像虽然是"任何登录用户可读"（`UserFileController::readable` 最后一条），但"可读"的前提仍是
 * 带 token** —— 匿名读不了，这一步省不掉。
 * （第三方 OAuth 的历史值是绝对地址，那类本来就能直接显示，不拉。）
 *
 * 取不到（无头像 / 403 / 网络失败）一律回空串，由调用方回落到昵称首字母，不留碎图。
 *
 * ponytail: 进程内缓存不回收（同一会话里出现过的不同头像数量有限、单张几十 KB）。
 * 要回收得做引用计数或 LRU + revokeObjectURL，等真有长列表再说。
 */
const cache = new Map<string, Promise<string>>();

function load(key: string): Promise<string> {
  let p = cache.get(key);
  if (!p) {
    // 失败也进缓存（存空串）：碎图重试没有意义，下一次渲染不该再打一遍请求
    p = fileBlob(key).then(
      (b) => URL.createObjectURL(b),
      () => '',
    );
    cache.set(key, p);
  }
  return p;
}

/**
 * 状态按 key 存（`loaded.k === key` 才认）：换人/换图时不会短暂显示上一个人的头像。
 * 绝对地址与空值在渲染期直接判掉，不进 effect —— 渲染期已有的答案没有理由再走一轮 state。
 */
export function useAvatar(stored: string | null | undefined): string {
  const key = (stored ?? '').trim();
  const absolute = /^https?:/i.test(key) ? key : '';
  const [loaded, setLoaded] = useState<{ k: string; u: string } | null>(null);

  useEffect(() => {
    if (!key || absolute) return;
    let alive = true;
    void load(key).then((u) => {
      if (alive) setLoaded({ k: key, u });
    });
    return () => {
      alive = false;
    };
  }, [key, absolute]);

  return absolute || (loaded?.k === key ? loaded.u : '');
}
