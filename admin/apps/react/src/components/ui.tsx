/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useEffect, useRef, type KeyboardEvent as ReactKeyboardEvent, type ReactNode } from 'react';
import { t } from '../i18n/index.ts';
import { focusables, trapTab } from '../lib/focus-trap.ts';

export type Tone = 'primary' | 'amber' | 'success' | 'danger' | 'muted';

export function PageHead({ title, sub, children }: { title: string; sub?: string; children?: ReactNode }) {
  return (
    <header className="pagehead">
      <div className="pagehead-t">
        <h1 className="h1">{title}</h1>
        {sub ? <p className="sub muted">{sub}</p> : null}
      </div>
      {children ? <div className="pagehead-a">{children}</div> : null}
    </header>
  );
}

export function Card({
  title,
  sub,
  actions,
  children,
}: {
  title?: string;
  sub?: string;
  actions?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section className="card">
      {title || actions ? (
        <div className="card-h">
          <div>
            {title ? <h2 className="h2">{title}</h2> : null}
            {sub ? <p className="sub muted">{sub}</p> : null}
          </div>
          {actions ? <div className="card-a">{actions}</div> : null}
        </div>
      ) : null}
      <div className="card-b">{children}</div>
    </section>
  );
}

/**
 * 统计块。`icon` / `color` 是给仪表盘那种「后端带图标与强调色」的卡片用的：
 * 图标是装饰（语义由 `label` 承载，故 `aria-hidden`），`color` 只落在图标上 ——
 * 后端给的十六进制当大面积底色会跟令牌体系打架（暗色下尤其）。
 */
export function Stat({
  label,
  value,
  hint,
  tone,
  icon,
  color,
}: {
  label: string;
  value: ReactNode;
  hint?: ReactNode;
  tone?: Tone;
  icon?: ReactNode;
  color?: string;
}) {
  return (
    <div className={`stat${tone ? ` t-${tone}` : ''}`}>
      {icon ? (
        <span className="stat-i" style={color ? { color } : undefined} aria-hidden="true">
          {icon}
        </span>
      ) : null}
      <span className="stat-l">{label}</span>
      <span className="stat-v">{value}</span>
      {hint ? <span className="stat-h">{hint}</span> : null}
    </div>
  );
}

// 缺省值在**每次调用**时求值（不是模块求值期），故这里现取译文是活的；调用方传的是成品文案
export function Loading({ rows = 3, label = t('app.loading') }: { rows?: number; label?: string }) {
  return (
    <div className="skel" role="status" aria-label={label}>
      {Array.from({ length: rows }, (_unused, index) => (
        <span key={index} className="skel-row" />
      ))}
    </div>
  );
}

export function Empty({ text = t('app.no_data') }: { text?: string }) {
  return (
    <div className="empty">
      {/* 吉祥物小骰（Dicey）：纯装饰，语义由下面的文案承载；随 BASE_URL 走子路径部署 */}
      <img className="empty-mark" src={`${import.meta.env.BASE_URL}mascot.svg`} alt="" aria-hidden="true" />
      <span>{text}</span>
    </div>
  );
}

/**
 * 操作结果提示条。tone 缺省 error（服务端拒绝的原话，红框）；
 * 成功类动作（打款/审核通过）传 `ok` —— 把「打款成功」摆进红框会让人以为出了事。
 */
export function ErrorNote({
  message,
  tone = 'error',
  onRetry,
}: {
  message: string;
  tone?: 'error' | 'ok';
  onRetry?: () => void;
}) {
  return (
    <div className={`errnote${tone === 'ok' ? ' ok' : ''}`} role="alert">
      <span className="errnote-t">{message}</span>
      {onRetry ? (
        <button type="button" className="btn btn-sm" onClick={onRetry}>
          {t('common.retry')}
        </button>
      ) : null}
    </div>
  );
}

export function Badge({ children, tone = 'muted' }: { children: ReactNode; tone?: Tone }) {
  return <span className={`badge b-${tone}`}>{children}</span>;
}

export function Tabs({
  tabs,
  value,
  onChange,
}: {
  tabs: { key: string; label: string }[];
  value: string;
  onChange: (key: string) => void;
}) {
  // 无 role/aria：这组按钮**没有** tab 语义（全树没有 tabpanel，也没有 roving tabindex / 方向键），
  // 挂 `role="tablist"` + `role="tab"` + `aria-selected` 是**假语义** —— 读屏会把它们播成「选项卡 2/5」
  // 并期待 Tab 键/方向键按 tabpanel 规矩走，而实际只是普通按钮。退化成诚实的按钮组，标注选中态用
  // 视觉类 `.on`（angular 那棵的 ui-tabs 同款）。
  return (
    <div className="tabs">
      {tabs.map((tab) => (
        <button
          key={tab.key}
          type="button"
          aria-pressed={tab.key === value}
          className={`tab${tab.key === value ? ' on' : ''}`}
          onClick={() => onChange(tab.key)}
        >
          {tab.label}
        </button>
      ))}
    </div>
  );
}

