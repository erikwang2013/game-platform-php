/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { api, type IdentityStatusValue } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { dt } from '../lib/datetime.ts';
import { ACCEPT, MAX_BYTES, uploadImage } from '../lib/upload.ts';
import { countryOptions } from '../lib/countryOptions.ts';
import { ErrorBox, Loading } from '../components/States.tsx';
import { t, type MessageKey } from '../i18n/index.ts';
import { useI18n } from '../i18n/useI18n.ts';

/**
 * 实名认证（KYC）—— **提现档位的唯一开关**：`WithdrawController::withdrawLevel:321-323`
 * 只在 `status === 'approved'` 时给 verified 档（更高单笔/日/月额度、更低费率），其余一律 default。
 * 也就是说这个页面直接决定用户能提多少，不是装饰页。
 *
 * 三个证件照走 aetherupload 单块直传（`lib/upload.ts`），提交时发的是**上传后的落库值**。
 * 提交后**不再回显照片**：`identity/status` 只回掩码姓名与审核结果，不回照片路径
 * （`IdentityController::status:33-40`）—— 所以照片区只在「未提交/已驳回」时有意义。
 *
 * 口径与 angular 那棵一致（同一个后端、同一套判据）：先本地挡一遍必填再发请求（省一次必 422 的往返），
 * 提交成功后**回读真状态**，不凭「请求成功」就宣布已认证。
 */
const TYPE_LABEL: Record<string, MessageKey> = {
  id_card: 'kyc.type_id_card',
  passport: 'kyc.type_passport',
  driver_license: 'kyc.type_driver_license',
};

/** 真值见 `IdentityController::apply` 的 validator 白名单（in:id_card,passport,driver_license） */
const ID_TYPES = ['id_card', 'passport', 'driver_license'] as const;

/** 未提交时服务端只回 status 一个字段；这四种之外的值原样透出，不猜 */
const STATUS_LABEL: Record<string, MessageKey> = {
  not_submitted: 'kyc.status_not_submitted',
  pending: 'kyc.status_pending',
  approved: 'kyc.status_approved',
  rejected: 'kyc.status_rejected',
};

const STATUS_PILL: Record<string, string> = {
  not_submitted: 'pill--plain',
  pending: 'pill--plain',
  approved: 'pill--yellow',
  rejected: 'pill--orange',
};

/** 服务端 validator 里只有 id_back_photo 是 nullable，另外两张必填 */
const PHOTOS: ReadonlyArray<{ key: string; label: MessageKey }> = [
  { key: 'id_front_photo', label: 'kyc.photo_front' },
  { key: 'id_back_photo', label: 'kyc.photo_back' },
  { key: 'selfie_photo', label: 'kyc.photo_selfie' },
];

/**
 * 表里查不到的值**原样透出**（服务端将来加状态/类型不吞字），查得到的**在渲染期才翻**。
 *
 * ⚠ 存**键**不存文案：模块顶层求值只发生一次，存文案会把它冻在首屏语言上
 * （与 `Wallet.tsx` 的 `TABS`、`Layout.tsx` 的 `NAV` 同款约定）。
 */
const lookup = (map: Record<string, MessageKey>, v: string | undefined, empty = ''): string => {
  if (!v) return empty;
  const k = map[v];
  return k ? t(k) : v;
};

const typeLabel = (v?: string) => lookup(TYPE_LABEL, v, '—');

/**
 * 反馈文案的**暂存形**：本地校验存「键」、服务端/上传失败存「原始错误 + 兜底键」，
 * **一份都不存翻好的串** —— `pick` / `submit` 都在 `await` 之后才落值，存串就把语言
 * 冻在那一刻（同 `Friends.tsx` 的 `Msg`）。
 */
type Msg = { key: MessageKey } | { err: unknown; fallback: MessageKey };

