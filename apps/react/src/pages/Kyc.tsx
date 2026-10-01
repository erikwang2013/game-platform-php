/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { ApiError, api, type IdentityStatusValue } from '../lib/api.ts';
import { useAsync } from '../lib/hooks.ts';
import { dt } from '../lib/datetime.ts';
import { ACCEPT, MAX_BYTES, uploadImage } from '../lib/upload.ts';
import { ErrorBox, Loading } from '../components/States.tsx';

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
const TYPE_LABEL: Record<string, string> = {
  id_card: '身份证',
  passport: '护照',
  driver_license: '驾照',
};

/** 真值见 `IdentityController::apply` 的 validator 白名单（in:id_card,passport,driver_license） */
const ID_TYPES = ['id_card', 'passport', 'driver_license'] as const;

/** 未提交时服务端只回 status 一个字段；这四种之外的值原样透出，不猜 */
const STATUS_LABEL: Record<string, string> = {
  not_submitted: '未提交',
  pending: '审核中',
  approved: '已认证',
  rejected: '已驳回',
};

const STATUS_PILL: Record<string, string> = {
  not_submitted: 'pill--plain',
  pending: 'pill--plain',
  approved: 'pill--yellow',
  rejected: 'pill--orange',
};

/** 服务端 validator 里只有 id_back_photo 是 nullable，另外两张必填 */
const PHOTOS = [
  { key: 'id_front_photo', label: '证件正面照' },
  { key: 'id_back_photo', label: '证件背面照（可选）' },
  { key: 'selfie_photo', label: '手持自拍照' },
] as const;

const typeLabel = (t?: string) => (t ? (TYPE_LABEL[t] ?? t) : '—');

