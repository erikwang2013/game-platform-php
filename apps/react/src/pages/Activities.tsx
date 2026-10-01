/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiError, api, type Activity, type ActivityProgress, type ActivityReward } from '../lib/api.ts';
import { dt } from '../lib/datetime.ts';
import { useAsync } from '../lib/hooks.ts';
import { ErrorBox, Loading } from '../components/States.tsx';

/**
 * 运营活动：列表 + 今日进度 + 签到领奖。
 *
 * 口径（与 angular 那棵一致，两处按后端实况做了修正）：
 *  - **只发两个请求**（list + progress）后在客户端按 activity_id 合并，**不逐条调
 *    `/activities/{hashid}`** —— 那条 detail 回的 participation 与 progress 同源，逐个拉就是 N+1；
 *  - 签到后**只重拉 progress**（活动定义没变），**绝不在前端自己累加进度/余额**：
 *    奖励是真钱，服务端在同一事务里写 reward_log + WalletService::mutate，客户端只认回包；
 *  - `config` 刻意不渲染（按 type 各自 schema，猜字段名等于编造），目标值取 progress 的 `target`。
 *
 * ⚠ 两处**修正 angular 口径**的地方，都出自服务端读码，不是口味：
 *  1. `ActivityService::checkin` 的 `canJoin` 是**与 type 无关**的，`ctx` 里 `game_id` 恒 0
 *     （ActivityController:154 只塞了 event/game_id/now）⇒ 绑了具体游戏的活动
 *     （`activity.game_id !== 0`）**必然 400 'Activity not available'**，按钮点了必失败。
 *  2. `invite` 类还多一条 `ctx['activity_id'] > 0 && activity.id === activity_id`，
 *     而 ctx 里**根本没有 activity_id** ⇒ invite 类**永远 400**（它的进度本来就该由
 *     注册事件写，见 ShareLink::bindConversion）。angular 给 invite 也摆了「领取」按钮。
 *  3. `daily_task` 的进度语义是「消费 deposit.completed 等事件累加」，而 `checkin` 每调一次
 *     就 `current += 1` 且建行时 `target` 硬编码为 1 ⇒ 点一下即达标发奖，**不需要真去做任务**。
 *     这是服务端的设计缺口（已上报，未改后端）。本页**不给它按钮**，不去利用。
 */
const TYPE_LABEL: Record<string, string> = {
  signin: '每日签到',
  daily_task: '每日任务',
  invite: '邀请活动',
};

/** 从没参与过的活动不会出现在 /progress 里，补一条零值而不是显示 undefined */
const NONE: ActivityProgress = { activity_id: '', current: 0, target: 0, status: 'progressing' };

/**
 * 能不能签到。返回空串 = 可点；否则是**不可点的原因**（不是错误提示）。
 * 判据见文件头注释；三条都是「点了服务端必拒」或「点了能白拿」。
 */
function blockReason(a: Activity): string {
  if (a.type !== 'signin') {
    return a.type === 'invite' ? '按好友注册自动累计' : '按任务条件自动累计';
  }
  // ⚠ 服务端只在 game_id>0 时才编码，故此字段要么是字面量 0、要么是 hashid 字符串
  if (a.game_id !== null && String(a.game_id) !== '0' && a.game_id !== '') {
    return '请在对应游戏内完成';
  }
  return '';
}

/** 奖励条目的 type 是币种，不是流水类型 */
const coinLabel = (t: string) =>
  t === 'platform_coin' ? '平台币' : t === 'game_coin' ? '游戏币' : t;

function rewardText(list: ActivityReward[]): string {
  if (list.length === 0) return '已达标（本次没有可发放的奖励）';
  return list.map((r) => `${coinLabel(r.type)} ${r.amount}`).join('、');
}

