/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { ApiError } from '../lib/api';
import {
  buildPayload,
  draftFrom,
  firstMissing,
  optionsWithCurrent,
  type Draft,
  type Field,
  type FieldOption,
} from '../lib/crud';
import { uploadImage } from '../lib/upload';
import { Modal } from './ui';

/**
 * 字段描述驱动的通用表单弹框，新建 / 编辑共用（编辑传 row：预填 + 只提交改动过的字段）。
 * 提交抛错即在框内显示服务端 message 且**不关框**，用户可改后重试；成功由调用方负责关框 + 刷新列表。
 *
 * fullEdit：编辑也按新建那套发全量（见 lib/crud.ts 的 buildPayload）—— 只给 update 与 create
 * 共用同一套必填校验的模块（风控规则），预填仍按 row 走，只是不再做「改动比较」。
 */
export function FormModal({
  title,
  fields,
  row,
  fullEdit,
  submitLabel,
  onSubmit,
  onClose,
}: {
  title: string;
  fields: Field[];
  row?: Record<string, unknown>;
  fullEdit?: boolean;
  submitLabel: string;
  onSubmit: (body: Record<string, unknown>) => Promise<void>;
  onClose: () => void;
}) {
  const [draft, setDraft] = useState<Draft>(() => draftFrom(fields, row));
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  // 正在上传的字段名（上传期间禁用那个字段的按钮）
  const [uploading, setUploading] = useState<string | null>(null);
  // 动态值域（权限树这类端点）：开框时拉一次，按字段名缓存。拉不到就只剩「当前值」一项，
  // 此时**必须说出来** —— 界面上看不见的选项，用户会当成「本来就没有」，从而把已有授权改没。
  const [loaded, setLoaded] = useState<Record<string, FieldOption[]>>({});

  useEffect(() => {
    let alive = true;
    for (const field of fields) {
      const load = field.options;
      if (typeof load !== 'function') continue;
      void load()
        .then((options) => {
          if (alive) setLoaded((prev) => ({ ...prev, [field.name]: options }));
        })
        .catch(() => {
          if (alive) setError(`${field.label}的选项加载失败，已选值仍可见，但不改它就别提交`);
        });
    }
    return () => {
      alive = false;
    };
  }, [fields]);

  /**
   * 上传一张图，把**绝对**展示 URL 写回字段值 —— 与手输 URL 走同一条路径（同一个 draft 字段），
   * 故「编辑态只发改动」的比较不用另立规则：上传结果与原值不同就会被发出去。
   * 失败原样显示服务端的 error（见 lib/upload.ts），不吞成自编文案。
   */
  const upload = async (field: Field, file: File) => {
    setUploading(field.name);
    setError(null);
    try {
      const url = await uploadImage(file, window.location.origin);
      setDraft((prev) => ({ ...prev, [field.name]: url }));
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : '网络异常，请稍后重试');
    } finally {
      setUploading(null);
    }
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (busy) return;
    const missing = firstMissing(fields, draft);
    if (missing !== null) {
      setError(missing);
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await onSubmit(buildPayload(fields, draft, row, fullEdit));
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : '网络异常，请稍后重试');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal title={title} onClose={onClose}>
      <form className="form form-cols" onSubmit={(event) => void submit(event)}>
        {fields.map((field) => (
          <label className={`label${isWide(field.type) ? ' wide' : ''}`} key={field.name}>
            {field.label}
            {field.required ? <span className="req"> *</span> : null}
            <Input
              field={withOptions(field, loaded)}
              value={draft[field.name] ?? ''}
              onChange={(value) => setDraft((prev) => ({ ...prev, [field.name]: value }))}
              uploading={uploading === field.name}
              onUpload={(file) => void upload(field, file)}
            />
            {field.hint ? <span className="muted hint">{field.hint}</span> : null}
          </label>
        ))}
        {error ? (
          <p className="errnote wide" role="alert">
            {error}
          </p>
        ) : null}
        <button className="btn wide" type="submit" disabled={busy}>
          {busy ? '提交中…' : submitLabel}
        </button>
      </form>
    </Modal>
  );
}

/** 动态值域的字段：把已拉到的选项贴回去，控件与 optionsWithCurrent 只认数组。 */
const withOptions = (field: Field, loaded: Record<string, FieldOption[]>): Field =>
  typeof field.options === 'function' ? { ...field, options: loaded[field.name] ?? [] } : field;

