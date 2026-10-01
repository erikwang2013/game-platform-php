/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { ApiError, api } from '../lib/api.ts';
import { SecretBox } from '../components/SecretBox.tsx';

/**
 * 邀请好友。两个端点都真能用，且短码在这棵树里**有真实消费者**（不是摆设）：
 *
 *  1. `POST /shares`（登录态）生成 8 位短码 —— 本页做这一步；
 *  2. 被邀请人打开邀请链接 → 本树 `/login?code=xxx` 页**作为落地页**调
 *     `POST /shares/visit`（匿名路由）上报点击 ⇒ `clicks` 原子自增；
 *  3. 被邀请人用该码注册 → `AuthController::register` 收 `share_code` →
 *     `ShareLink::bindConversion` ⇒ `conversions` 自增，且若短码带了 `activity_id`，
 *     还会写一条邀请活动的 `user.registered` 进度（可能带真钱奖励）。
 *     见 Login.tsx 的 `?code=` 入口与 api.register 的 shareCode 参数。
 *
 * ⚠ 两个**后端缺口**，本页按现状如实呈现、不假装有：
 *  - `clicks`/`conversions` **没有任何读端点**（ShareController 只有 create/visit），
 *    也没有「我的短码列表」⇒ 本页无法展示邀请战绩，也不缓存短码（缓存＝第二真值源）。
 *    每点一次「生成」就多一个码，旧码仍有效但查不回来。
 *  - `expires_at` 在 `create()` 里从不赋值、列默认 NULL ⇒ **实际永不过期**，
 *    回包恒 null。所以本页不显示有效期，也不写「长期有效」这种没依据的承诺。
 */

/** 与 ShareController::create 的 8 位随机字母数字一致；服务端只校验 visit 的 max:12 */
const CODE_LEN = 8;

export function Invite() {
  const [code, setCode] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  // 生成的码永不退回服务端查询，链接里的 origin + BASE_URL 就是本树自己的挂载点
  // （生产 build 带 --base=/app-react/，子路径部署也拼得对）
  const link = code
    ? `${window.location.origin}${import.meta.env.BASE_URL}login?code=${code}`
    : '';

  const generate = async () => {
    if (busy) return;
    setBusy(true);
    setErr(null);
    try {
      const r = await api.createShare();
      setCode(r.short_code);
    } catch (e) {
      setErr(e instanceof ApiError ? e.message : '生成失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <section className="stack">
        <p className="label">邀请好友</p>
        <h1 className="h1">
          邀请
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          生成一个邀请码发给朋友。对方打开链接、用它注册后，这次邀请才会计入转化。
        </p>
      </section>

      <section className="card stack">
        {!code && (
          <>
            <p className="small muted" style={{ margin: 0 }}>
              邀请码一次一个，点下面的按钮生成。
            </p>
            <button
              type="button"
              className="btn btn--primary btn--block"
              disabled={busy}
              onClick={generate}
            >
              {busy && <span className="spin" aria-hidden="true" />}
              生成邀请码
            </button>
          </>
        )}

        {err && <p className="err" role="alert">{err}</p>}

        {code && (
          <>
            <SecretBox label={`邀请码（${CODE_LEN} 位）`} value={code} />
            <SecretBox label="邀请链接" value={link} />
            <p className="small muted" style={{ margin: 0 }}>
              朋友打开这个链接注册即可。对方注册时填的码会在服务端绑定到你的账号。
            </p>
            <button type="button" className="btn btn--sm" disabled={busy} onClick={generate}>
              再生成一个
            </button>
          </>
        )}
      </section>

      <section className="card card--flat stack">
        <p className="label" style={{ margin: 0 }}>
          流程
        </p>
        <p className="small muted" style={{ margin: 0 }}>
          1. 生成邀请码 · 2. 把链接发给朋友 · 3. 朋友用链接里的码注册。三步都在本树内完成。
        </p>
      </section>
    </>
  );
}
