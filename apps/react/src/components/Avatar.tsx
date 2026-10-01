/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useAvatar } from '../lib/avatar.ts';

/** 头像位：取不到就兜底昵称首字母，别留碎图（取法见 lib/avatar.ts 的文件头） */
export function Avatar({ stored, name }: { stored: string | null | undefined; name: string }) {
  const src = useAvatar(stored);
  return (
    <span className="avatar" aria-hidden="true">
      {src ? <img src={src} alt="" /> : name.slice(0, 1)}
    </span>
  );
}
