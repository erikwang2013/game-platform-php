/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useCallback, useRef, useState } from 'react';
import { CaptchaModal } from '../components/CaptchaModal.tsx';
import type { CaptchaProof } from './captcha.ts';

/**
 * 弹框验证码的唯一入口：`const proof = await ask();` 拿到 { captcha_key, clicks }，
 * 用户取消/关闭则拿到 null。返回的 modal 渲染到页面 JSX 里即可（遮罩是 fixed，放哪都行）。
 */
export function useCaptcha() {
  const [open, setOpen] = useState(false);
  const settleRef = useRef<((proof: CaptchaProof | null) => void) | null>(null);

  const ask = useCallback(
    () =>
      new Promise<CaptchaProof | null>((resolve) => {
        // 上一轮若还没收口（理论上不会），先按取消结掉，别把 promise 悬着
        settleRef.current?.(null);
        settleRef.current = resolve;
        setOpen(true);
      }),
    [],
  );

  const settle = useCallback((proof: CaptchaProof | null) => {
    const resolve = settleRef.current;
    settleRef.current = null;
    setOpen(false);
    resolve?.(proof);
  }, []);

  return {
    ask,
    modal: open ? <CaptchaModal onCancel={() => settle(null)} onConfirm={settle} /> : null,
  };
}
