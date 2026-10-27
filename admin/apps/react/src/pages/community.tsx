/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 社群：分享裂变统计（GET /admin/v1/share/stats）。
 */
import { useState } from 'react';
import { Section } from '../components/Section';
import { Card } from '../components/ui';
import { t, useI18n } from '../i18n/index.ts';

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
