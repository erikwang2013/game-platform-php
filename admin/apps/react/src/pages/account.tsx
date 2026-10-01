/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 「我的账号」：只读信息 + **改资料 / 改密码**。
 *
 * 后端两条端点（ProfileController）本树一直没有入口 —— 也就是说登录进来的管理员**改不了自己的密码**，
 * 只能让别的管理员在「后台账号」里重置。这里补上：
 * - PUT /admin/v1/profile `{real_name, phone, email}`：逐字段 `has()` 才写，故沿用 FormModal 的
 *   「编辑只发改动过的字段」——不发就不会把没打算改的字段写空。成功回来的是**整条**管理员记录
 *   （不含 password/id_card），据此刷新本地会话，侧栏/顶栏的名字当场跟着变。
 * - PUT /admin/v1/profile/password `{old_password, new_password}`：旧密码服务端校验，
 *   新密码有强度规则（8-32 位、含大小写与数字）—— 规则写在服务端，这里只做**两次输入一致性**，
 *   强度不达标由服务端 422 原话返回（不在前端复刻一套会漂的规则）。
 */
import { useState } from 'react';
import { FormModal } from '../components/FormModal';
import { Card, ErrorNote, Field } from '../components/ui';
import { t, useI18n } from '../i18n/index.ts';
import { ApiError, api, type AdminUser } from '../lib/api';
import { useAuth } from '../lib/auth';
import type { Field as FormField } from '../lib/crud';
import { useSignOut } from '../lib/hooks';

/** 可改字段（username 不在其中：后端 updateProfile 不收它，摆上去就是骗人）。 */
const PROFILE_FIELDS: FormField[] = [
  { name: 'real_name', label: 'f.real_name', type: 'text', hint: 'account.profile_hint' },
  { name: 'phone', label: 'f.phone', type: 'text' },
  { name: 'email', label: 'f.email', type: 'text' },
];

const PASSWORD_FIELDS: FormField[] = [
  { name: 'old_password', label: 'admins.current_password', type: 'password', required: true },
  { name: 'new_password', label: 'admins.new_password', type: 'password', required: true, hint: 'account.password_hint' },
  { name: 'confirm_password', label: 'account.confirm_password', type: 'password', required: true },
];

type Notice = { text: string; tone: 'error' | 'ok' };

/** 账号页签内容（TabPage 的 account 组渲染它）。 */
export function AccountCard() {
  useI18n();
  const { user, refreshUser } = useAuth();
  const signOut = useSignOut();
  const [editing, setEditing] = useState<'profile' | 'password' | null>(null);
  const [notice, setNotice] = useState<Notice | null>(null);

  const saveProfile = async (body: Record<string, unknown>) => {
    if (Object.keys(body).length === 0) {
      // 一个字段都没改：不发请求（后端 updateProfile 全是 has() 分支，空体等于无操作）
      setEditing(null);
      return;
    }
    const updated = await api<Partial<AdminUser>>('/admin/v1/profile', { method: 'PUT', body });
    // 服务端回的是整条记录，据它更新本地会话 —— 不重新登录也能看到侧栏名字变了
    refreshUser({
      id: String(updated.id ?? user?.id ?? ''),
      username: String(updated.username ?? user?.username ?? ''),
      real_name: String(updated.real_name ?? ''),
    });
    setEditing(null);
    setNotice({ text: t('account.profile_saved'), tone: 'ok' });
  };

  const savePassword = async (body: Record<string, unknown>) => {
    if (body.new_password !== body.confirm_password) {
      // 抛给 FormModal：框内提示且**不关框**，用户可以改了重试（服务端的强度校验同理）
      throw new ApiError(422, t('account.password_mismatch'));
    }
    // 确认框那一栏只是前端自校验，不进请求体
    const payload = { ...body };
    delete payload.confirm_password;
    await api('/admin/v1/profile/password', { method: 'PUT', body: payload });
    setEditing(null);
    setNotice({ text: t('account.password_changed'), tone: 'ok' });
  };

  return (
    <>
      <Card title={t('account.title')} sub={t('account.sub')}>
        <Field label={t('account.username')} value={user?.username ?? '—'} />
        <Field label={t('account.real_name')} value={user?.real_name ?? '—'} />
        <Field label={t('account.user_id')} value={user?.id ?? '—'} />
        <Field
          label={t('account.actions')}
          value={
            <span className="rowact">
              <button
                type="button"
                className="btn btn-sm"
                onClick={() => {
                  setNotice(null);
                  setEditing('profile');
                }}
              >
                {t('account.edit_profile')}
              </button>
              <button
                type="button"
                className="btn btn-sm"
                onClick={() => {
                  setNotice(null);
                  setEditing('password');
                }}
              >
                {t('account.change_password')}
              </button>
              <button type="button" className="btn btn-sm" onClick={() => void signOut()}>
                {t('app.logout')}
              </button>
            </span>
          }
        />
      </Card>
      {notice ? <ErrorNote message={notice.text} tone={notice.tone} /> : null}
      {editing === 'profile' ? (
        <FormModal
          title={t('account.edit_profile')}
          fields={PROFILE_FIELDS}
          // 只预填本地会话里有的字段：phone/email 不在登录返回的 user 里，留空即「没改」，
          // 不会因为不知道原值就把它们写空
          row={{ real_name: user?.real_name ?? '' }}
          submitLabel={t('common.save')}
          onSubmit={saveProfile}
          onClose={() => setEditing(null)}
        />
      ) : null}
      {editing === 'password' ? (
        <FormModal
          title={t('account.change_password')}
          fields={PASSWORD_FIELDS}
          submitLabel={t('account.change_password')}
          onSubmit={savePassword}
          onClose={() => setEditing(null)}
        />
      ) : null}
    </>
  );
}
