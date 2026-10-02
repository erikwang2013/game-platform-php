/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { useI18n } from '../i18n/useI18n.ts';

/**
 * 只读文本框：点一下就全选，配合下面的「复制」按钮与浏览器原生复制都能用。
 * 不用 navigator.clipboard 作为唯一手段——它要求安全上下文，子路径/内网 http 下会直接抛。
 *
 * 原在 Security.tsx 内（2FA 密钥/备用码用），邀请页也要复制短码与链接 ⇒ 提到 components 共用。
 */
export function SecretBox({ value, label }: { value: string; label: string }) {
  const { t } = useI18n();
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // 复制不了不报错：框里内容可选，用户手动选也一样
    }
  };

  return (
    <label className="field">
      <span>{label}</span>
      <div className="row" style={{ gap: 8, flexWrap: 'nowrap' }}>
        <input
          className="input mono"
          readOnly
          value={value}
          onFocus={(e) => e.currentTarget.select()}
          style={{ flex: 1, minWidth: 0 }}
        />
        <button type="button" className="btn btn--sm" onClick={copy}>
          {copied ? t('app.copied') : t('app.copy')}
        </button>
      </div>
    </label>
  );
}