export function Kyc() {
  const st = useAsync(() => api.identityStatus(), []);

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
  const [formErr, setFormErr] = useState('');
  const [okMsg, setOkMsg] = useState('');

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

  const clearErr = () => setFormErr('');

  /** 选图 → 先本地预览，再直传；失败只清掉这一张，不影响已传成功的 */
  const pick = async (key: string, input: HTMLInputElement) => {
    const file = input.files?.[0];
    input.value = ''; // 允许同一张图重选（不清则 change 不触发）
    if (!file) return;

    if (file.size > MAX_BYTES) {
      setFormErr('图片超过 5MB，请压缩后再试');
      return;
    }
    setFormErr('');
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
      setFormErr(e instanceof Error ? e.message : '上传失败，请重试');
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
    if (!name) return setFormErr('请填写真实姓名');
    if (!num) return setFormErr('请填写证件号码');
    if (Object.values(upBusy).some(Boolean)) return setFormErr('照片仍在上传中，请稍候');
    if (!front) return setFormErr('请上传证件正面照');
    if (!selfie) return setFormErr('请上传手持自拍照');

    setBusy(true);
    setFormErr('');
    setOkMsg('');
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
      setOkMsg('已提交，等待审核');
      // 回读真状态，不凭「请求成功」宣布结果
      st.reload();
    } catch (err) {
      setFormErr(err instanceof ApiError ? err.message : '提交失败，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  const status: IdentityStatusValue | undefined = st.data?.status;

  return (
    <>
      <section className="stack">
        <p className="label">账户</p>
        <h1 className="h1">
          实名认证
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
      </section>

      {st.loading && <Loading />}
      {!st.loading && st.error && <ErrorBox message={st.error} onRetry={st.reload} />}

      {!st.loading && !st.error && st.data && (
        <>
          <section className="card card--flat stack">
            <div className="between">
              <span className="small muted">当前状态</span>
              <span className={`pill ${STATUS_PILL[status ?? ''] ?? 'pill--plain'}`}>
                {STATUS_LABEL[status ?? ''] ?? status}
              </span>
            </div>
            <p className="small muted" style={{ margin: 0 }}>
              认证通过后提现额度按<b>已认证档</b>计（更高单笔/日/月额度、更低费率）；未认证或审核中按默认档计。
              证件信息仅用于合规审核，不会对外展示。
            </p>
            {status !== 'not_submitted' && (
              <div className="list">
                <div className="li">
                  <span className="small muted">姓名</span>
                  <span className="small">{st.data.real_name || '—'}</span>
                </div>
                <div className="li">
                  <span className="small muted">证件类型</span>
                  <span className="small">{typeLabel(st.data.id_type)}</span>
                </div>
                {st.data.submitted_at && (
                  <div className="li">
                    <span className="small muted">提交时间</span>
                    <span className="small">{dt(st.data.submitted_at)}</span>
                  </div>
                )}
                {st.data.reviewed_at && (
                  <div className="li">
                    <span className="small muted">审核时间</span>
                    <span className="small">{dt(st.data.reviewed_at)}</span>
                  </div>
                )}
                {st.data.review_note && (
                  <div className="li">
                    <span className="small muted">审核备注</span>
                    <span className="small">{st.data.review_note}</span>
                  </div>
                )}
              </div>
            )}
          </section>

          {status === 'pending' && (
            <section className="card card--flat stack">
              <p className="label">审核中</p>
              <p className="small muted" style={{ margin: 0 }}>
                通常 1-2 个工作日出结果，通过后提现额度自动升级，无需再操作。
              </p>
            </section>
          )}

          {status === 'approved' && (
            <section className="card card--flat stack">
              <p className="label">已完成认证</p>
              <p className="small muted" style={{ margin: 0 }}>
                你的提现按已认证档计。
              </p>
              <Link className="btn btn--primary btn--block" to="/wallet/withdraw">
                去提现
              </Link>
            </section>
          )}

          {(status === 'not_submitted' || status === 'rejected') && (
            <section className="stack">
              {status === 'rejected' && (
                <p className="err" style={{ margin: 0 }}>
                  上次提交被驳回，可按上面的驳回原因修改后重新提交（会覆盖原记录）。
                </p>
              )}

              <form className="card card--flat stack" onSubmit={submit}>
                {formErr && (
                  <p className="err" role="alert">
                    {formErr}
                  </p>
                )}
                {okMsg && <p className="small">{okMsg}</p>}

                <label className="field">
                  <span>真实姓名</span>
                  <input
                    className="input"
                    name="realName"
                    type="text"
                    autoComplete="name"
                    placeholder="与证件一致"
                    value={realName}
                    onChange={(e) => {
                      setRealName(e.target.value);
                      clearErr();
                    }}
                  />
                </label>

                <label className="field">
                  <span>证件类型</span>
                  <select
                    className="input"
                    name="idType"
                    value={idType}
                    onChange={(e) => {
                      setPickedType(e.target.value);
                      clearErr();
                    }}
                  >
                    {ID_TYPES.map((t) => (
                      <option key={t} value={t}>
                        {TYPE_LABEL[t]}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="field">
                  <span>证件号码</span>
                  <input
                    className="input mono"
                    name="idNumber"
                    type="text"
                    autoComplete="off"
                    placeholder="证件上的号码"
                    value={idNumber}
                    onChange={(e) => {
                      setIdNumber(e.target.value);
                      clearErr();
                    }}
                  />
                </label>

                <label className="field">
                  <span>国家/地区（可选）</span>
                  <input
                    className="input"
                    name="country"
                    type="text"
                    autoComplete="country-name"
                    placeholder="如 CN"
                    value={country}
                    onChange={(e) => {
                      setCountry(e.target.value);
                      clearErr();
                    }}
                  />
                </label>

                {PHOTOS.map((p) => (
                  <label className="field" key={p.key}>
                    <span>{p.label}</span>
                    <input
                      className="input"
                      type="file"
                      accept={ACCEPT}
                      aria-label={p.label}
                      onChange={(e) => void pick(p.key, e.target)}
                    />
                    {upBusy[p.key] ? (
                      <span className="small muted">上传中…</span>
                    ) : photo[p.key] ? (
                      <span className="small muted">已上传</span>
                    ) : null}
                    {preview[p.key] && (
                      <img
                        src={preview[p.key]}
                        alt={`${p.label}预览`}
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
                  支持 jpg / png / gif / webp，单张不超过 5MB。
                </p>

                <button className="btn btn--primary btn--block" type="submit" disabled={busy}>
                  {busy ? '提交中…' : '提交认证'}
                </button>
              </form>
            </section>
          )}
        </>
      )}
    </>
  );
}
