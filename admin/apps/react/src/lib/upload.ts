/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * aetherupload 两步协议的字段拼装（preprocess → uploading），供 `image` 字段类型上传图片。
 * 契约源头：admin/config/plugin/erikwang2013/aetherupload-webman/app.php 的 image 组
 * （≤5MB，jpg/jpeg/png/gif/webp）+ 插件 UploadController。**字段名一个都不能改** ——
 * 服务端按名字取，写错只会回一句「参数无效」，看不出是哪个字段。
 *
 * 拼装是纯函数（不碰网络，供 node --test 直接覆盖）；只有 uploadImage 一处编排。
 */
import { t } from '../i18n/index.ts';
import { ApiError, rawPost } from './api.ts';

export const PREPROCESS_PATH = '/admin/v1/aetherupload/preprocess';
export const UPLOADING_PATH = '/admin/v1/aetherupload/uploading';

/** 上传组：服务端按组取大小上限与后缀白名单。 */
const GROUP = 'image';
/** 服务端按 locale 选错误文案的语言。 */
const LOCALE = 'zh_CN';

/** preprocess 的响应；失败时 `error` 是**给人看的字符串**（非 0 即失败），其余字段保持缺省。 */
export type Preprocess = {
  chunkSize?: number;
  groupSubDir?: string;
  resourceTempBaseName?: string;
  resourceExt?: string;
  savedPath?: string;
};

/** preprocess 的表单字段。`resource_hash` 必须**在**（服务端 present 规则）且传空串：lax_mode 下客户端不算 md5。 */
export function preprocessFields(fileName: string, size: number): Record<string, string> {
  return {
    resource_name: fileName,
    resource_size: String(size),
    resource_hash: '',
    locale: LOCALE,
    group: GROUP,
  };
}

/**
 * 每块的 `[start, end)`。按服务端给的 chunkSize 切（chunk_index 从 1 开始递增，服务端只收「上一块 + 1」）；
 * chunkSize 拿不到就整文件一块 —— 图片 ≤5MB，单块也在服务端上限内。
 */
export function chunkRanges(size: number, chunkSize: number): [number, number][] {
  const step = chunkSize > 0 ? chunkSize : Math.max(size, 1);
  const ranges: [number, number][] = [];
  for (let start = 0; start < size; start += step) ranges.push([start, Math.min(start + step, size)]);
  return ranges.length > 0 ? ranges : [[0, 0]];
}

/** 单块的表单字段（文件本身由调用方塞进 `resource_chunk`）。 */
export function chunkFields(pre: Preprocess, index: number, total: number): Record<string, string> {
  return {
    resource_ext: pre.resourceExt ?? '',
    chunk_total: String(total),
    chunk_index: String(index),
    resource_temp_basename: pre.resourceTempBaseName ?? '',
    group_subdir: pre.groupSubDir ?? '',
    locale: LOCALE,
    group: GROUP,
    resource_hash: '',
  };
}

/**
 * 落库值：**绝对**展示 URL。C 端用户也要看这张封面，相对路径会指到 C 端自己的主机上；
 * display 路由公开（`<img src>` 带不了 Authorization 头），savedPath 里是文件内容的 md5，不可猜。
 */
export function displayUrl(savedPath: string, origin: string): string {
  return `${origin}/admin/v1/aetherupload/display/${savedPath}`;
}

/**
 * 上传一张图片，返回落库用的绝对展示 URL。
 * 失败抛 ApiError，message 是**服务端 error 原文**（原样显示，别换成自编文案）。
 */
export async function uploadImage(file: File, origin: string): Promise<string> {
  const pre = await post<Preprocess>(PREPROCESS_PATH, new URLSearchParams(preprocessFields(file.name, file.size)));
  // 秒传命中：本仓 instant_completion=false，恒为空
  if (pre.savedPath) return displayUrl(pre.savedPath, origin);

  const ranges = chunkRanges(file.size, pre.chunkSize ?? 0);
  for (const [offset, [start, end]] of ranges.entries()) {
    const form = new FormData();
    for (const [name, value] of Object.entries(chunkFields(pre, offset + 1, ranges.length))) form.set(name, value);
    form.set('resource_chunk', file.slice(start, end), file.name);
    const { savedPath } = await post<{ savedPath?: string }>(UPLOADING_PATH, form);
    if (savedPath) return displayUrl(savedPath, origin);
  }
  throw new ApiError(-1, t('upload.incomplete'));
}

/**
 * POST 一步并检查 `error`：0（数字或 '0'）＝成功；非 0 时它是服务端本地化好的句子，原样上抛。
 * 没有 `error` 字段说明这不是插件的响应（中间件/网关的信封），把服务端 message 摆出来，别让它变成后面某步的怪错。
 */
async function post<T>(path: string, body: URLSearchParams | FormData): Promise<T> {
  const payload = await rawPost(path, body);
  if (payload.error === undefined) {
    throw new ApiError(-1, typeof payload.message === 'string' && payload.message !== '' ? payload.message : t('upload.bad_response'));
  }
  if (Number(payload.error) !== 0) throw new ApiError(-1, String(payload.error));
  return payload as T;
}
