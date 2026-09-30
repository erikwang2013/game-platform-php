/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { describe, expect, it } from 'vitest';
import { Field, payload } from './crud';
import { json } from './render';

const F: Field[] = [
  { name: 'name', label: '名称', type: 'text' },
  { name: 'status', label: '状态', type: 'switch' },
  { name: 'sort', label: '排序', type: 'number' },
  { name: 'api_key', label: 'API Key', type: 'text', keepIfEmpty: true },
];

describe('payload（表单值 → 请求体）', () => {
  it('新建：switch 落 0/1，number 空值不提交，未回的字段不出现', () => {
    const body = payload(F, {}, { name: 'a', status: 1, sort: '', api_key: 'k' });
    expect(body).toEqual({ name: 'a', status: 1, api_key: 'k' });
  });

  it('编辑：与旧值相同的字段不提交（局部更新，避免未回显的密钥被空串清空）', () => {
    const old = { name: 'a', status: 1, sort: 3, api_key: 'secret' };
    const body = payload(F, old, { name: 'a', status: 1, sort: '3', api_key: '' });
    expect(body).toEqual({});
  });

  /**
   * 后端 create 的 status 默认值是 1（AnnouncementController.php:75、AchievementController.php:56）
   * ⇒ 新建时「关掉的开关」若被当成「没改」丢掉，就成了「建成即已启用」。
   * switch 没有未设置态，所以 create 恒发；编辑态照旧同值不发（第二行是防回退的对照组）。
   */
  it('新建：关掉的 switch（0）必须提交，不能被判成「没改」', () => {
    expect(payload(F, {}, { name: 'a', status: 0 })).toEqual({ name: 'a', status: 0 });
    expect(payload(F, { name: 'a', status: 0, sort: null }, { name: 'a', status: 0 })).toEqual({});
  });

  it('编辑：改了的字段才提交，number 转数字、switch 转 0/1', () => {
    const old = { name: 'a', status: 1, sort: 3, api_key: 'secret' };
    const body = payload(F, old, { name: 'b', status: 0, sort: '7', api_key: 'secret' });
    expect(body).toEqual({ name: 'b', status: 0, sort: 7 });
  });

  it('编辑：清空可空字段是真改动（提交空串），但 keepIfEmpty 字段留空一律跳过', () => {
    const old = { name: 'a', status: 0, sort: null, api_key: 'secret' };
    const body = payload(F, old, { name: '', status: 0, sort: '', api_key: '' });
    expect(body).toEqual({ name: '' });
  });

  /**
   * JSON 列（activity.config 等）读回来是数组/对象，表单预填是 json() 文本。
   * 两侧必须用同一个序列化器才判得等：判等失败的话每编辑一次都会把格式化过的
   * JSON 原样回写一遍（旧实现 norm 对 object 直接返回 undefined ⇒ 永远不等 ⇒ 永远提交）。
   */
  it('JSON 字段：旧值是对象时不因「序列化 vs 对象」而误判为改动', () => {
    const FJ: Field[] = [{ name: 'config', label: '配置', type: 'textarea' }];
    const old = { config: [{ day: 1, reward: { type: 'platform_coin', amount: '100' } }] };
    expect(payload(FJ, old, { config: json(old.config) })).toEqual({});
    expect(payload(FJ, old, { config: '{"rewards":[]}' })).toEqual({ config: '{"rewards":[]}' });
  });
});
