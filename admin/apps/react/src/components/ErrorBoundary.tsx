/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, type ErrorInfo, type ReactNode } from 'react';
import { t } from '../i18n/index.ts';

type Props = {
  children: ReactNode;
  /**
   * 变化时清掉错误态（Shell 传当前路由路径）。
   *
   * 为什么必须有：React 的错误边界**不会自己复位** —— 一次渲染异常之后它永远停在降级 UI 上，
   * 用户点侧栏换页面也回不来。而「别的页面还能用」正是这层护栏存在的理由。
   * 用状态复位而不是在边界上挂 `key`：`key` 会把整棵页面子树重挂（筛选条、分页、标签序号全丢）。
   */
  resetKey?: string;
};
type State = { error: unknown; key: string | undefined };

/**
 * 渲染异常护栏：一棵子树抛异常时降级成「这一屏挂了 + 重试」，而不是**整站白屏**。
 *
 * 为什么必须有（审计真机实测复现过）：`GET /admin/v1/dashboard` 回 `{stats:{}}`（键在、类型错）
 * 时 `Stats` 拿着一个对象去 `.map`，React 卸载整棵树 ⇒ `#root` 空、`body.innerText` 空、
 * 导航全没，控制台只剩一行 `items.map is not a function`。用户看到的是一片**纯白**，
 * 连「出错了」三个字都没有。
 *
 * 与形状校验（`lib/dashboard.ts` 的 `hasDashboardShape`）是两道不同的闸：那道只管仪表盘那一个
 * 端点的**数据形状**，这道兜住**所有**渲染期异常 —— 含懒加载的路由 chunk 拉不下来（断网/
 * 部署换了文件名）。这一层是最后一道，它挂住的时候整棵应用树已经没了。
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null, key: this.props.resetKey };

  static getDerivedStateFromError(error: unknown): Partial<State> {
    return { error };
  }

  static getDerivedStateFromProps(props: Props, state: State): Partial<State> | null {
    return props.resetKey === state.key ? null : { error: null, key: props.resetKey };
  }

  componentDidCatch(error: unknown, info: ErrorInfo): void {
    // 控制台留原文与组件栈：降级 UI 只说「挂了」，排查要的是堆栈（React 自己也会打，这里加个前缀好认）
    console.error('[admin-react] render error:', error, info.componentStack);
  }

  render(): ReactNode {
    if (this.state.error === null) return this.props.children;
    return (
      <div className="card" role="alert">
        <p>{t('app.render_failed')}</p>
        {/* 原文摆出来：运营截图给支持就能定位，比「未知错误」有用。渲染是转义过的，不是注入面 */}
        <p className="muted">{String((this.state.error as Error)?.message ?? this.state.error).slice(0, 300)}</p>
        <button type="button" className="btn btn-sm" onClick={() => window.location.reload()}>
          {t('common.retry')}
        </button>
      </div>
    );
  }
}
