/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Injectable, inject } from '@angular/core';
import { Api, Row } from './api.service';
import { t } from './i18n/i18n';

/**
 * aetherupload 两步协议（插件 erikwang2013/aetherupload-webman；本仓配置见
 * admin/config/plugin/erikwang2013/aetherupload-webman/app.php）。
 *
 * 响应**不是**统一信封（没有 code 字段）：成功判据是 `error === 0`，失败时 error 是给人看的
 * 字符串 ⇒ 走 Api.raw() 而不是 request()（后者按 code !== 0 抛，会把上传成功也判成失败）。
 *
 * 协议拼装（表单字段/切块区间/落库 URL）都是纯函数，钉子在 upload.spec.ts；
 * 本类只负责发请求。
 */
const PREPROCESS = '/admin/v1/aetherupload/preprocess';
const UPLOADING = '/admin/v1/aetherupload/uploading';
const DISPLAY = '/admin/v1/aetherupload/display/';
/** 与后端 groups 的键、插件语言目录同名 */
const GROUP = 'image';
/**
 * 插件自己的语言字段（不是本树的 i18n）：插件按它选 `resource/languages/<locale>/` 下的表来译
 * `error` 原文（键是字面句子，见 aetherupload-webman 的 fail()）。**刻意不跟着界面语言走** ——
 * 插件的语言目录名与 13 语言短码不是一套（是 zh_CN 这种），填错会静默回落。
 */
const LOCALE = 'zh_CN';

/** preprocess 表单（字段名照 UploadController::preprocess 的 validator，一个都别改） */
export function preprocessBody(file: { name: string; size: number }): URLSearchParams {
  return new URLSearchParams({
    resource_name: file.name,
    resource_size: String(file.size),
    // 空串：宽松模式不做秒传与完整性校验，但服务端要求该键 present，不能省
    resource_hash: '',
    locale: LOCALE,
    group: GROUP,
  });
}

/** preprocess 回的元数据，uploading 时原样带回 */
export type Pre = {
  chunkSize: number;
  groupSubDir: string;
  resourceTempBaseName: string;
  resourceExt: string;
  savedPath: string;
};

/** uploading 的文本部分；文件部分 resource_chunk 由调用方 append（它不是字符串） */
export function chunkBody(pre: Pre, chunkIndex: number, chunkTotal: number): URLSearchParams {
  return new URLSearchParams({
    resource_ext: pre.resourceExt,
    chunk_total: String(chunkTotal),
    // 从 1 起：服务端按 lastChunkIndex + 1 校验分块连续性，0 起会被当成重传直接吞掉
    chunk_index: String(chunkIndex),
    resource_temp_basename: pre.resourceTempBaseName,
    group: GROUP,
    group_subdir: pre.groupSubDir,
    locale: LOCALE,
    resource_hash: '',
  });
}

/** 按服务端给的 chunkSize 算切片区间（末块取余）；空文件也回一块（chunk_total 至少为 1） */
export function chunks(size: number, chunkSize: number): { start: number; end: number }[] {
  // chunkSize 非正数（或回的是字符串）时退化成单块 —— 别让 start += step 变成死循环
  const step = Number(chunkSize) > 0 ? Number(chunkSize) : size;
  const out: { start: number; end: number }[] = [];
  for (let start = 0; start < size; start += step) {
    out.push({ start, end: Math.min(start + step, size) });
  }
  return out.length ? out : [{ start: 0, end: 0 }];
}

/**
 * 服务端 error → 提示文本（空串 = 成功）。字符串原样透出，不翻译不改写。
 * 判定成功要 `error` **恰为 0**：AdminAuth 拒绝时回的是统一信封（HTTP 200 + code 401，
 * 见 app/middleware/AdminAuth.php:34），那种响应根本没有 error 字段 —— 若按「falsy 即成功」
 * 处理，savedPath 会取到 undefined 拼进落库 URL。
 */
export function failText(res: Row): string {
  const e = res['error'];
  if (e === 0 || e === '0') return '';
  if (typeof e === 'string' && e) return e;
  if (res['code'] === 401) return t('app.session_expired');
  // 既没有 error 也不是 401 信封 ⇒ 这响应根本不是上传接口的格式，别当成成功放过去
  return e === undefined || e === null
    ? t('upload.failed_no_error')
    : t('upload.failed_reason', { reason: String(e) });
}

/** 落库值 = 绝对 URL：这个字段 C 端也要看，相对路径出了管理端就废 */
export function displayUrl(origin: string, savedPath: string): string {
  return `${origin.replace(/\/+$/, '')}${DISPLAY}${savedPath}`;
}

@Injectable({ providedIn: 'root' })
export class ImageUpload {
  private readonly api = inject(Api);

  /** 两步走完回**绝对 URL**；失败抛 Error，message 即服务端 error 原文 */
  async image(file: File): Promise<string> {
    const pre = await this.step<Pre>(PREPROCESS, preprocessBody(file));
    if (pre.savedPath) return displayUrl(location.origin, pre.savedPath); // 秒传命中（本仓关着）
    const parts = chunks(file.size, pre.chunkSize);
    let saved = '';
    for (let i = 0; i < parts.length; i++) {
      const part = parts[i]!;
      const fd = new FormData();
      // 文件名不能省：没有 filename 的 part 会被 PHP 解析成普通字段，$request->file() 拿到 null
      // 类型也要显式带上：Blob.slice 不继承原 File 的 type，缺省会发成 application/octet-stream
      //（服务端是靠 mime_content_type() 嗅探合并后的真实字节，能过；但没必要发一个不实的类型）
      fd.append('resource_chunk', file.slice(part.start, part.end, file.type), file.name);
      for (const [k, v] of chunkBody(pre, i + 1, parts.length)) fd.append(k, v);
      saved = (await this.step<{ savedPath: string }>(UPLOADING, fd)).savedPath;
    }
    if (!saved) throw new Error(t('upload.failed_no_path'));
    return displayUrl(location.origin, saved);
  }

  private async step<T extends Row>(path: string, body: URLSearchParams | FormData): Promise<T> {
    const res = await this.api.raw<T>('POST', path, body);
    const fail = failText(res);
    if (fail) throw new Error(fail);
    return res;
  }
}