/** 多行控件占满整行（表单是两列布局）。 */
const isWide = (type: Field['type']): boolean =>
  type === 'textarea' || type === 'json' || type === 'jsonobj' || type === 'lines' || type === 'multi';

/** 多选的已选值：可能是库里的旧值（不在当前值域里）—— 逐个补成选项，否则控件会把它当没选上。 */
const withCurrent = (field: Field, values: string[]): FieldOption[] => {
  const options = Array.isArray(field.options) ? field.options : [];
  const extra = values
    .filter((value) => !options.some((option) => option.value === value))
    .map((value) => ({ value, label: `${value}（当前值）` }));
  return [...extra, ...options];
};

/** 按字段类型选控件；值一律字符串（switch 用 '1'/'0'）。 */
function Input({
  field,
  value,
  onChange,
  uploading,
  onUpload,
}: {
  field: Field;
  value: string;
  onChange: (value: string) => void;
  /** image 字段：正在上传（按钮转文案并禁用） */
  uploading?: boolean;
  /** image 字段：选好文件（由 FormModal 走上传流程） */
  onUpload?: (file: File) => void;
}) {
  // 文件选择器藏起来由「上传」按钮代点：外层已经是 <label>，label 套 label 不合法，
  // 而 label 会把整行都变成触发区（点一下字段名就弹文件框）
  const file = useRef<HTMLInputElement>(null);

  // 图片：保留文本框（存量值是手输 URL / 图标名）＋ 上传按钮 ＋ 缩略图。
  // 展示路由公开，缩略图直接 <img src>，不带鉴权头
  if (field.type === 'image') {
    return (
      <span className="imgfield">
        <span className="imgrow">
          <input
            className="input"
            type="text"
            value={value}
            disabled={field.readOnly}
            placeholder={field.placeholder}
            onChange={(event) => onChange(event.target.value)}
          />
          <button type="button" className="btn btn-sm" disabled={field.readOnly || uploading} onClick={() => file.current?.click()}>
            {uploading ? '上传中…' : '上传'}
          </button>
        </span>
        <input
          ref={file}
          type="file"
          accept="image/*"
          hidden
          onChange={(event) => {
            const picked = event.target.files?.[0];
            // 清空选择：连选同一个文件也要再触发一次 change
            event.target.value = '';
            if (picked) onUpload?.(picked);
          }}
        />
        {value ? <img className="thumb" src={value} alt="预览" /> : null}
      </span>
    );
  }

  // json / jsonobj / lines 与 textarea 同形：json 原样上送（服务端 json_decode 校验）、
  // jsonobj 提交时解成对象、lines 每行一个值转数组
  if (field.type === 'textarea' || field.type === 'json' || field.type === 'jsonobj' || field.type === 'lines') {
    return (
      <textarea
        className="input"
        rows={field.type === 'textarea' ? 4 : 6}
        value={value}
        disabled={field.readOnly}
        placeholder={field.placeholder}
        onChange={(event) => onChange(event.target.value)}
      />
    );
  }

  // 多选：值不进 DOM（<select multiple> 的 value 是数组），故仍以「一行一个值」的字符串往返
  if (field.type === 'multi') {
    const values = value === '' ? [] : value.split('\n');
    return (
      <select
        className="input"
        multiple
        size={10}
        value={values}
        disabled={field.readOnly}
        onChange={(event) => onChange([...event.target.selectedOptions].map((option) => option.value).join('\n'))}
      >
        {withCurrent(field, values).map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    );
  }

  if (field.type === 'select') {
    return (
      <select
        className="input"
        value={value}
        disabled={field.readOnly}
        onChange={(event) => onChange(event.target.value)}
      >
        <option value="">请选择</option>
        {optionsWithCurrent(field, value).map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    );
  }

  if (field.type === 'switch') {
    return (
      <input
        className="switch"
        type="checkbox"
        checked={value === '1'}
        disabled={field.readOnly}
        onChange={(event) => onChange(event.target.checked ? '1' : '0')}
      />
    );
  }

  return (
    <input
      className="input"
      type={field.type === 'number' ? 'number' : 'text'}
      value={value}
      disabled={field.readOnly}
      placeholder={field.placeholder}
      onChange={(event) => onChange(event.target.value)}
    />
  );
}