export function Activities() {
  /**
   * 首屏两个请求**并发**（`Promise.all`，等价于 angular 的 forkJoin），按 activity_id 合并。
   * 用现成的 useAsync 拿三态，不自己再写一遍 effect+setState。
   */
  const first = useAsync(() => Promise.all([api.activities(), api.activityProgress()]), []);
  const list: Activity[] = first.data?.[0].list ?? [];

  /**
   * 签到后只重拉 progress（活动定义没变）：把新进度放覆盖层，
   * 而不是重拉整个 Promise.all —— 否则每签一次都白拉一遍 list。
   */
  const [progAfter, setProgAfter] = useState<ActivityProgress[] | null>(null);
  const prog = useMemo(
    () => new Map((progAfter ?? first.data?.[1].list ?? []).map((x) => [x.activity_id, x])),
    [progAfter, first.data],
  );

  const [busyId, setBusyId] = useState<string | null>(null);
  const [rowErr, setRowErr] = useState<{ id: string; msg: string } | null>(null);
  const [note, setNote] = useState<string | null>(null);
  const [reward, setReward] = useState<ActivityReward[] | null>(null);

  /** 手动重载：连覆盖层一起清掉，否则会拿旧进度盖在新 list 上 */
  const load = () => {
    setProgAfter(null);
    setNote(null);
    setReward(null);
    setRowErr(null);
    first.reload();
  };

  const checkin = async (a: Activity) => {
    if (busyId) return;
    setBusyId(a.id);
    setRowErr(null);
    setNote(null);
    setReward(null);
    try {
      const r = await api.activityCheckin(a.id);
      if (r.status === 'rewarded') setReward(r.reward ?? []);
      else if (r.status === 'already') setNote('今天已经领过了，明天再来。');
      else setNote('已记录，还没达到目标，继续加油。');
      try {
        const p = await api.activityProgress();
        setProgAfter(p.list ?? []);
      } catch {
        // 进度刷新失败**不清空**已有展示，下次刷新会补上
      }
    } catch (e) {
      setRowErr({ id: a.id, msg: e instanceof ApiError ? e.message : '操作失败，请稍后重试' });
    } finally {
      setBusyId(null);
    }
  };

  const pct = (p: ActivityProgress) =>
    !p.target || p.target <= 0 ? 0 : Math.min(100, Math.round((p.current / p.target) * 100));

  const progressText = (p: ActivityProgress) => {
    if (p.status === 'rewarded') return '今日已完成';
    if (!p.target || p.target <= 0) return '今日还没开始';
    return `今日进度 ${p.current} / ${p.target}`;
  };

  const statusLabel = (p: ActivityProgress) => {
    if (p.status === 'rewarded') return '已领取';
    if (p.status === 'completed') return '已达标';
    return !p.target || p.target <= 0 ? '未开始' : '进行中';
  };

  return (
    <>
      <section className="stack">
        <p className="label">运营活动</p>
        <h1 className="h1">
          活动
          <span style={{ color: 'var(--orange)' }}>.</span>
        </h1>
        <p className="small muted" style={{ margin: 0 }}>
          签到与任务进度按自然日重置。奖励由服务端直接发放到钱包，可在流水中查看。
        </p>
      </section>

      {reward && (
        <p className="card card--flat" role="status" style={{ margin: 0 }}>
          已发放：{rewardText(reward)}（可在钱包流水中查看）
        </p>
      )}
      {note && (
        <p className="card card--flat" role="status" style={{ margin: 0 }}>
          {note}
        </p>
      )}

      <section className="stack">
        {first.loading && <Loading />}
        {!first.loading && first.error && <ErrorBox message={first.error} onRetry={load} />}
        {!first.loading && !first.error && list.length === 0 && (
          <p className="card card--flat" style={{ margin: 0 }}>
            暂无可参与的活动 —— 平台还没投放，或当前账号不在灰度范围内。
          </p>
        )}

        {!first.loading &&
          !first.error &&
          list.map((a) => {
            const p = prog.get(a.id) ?? NONE;
            const reason = blockReason(a);
            const busy = busyId === a.id;
            const done = p.status === 'rewarded';
            return (
              <div className="card stack" key={a.id}>
                <div className="row" style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                  <div style={{ minWidth: 0 }}>
                    <p style={{ margin: 0, fontWeight: 700 }}>{a.name}</p>
                    <p className="small muted" style={{ margin: '4px 0 0' }}>
                      {TYPE_LABEL[a.type] ?? a.type}
                      {a.end_at ? ` · 截止 ${dt(a.end_at)}` : ''}
                      {/* game_id 是 hashid 才可跳；字面量 0 = 全平台，没有对应游戏页 */}
                      {a.game_id !== null && String(a.game_id) !== '0' && a.game_id !== '' && (
                        <>
                          {' · '}
                          <Link to={`/game/${a.game_id}`}>去玩对应游戏</Link>
                        </>
                      )}
                    </p>
                  </div>
                  <span className="pill pill--plain">{statusLabel(p)}</span>
                </div>

                <div className="prog" aria-hidden="true">
                  <i style={{ width: `${pct(p)}%` }} />
                </div>

                <div className="row" style={{ justifyContent: 'space-between', alignItems: 'center' }}>
                  <span className="small muted">{progressText(p)}</span>
                  {reason ? (
                    <span className="small muted">{reason}</span>
                  ) : (
                    <button
                      type="button"
                      className="btn btn--sm btn--primary"
                      disabled={busy || done}
                      onClick={() => void checkin(a)}
                    >
                      {busy && <span className="spin" aria-hidden="true" />}
                      {done ? '今日已领' : a.type === 'signin' ? '签到' : '领取'}
                    </button>
                  )}
                </div>

                {rowErr && rowErr.id === a.id && (
                  <p className="err" role="alert" style={{ margin: 0 }}>
                    {rowErr.msg}
                  </p>
                )}
              </div>
            );
          })}
      </section>
    </>
  );
}
