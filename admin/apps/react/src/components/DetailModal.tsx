/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { useApi } from '../lib/hooks';
import { AutoView } from './AutoView';
import { ErrorNote, Loading, Modal } from './ui';

/** 行详情弹窗：按 hashid 拉单条记录，形状未确认时交给 AutoView。 */
export function DetailModal({ path, title, onClose }: { path: string; title: string; onClose: () => void }) {
  const { data, loading, error, reload } = useApi<unknown>(path);
  return (
    <Modal title={title} onClose={onClose}>
      {loading ? <Loading rows={5} /> : error ? <ErrorNote message={error} onRetry={reload} /> : <AutoView data={data} />}
    </Modal>
  );
}
