/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 社群：**组队/公会**（GET /admin/v1/groups + /groups/{hashid}/audit）与
 * **分享裂变统计**（GET /admin/v1/share/stats）—— 两个 M4 端点本树都没有入口。
 *
 * 都是只读：后端没有群组的增删改端点（成员由游戏侧写入），故配置里只给 `views`（审计弹框），
 * 不给 fields/labelKey —— 那会长出点了必然 404 的「新建/编辑/删除」按钮。
 */
import { useState } from 'react';
import { RowBrowser, type CrudConfig } from '../components/RowBrowser';
import { Section } from '../components/Section';
import { Card, Loading } from '../components/ui';
import { t, useI18n } from '../i18n/index.ts';
import { asRows } from '../components/AutoView';
import { useApi } from '../lib/hooks';
import { pick } from '../lib/format';

/** 群组成员审计视图：行尾按钮 → DetailModal 拉 `/groups/{hashid}/audit`（`{group, members}`）。 */
export const GROUPS_CRUD: CrudConfig = {
  base: '/admin/v1/groups',
  // 本配置没有 fields ⇒ 新建/编辑按钮不会渲染（RowBrowser 的 canEdit），noun 只是类型上必填
  noun: 'f.group',
  views: [{ label: 'f.audit', title: 'f.group_audit', path: (id) => `/admin/v1/groups/${id}/audit` }],
};

/**
 * 列**上限 8**（columnsFrom 的 max）：10 个字段里挑 8 个，否则「到期时间」会被挤掉
 * （真机实测：带满 10 个时表尾停在 Status，`expire_at` / `created_at` 直接看不见）。
 * 砍掉的是这条只读列表里最不参与决策的两个：`level`（派生的容量档位）与 `created_at`。
 */
const GROUP_COLUMNS = ['id', 'name', 'type', 'game_id', 'owner_id', 'member_count', 'status', 'expire_at'];

type Filters = { type: string; status: string; game_id: string };
const EMPTY: Filters = { type: '', status: '', game_id: '' };

/**
 * 群组列表 + 三个筛选（后端的 `type` / `status` / `game_id`，全部精确匹配）。
 * 游戏下拉取首页 200 条，与「区服」那组同一个口径（后台游戏基数是两位数，够用）——
 * `game_id` 必须是 hashid，手输不了，故只能给下拉。
 */
export function CommunityGroups() {
  useI18n();
  const [draft, setDraft] = useState<Filters>(EMPTY);
  const [applied, setApplied] = useState<Filters>(EMPTY);
  const { data, loading } = useApi<unknown>('/admin/v1/game/list', { limit: 200 });
  const games = asRows(data) ?? [];

  const set = (key: keyof Filters) => (event: { target: { value: string } }) =>
    setDraft((prev) => ({ ...prev, [key]: event.target.value }));

  return (
    <>
      <form
        className="toolbar"
        onSubmit={(event) => {
          event.preventDefault();
          setApplied(draft);
        }}
      >
        <label className="label">
          {t('f.type')}
          <select className="input" value={draft.type} onChange={set('type')}>
            <option value="">{t('community.any_type')}</option>
            <option value="team">{t('community.team')}</option>
            <option value="guild">{t('community.guild')}</option>
          </select>
        </label>
        <label className="label">
          {t('f.status')}
          <select className="input" value={draft.status} onChange={set('status')}>
            <option value="">{t('community.any_status')}</option>
            <option value="1">{t('community.status_on')}</option>
            <option value="0">{t('community.status_off')}</option>
          </select>
        </label>
        <label className="label">
          {t('nav.games')}
          {loading ? (
            <Loading rows={1} />
          ) : (
            <select className="input" value={draft.game_id} onChange={set('game_id')}>
              <option value="">{t('community.any_game')}</option>
              {games.map((game) => {
                const id = String(pick(game, ['id', 'game_id']) ?? '');
                return (
                  <option key={id} value={id}>
                    {String(game.name ?? game.game_name ?? id)}
                  </option>
                );
              })}
            </select>
          )}
        </label>
        <button className="btn" type="submit">
          {t('common.search')}
        </button>
        <button
          type="button"
          className="btn"
          onClick={() => {
            setDraft(EMPTY);
            setApplied(EMPTY);
          }}
        >
          {t('common.reset')}
        </button>
      </form>
      <RowBrowser key={JSON.stringify(applied)} path="/admin/v1/groups" query={applied} preferred={GROUP_COLUMNS} crud={GROUPS_CRUD} />
    </>
  );
}

/**
 * 分享统计：`{funnel:{shares,clicks,conversions}, daily:[{day,shares,clicks,conversions}]}`。
 * 无参调用即全量（后端只在给了 activity_id / from / to 时才加条件），故只留日期范围。
 */
export function ShareStats() {
  useI18n();
  const [draft, setDraft] = useState({ from: '', to: '' });
  const [applied, setApplied] = useState(draft);

  const set = (key: 'from' | 'to') => (event: { target: { value: string } }) =>
    setDraft((prev) => ({ ...prev, [key]: event.target.value }));

  return (
    <>
      <Card title={t('report.range')} sub={t('share.range_hint')}>
        <form
          className="toolbar"
          onSubmit={(event) => {
            event.preventDefault();
            setApplied(draft);
          }}
        >
          <label className="label">
            {t('common.start_date')}
            <input className="input" type="date" value={draft.from} onChange={set('from')} />
          </label>
          <label className="label">
            {t('common.end_date')}
            <input className="input" type="date" value={draft.to} onChange={set('to')} />
          </label>
          <button className="btn" type="submit">
            {t('common.search')}
          </button>
          <button
            type="button"
            className="btn"
            onClick={() => {
              setDraft({ from: '', to: '' });
              setApplied({ from: '', to: '' });
            }}
          >
            {t('common.reset')}
          </button>
        </form>
      </Card>
      <Section key={JSON.stringify(applied)} title={t('tab.share_stats')} path="/admin/v1/share/stats" query={applied} />
    </>
  );
}