/** 渲染期才翻（本文件用模块级 `t`，组件里那次 `useI18n()` 负责订阅重绘）。 */
const msgText = (m: Msg): string =>
  'err' in m ? (m.err instanceof Error ? m.err.message : t(m.fallback)) : t(m.key);

export function Kyc() {
  // 只为订阅语言变更引起的重渲染；文案求值走模块级的 t()（同 `Me.tsx`）
  useI18n();
  const st = useAsync(() => api.identityStatus(), []);
  /**
   * 国家/地区选项：公开端点，只列在册国家代码。取不到不挡提交 —— country 在服务端是
   * nullable，是纯可选项，这一项降级成「只有未选择」比拦住用户强。
   */
  const countries = useAsync(() => api.countries(), []);
  const codes = (countries.data?.list ?? []).map((c) => c.country_code);
  /** 下拉第一项：三态都要有话说，否则「还没拉到」和「拉失败」都长成「未选择」 */
  const noneLabel = countries.loading
    ? t('kyc.country_loading')
    : countries.error
      ? t('kyc.country_failed')
      : t('kyc.country_none');

  const [realName, setRealName] = useState('');
  const [idNumber, setIdNumber] = useState('');
  const [country, setCountry] = useState('');
  /** 用户没选过时留 null，展示值按服务端回填的 id_type 推导 —— 不在 effect 里回写 state */
  const [pickedType, setPickedType] = useState<string | null>(null);
  const serverType = st.data?.id_type;
  const idType =
    pickedType ?? (serverType && (ID_TYPES as readonly string[]).includes(serverType) ? serverType : 'id_card');

  /** 落库值（相对地址），提交时直接发给 /user/identity/apply */
  const [photo, setPhoto] = useState<Record<string, string>>({});
  /** 本地预览的 objectURL：只让用户确认选对了图，不发请求 */
  const [preview, setPreview] = useState<Record<string, string>>({});
  const [upBusy, setUpBusy] = useState<Record<string, boolean>>({});

  const [busy, setBusy] = useState(false);
  const [formErr, setFormErr] = useState<Msg | null>(null);
  const [okMsg, setOkMsg] = useState<MessageKey | null>(null);

  // objectURL 不撤销会一直占着内存；ref 镜像最新值供卸载时统一收尾
  // （镜像在 effect 里写、不在渲染期写：渲染期碰 ref 本身就是 React 反模式）
  const previewRef = useRef(preview);
  useEffect(() => {
    previewRef.current = preview;
  }, [preview]);
  useEffect(
    () => () => {
      for (const u of Object.values(previewRef.current)) URL.revokeObjectURL(u);
    },
    [],
  );

  const clearErr = () => setFormErr(null);

  /** 选图 → 先本地预览，再直传；失败只清掉这一张，不影响已传成功的 */
  const pick = async (key: string, input: HTMLInputElement) => {
    const file = input.files?.[0];
    input.value = ''; // 允许同一张图重选（不清则 change 不触发）
    if (!file) return;

    if (file.size > MAX_BYTES) {
      setFormErr({ key: 'kyc.err_too_large' });
      return;
    }
    setFormErr(null);
    setPreview((m) => {
      if (m[key]) URL.revokeObjectURL(m[key]!);
      return { ...m, [key]: URL.createObjectURL(file) };
    });
    setUpBusy((m) => ({ ...m, [key]: true }));
    try {
      const path = await uploadImage(file);
      setPhoto((m) => ({ ...m, [key]: path }));
    } catch (e) {
      setPhoto((m) => {
        const { [key]: _drop, ...rest } = m;
        return rest;
      });
      setFormErr({ err: e, fallback: 'upload.failed_retry' });
    } finally {
      setUpBusy((m) => ({ ...m, [key]: false }));
    }
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (busy) return;
    const name = realName.trim();
    const num = idNumber.trim();
    const front = photo['id_front_photo'] ?? '';
    const selfie = photo['selfie_photo'] ?? '';

    // 本地先挡一遍，避免明知 422 还发请求；服务端仍会二次校验
    if (!name) return setFormErr({ key: 'kyc.err_real_name' });
    if (!num) return setFormErr({ key: 'kyc.err_id_number' });
    if (Object.values(upBusy).some(Boolean)) return setFormErr({ key: 'kyc.err_uploading' });
    if (!front) return setFormErr({ key: 'kyc.err_front' });
    if (!selfie) return setFormErr({ key: 'kyc.err_selfie' });

    setBusy(true);
    setFormErr(null);
    setOkMsg(null);
    try {
      await api.applyIdentity({
        real_name: name,
        id_type: idType,
        id_number: num,
        id_front_photo: front,
        ...(photo['id_back_photo'] ? { id_back_photo: photo['id_back_photo']! } : {}),
        selfie_photo: selfie,
        ...(country.trim() ? { country: country.trim() } : {}),
      });
      setOkMsg('kyc.ok_submitted');
      // 回读真状态，不凭「请求成功」宣布结果
      st.reload();
    } catch (err) {
      setFormErr({ err, fallback: 'error.submit_failed' });
    } finally {
      setBusy(false);
    }
  };

  const status: IdentityStatusValue | undefined = st.data?.status;

  return (
    <>
      <section className="stack">
        <p className="label">{t('kyc.label_account')}</p>
        <h1 className="h1">
          {t('kyc.title')}
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
      </section>

      {st.loading && <Loading />}
      {!st.loading && st.error && <ErrorBox message={st.error} onRetry={st.reload} />}

      {!st.loading && !st.error && st.data && (
        <>
          <section className="card card--flat stack">
            <div className="between">
              <span className="small muted">{t('kyc.current_status')}</span>
              <span className={`pill ${STATUS_PILL[status ?? ''] ?? 'pill--plain'}`}>
                {lookup(STATUS_LABEL, status)}
              </span>
            </div>
            <p className="small muted" style={{ margin: 0 }}>
              {/*
                切成 4 段只为**保住 HEAD 那处 <b> 加粗边界**：`t()` 只能回字符串、回不了 React 元素，
                所以加粗由调用点包（同 `Me.tsx` 的 `me.export_desc_*`）。
                ⚠ 段间那个空格**显式写出来**：HEAD 里这一处是**两行都有内容**的换行+缩进，
                JSX 折成**一个空格**（`…按默认档计。\n证件信息…`）；行尾贴着标签的那两处则**不留空格**。
              */}
              {t('kyc.hint_1')}
              <b>{t('kyc.hint_2')}</b>
              {t('kyc.hint_3')} {t('kyc.hint_4')}
            </p>
            {status !== 'not_submitted' && (
              <div className="list">
                <div className="li">
                  <span className="small muted">{t('kyc.field_name')}</span>
                  <span className="small">{st.data.real_name || '—'}</span>
                </div>
                <div className="li">
                  <span className="small muted">{t('kyc.field_id_type')}</span>
                  <span className="small">{typeLabel(st.data.id_type)}</span>
                </div>
                {st.data.submitted_at && (
                  <div className="li">
                    <span className="small muted">{t('kyc.field_submitted_at')}</span>
                    <span className="small">{dt(st.data.submitted_at)}</span>
                  </div>
                )}
                {st.data.reviewed_at && (
                  <div className="li">
                    <span className="small muted">{t('kyc.field_reviewed_at')}</span>
                    <span className="small">{dt(st.data.reviewed_at)}</span>
                  </div>
                )}
                {st.data.review_note && (
                  <div className="li">
                    <span className="small muted">{t('kyc.field_review_note')}</span>
                    <span className="small">{st.data.review_note}</span>
                  </div>
                )}
              </div>
            )}
          </section>

          {status === 'pending' && (
            <section className="card card--flat stack">
              <p className="label">{t('kyc.status_pending')}</p>
              <p className="small muted" style={{ margin: 0 }}>
                {t('kyc.pending_note')}
              </p>
            </section>
          )}

          {status === 'approved' && (
            <section className="card card--flat stack">
              <p className="label">{t('kyc.approved_label')}</p>
              <p className="small muted" style={{ margin: 0 }}>
                {t('kyc.approved_note')}
              </p>
              <Link className="btn btn--primary btn--block" to="/wallet/withdraw">
                {t('kyc.go_withdraw')}
              </Link>
            </section>
          )}

          {(status === 'not_submitted' || status === 'rejected') && (
            <section className="stack">
              {status === 'rejected' && (
                <p className="err" style={{ margin: 0 }}>
                  {t('kyc.rejected_note')}
                </p>
              )}

              <form className="card card--flat stack" onSubmit={submit}>
                {formErr && (
                  <p className="err" role="alert">
                    {msgText(formErr)}
                  </p>
                )}
                {okMsg && <p className="small">{t(okMsg)}</p>}

                <label className="field">
                  <span>{t('kyc.field_real_name')}</span>
                  <input
                    className="input"
                    name="realName"
                    type="text"
                    autoComplete="name"
                    placeholder={t('kyc.real_name_ph')}
                    value={realName}
                    onChange={(e) => {
                      setRealName(e.target.value);
                      clearErr();
                    }}
                  />
                </label>

                <label className="field">
                  <span>{t('kyc.field_id_type')}</span>
                  <select
                    className="input"
                    name="idType"
                    value={idType}
                    onChange={(e) => {
                      setPickedType(e.target.value);
                      clearErr();
                    }}
                  >
                    {ID_TYPES.map((v) => (
                      <option key={v} value={v}>
                        {typeLabel(v)}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="field">
                  <span>{t('kyc.field_id_number')}</span>
                  <input
                    className="input mono"
                    name="idNumber"
                    type="text"
                    autoComplete="off"
                    placeholder={t('kyc.id_number_ph')}
                    value={idNumber}
                    onChange={(e) => {
                      setIdNumber(e.target.value);
                      clearErr();
                    }}
                  />
                </label>

                <label className="field">
                  <span>{t('kyc.field_country')}</span>
                  {/* 选项只有在册代码；不在列表里的旧值由 countryOptions 补一条，别让它被吞成空 */}
                  <select
                    className="input"
                    name="country"
                    value={country}
                    onChange={(e) => {
                      setCountry(e.target.value);
                      clearErr();
                    }}
                  >
                    <option value="">{noneLabel}</option>
                    {countryOptions(codes, country).map((c) => (
                      <option key={c} value={c}>
                        {c}
                      </option>
                    ))}
                  </select>
                </label>

                {PHOTOS.map((p) => (
                  <label className="field" key={p.key}>
                    <span>{t(p.label)}</span>
                    <input
                      className="input"
                      type="file"
                      accept={ACCEPT}
                      aria-label={t(p.label)}
                      onChange={(e) => void pick(p.key, e.target)}
                    />
                    {upBusy[p.key] ? (
                      <span className="small muted">{t('kyc.uploading')}</span>
                    ) : photo[p.key] ? (
                      <span className="small muted">{t('kyc.uploaded')}</span>
                    ) : null}
                    {preview[p.key] && (
                      <img
                        src={preview[p.key]}
                        alt={t('kyc.photo_preview_alt', { label: t(p.label) })}
                        style={{
                          marginTop: 8,
                          maxWidth: 220,
                          maxHeight: 150,
                          borderRadius: 10,
                          objectFit: 'cover',
                        }}
                      />
                    )}
                  </label>
                ))}

                <p className="small muted" style={{ margin: 0 }}>
                  {t('kyc.photo_hint')}
                </p>

                <button className="btn btn--primary btn--block" type="submit" disabled={busy}>
                  {busy ? t('app.submitting') : t('kyc.submit')}
                </button>
              </form>
            </section>
          )}
        </>
      )}
    </>
  );
}