/**
 * 分页条（形态照 angular 那棵的 ui-pager）：共 N 条 · 第 X/Y 页 + 上一页/下一页。
 * 只有「真翻得动」的列表才画（见 RowBrowser）：整表端点与树/裸数组没有 total，画上去是骗人。
 */
export function Pager({
  page,
  pages,
  total,
  onJump,
}: {
  page: number;
  pages: number;
  total: number;
  onJump: (page: number) => void;
}) {
  return (
    <div className="pager">
      <span>{t('table.pager', { total, page, pages })}</span>
      <button type="button" className="btn btn-sm" disabled={page <= 1} onClick={() => onJump(page - 1)}>
        {t('table.prev')}
      </button>
      <button type="button" className="btn btn-sm" disabled={page >= pages} onClick={() => onJump(page + 1)}>
        {t('table.next')}
      </button>
    </div>
  );
}

export function Field({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="field">
      <span className="field-l">{label}</span>
      <span className="field-v">{value}</span>
    </div>
  );
}

/**
 * body 滚动锁的**嵌套计数**。多框叠加（详情框里再开一个）时内层先关不能把外层的锁解掉，
 * 故只在 0↔1 的跨界写 style，中间层进出都不动它。StrictMode 的双跑也自洽：
 * 挂载(+1，写 hidden) → 清理(-1，写 '') → 再挂载(+1，写 hidden)。
 */
let locks = 0;

export function Modal({
  title,
  onClose,
  children,
  size,
}: {
  title: string;
  onClose: () => void;
  children: ReactNode;
  /**
   * `'sm'` = 贴合内容的小弹框。默认 `.modal` 是 `min(760px, 100%)`，表单/详情/导入报表需要它；
   * 登录验证码只有一张 **原生 300×200** 的画布，而 `.cap-img` 是 `width:100%` ⇒ 跟着容器走，
   * 760px 弹框会把它拉伸到 **2.4 倍**（既占满屏又糊）。给小尺寸后弹框 340px、内容 300px
   * ⇒ 画布 1:1。**别把默认值改小**，那会连累另外三个调用点。
   */
  size?: 'sm';
}) {
  const box = useRef<HTMLDivElement>(null);

  /**
   * 开框：记下**打开它的那个元素**、把焦点移进框内；关框/卸载再还回去。
   * 读 opener 与聚焦必须在同一个 effect 里且**读在前** —— 顺序反了就永远还给自己。
   * StrictMode 的双跑也靠这个顺序自愈：第二次挂载读到的仍是打开者（第一次已还回去）。
   */
  useEffect(() => {
    const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (box.current) focusables(box.current)[0]?.focus();
    // opener 已随视图移除时 focus() 是空操作（路由切走也走这条），不报错
    return () => opener?.focus();
  }, []);

  /**
   * 开框即锁背景滚动：`.backdrop` 是 position:fixed，弹框本身不滚动，滚轮事件会穿透到背后的
   * 列表上（内容一动，弹框看着跟着晃）。**独立的一个 effect** —— 上面那条的清理函数被
   * modal-focus.guard.test.ts 逐字钉着（焦点归还），别把锁混进去。
   */
  useEffect(() => {
    locks += 1;
    document.body.style.overflow = 'hidden';
    return () => {
      locks -= 1;
      if (locks === 0) document.body.style.overflow = '';
    };
  }, []);

  /**
   * Esc / Tab 都挂在**宿主元素**上而不是 window：套两层框时只有焦点所在那层会收到
   * （angular 的 ModalFocus 同一口径）。前提是焦点在框内 —— 由上面的 effect 保证。
   *
   * 宿主那行 tabindex=-1 是这条前提的补丁：容器自身**可程序化聚焦**（不进 tab 序），
   * 点框内非可聚焦区时浏览器把焦点交给它而不是 BODY ⇒ 键盘事件仍在框内，宿主级 Esc 不哑，
   * 从它按 Tab 也只落回框内（不再是一次点击就把陷阱击穿）。
   */
  const onKey = (event: ReactKeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      onClose();
      return;
    }
    if (event.key !== 'Tab' || !box.current) return;
    const next = trapTab(box.current, document.activeElement, event.shiftKey);
    if (!next) return; // 圈内中间元素之间：不抢，交给浏览器
    event.preventDefault();
    next.focus();
  };

  return (
    <div className="backdrop" onClick={onClose} role="presentation">
      <div
        ref={box}
        className={size === 'sm' ? 'modal modal-sm' : 'modal'}
        role="dialog"
        aria-modal="true"
        aria-label={title}
        tabIndex={-1}
        onKeyDown={onKey}
        onClick={(event) => event.stopPropagation()}
      >
        <div className="modal-h">
          <h2 className="h2">{title}</h2>
          <button type="button" className="btn btn-sm" onClick={onClose} aria-label={t('app.close')}>
            {t('app.close')}
          </button>
        </div>
        <div className="modal-b">{children}</div>
      </div>
    </div>
  );
}
