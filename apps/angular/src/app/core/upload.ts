/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, switchMap } from 'rxjs';
import { ApiError, Envelope } from './api.service';

/**
 * aetherupload 两步协议（插件 erikwang2013/aetherupload-webman；本仓配置见
 * service/config/plugin/erikwang2013/aetherupload-webman/app.php）。
 *
 * ⚠ 与 admin 树的三处**刻意不同**，别照抄管理端：
 * 1. 路径在 /api/v1 下，且两条都挂 UserAuth（能力对等，只是前缀不同）；
 * 2. **响应不是统一信封**（没有 code 字段）：成功判据是 `error === 0`，失败时 error 是给人看的
 *    字符串 ⇒ 不能走 Api.request()（它按 code !== 0 抛，会把上传成功也判成失败）；
 * 3. **落库值是相对地址** `/api/v1/user/file/{savedPath}`，不是拼域名的绝对地址 ——
 *    个人件的读取口是 UserFileController（按归属校验），它认的正是这个前缀。
 *
 * 协议拼装（表单字段 / 切块区间 / 落库 URL）都是纯函数，钉子在 upload.spec.ts。
 */
const PREPROCESS = '/api/v1/aetherupload/preprocess';
const UPLOADING = '/api/v1/aetherupload/uploading';
/** 个人件读取前缀，与 UserFileController::URL_PREFIX 必须一致 */
export const FILE_PREFIX = '/api/v1/user/file/';
/** 与后端 groups 的键同名（本树只配了 image 一组） */
const GROUP = 'image';
/**
 * 插件自己的语言字段（不是本树的 i18n）：插件按它选 `resource/translations/aetherupload/<locale>/`
 * 下的表来译 `error` 原文。**C 端只有 en / zh 两个目录**（admin 那边是 zh_CN，别抄）。
 */
const LOCALE = 'zh';

/** 服务端 groups.image 白名单（无 svg）与大小上限 */
export const MAX_BYTES = 5 * 1024 * 1024;
export const ACCEPT = 'image/jpeg,image/png,image/gif,image/webp';

/** preprocess 表单（字段名照 UploadController::preprocess 的 validator，一个都别改） */
export function preprocessBody(file: { name: string; size: number }): URLSearchParams {
  return new URLSearchParams({
    resource_name: file.name,
    resource_size: String(file.size),
    // 空串：lax_mode 下不做秒传与完整性校验，但服务端要求该键 present，不能省
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
 * 判定成功要 `error` **恰为 0**：UserAuth 拒绝时回的是统一信封（HTTP 200 + code 401），
 * 那种响应根本没有 error 字段 —— 若按「falsy 即成功」处理，savedPath 会取到 undefined
 * 拼进落库 URL。
 */
export function failText(res: Record<string, unknown>): string {
  const e = res['error'];
  if (e === 0 || e === '0') return '';
  if (typeof e === 'string' && e) return e;
  if (res['code'] === 401) return '登录状态已失效，请重新登录后再上传';
  // 既没有 error 也不是 401 信封 ⇒ 这响应根本不是上传接口的格式，别当成成功放过去
  return e === undefined || e === null ? '上传失败，请重试' : String(e);
}

/** 落库值 = 相对地址（见文件头第 3 条） */
export const fileUrl = (savedPath: string): string => `${FILE_PREFIX}${savedPath}`;

/** 由落库值反推读取 URL：历史值可能是第三方绝对地址，那类原样用 */
export function readUrl(stored: string): string {
  return stored.startsWith('http') ? stored : `${FILE_PREFIX}${stored.replace(/^.*\//, '')}`;
}

@Injectable({ providedIn: 'root' })
export class ImageUpload {
  private readonly http = inject(HttpClient);

  /** 两步走完回**落库值**（相对地址）；失败抛 Error，message 即服务端 error 原文 */
  async image(file: File): Promise<string> {
    const pre = await this.step<Pre>(PREPROCESS, preprocessBody(file));
    if (pre.savedPath) return fileUrl(pre.savedPath); // 秒传命中（本仓关着）
    const parts = chunks(file.size, pre.chunkSize);
    let saved = '';
    for (let i = 0; i < parts.length; i++) {
      const part = parts[i]!;
      const fd = new FormData();
      // 文件名不能省：没有 filename 的 part 会被 PHP 解析成普通字段，$request->file() 拿到 null
      // 类型也要显式带上：Blob.slice 不继承原 File 的 type，缺省会发成 application/octet-stream
      //（服务端靠 mime_content_type() 嗅探合并后的真实字节，能过；但没必要发一个不实的类型）
      fd.append('resource_chunk', file.slice(part.start, part.end, file.type), file.name);
      for (const [k, v] of chunkBody(pre, i + 1, parts.length)) fd.append(k, v);
      saved = (await this.step<{ savedPath: string }>(UPLOADING, fd)).savedPath;
    }
    if (!saved) throw new Error('上传完成但服务端未返回路径，请重试');
    return fileUrl(saved);
  }

  /**
   * 发一步并判成败。响应体**不套信封**（见文件头第 2 条），故这里不用 Api.request()。
   */
  private step<T extends Record<string, unknown>>(url: string, body: BodyInit): Promise<T> {
    return new Promise<T>((resolve, reject) => {
      this.http.post<T>(url, body).subscribe({
        next: (res) => {
          const err = failText(res as Record<string, unknown>);
          if (err) reject(new Error(err));
          else resolve(res);
        },
        error: (e: unknown) => reject(new Error(describeHttp(e))),
      });
    });
  }
}

function describeHttp(e: unknown): string {
  if (e instanceof ApiError) return e.message;
  if (e instanceof HttpErrorResponse) {
    const body = e.error as Envelope<unknown> | null;
    if (body && typeof body === 'object' && 'message' in body) return String(body.message);
    if (e.status === 0) return '无法连接服务器，请稍后重试';
    return `上传失败 (HTTP ${e.status})`;
  }
  return '上传失败，请重试';
}

/**
 * 取个人件字节（头像 / KYC 三照）。
 *
 * 为什么不能用 `<img [src]>`：这个端点要 Bearer 头，而 user/file **没有**给 KYC 照开匿名读取
 * （头像才有，见 UserFileController::readable）。所以只能带 token 取 blob。
 *
 * ⚠ 失败响应是 **HTTP 200 + JSON 信封**（成功走 `response()->file()`、失败走 `fail()`→`json()`），
 * 被 responseType:'blob' 收成 Blob ⇒ 必须按 blob.type 分流，否则会把一段 JSON 当图片喂给
 * `URL.createObjectURL`，得到一个永远碎掉的图。
 */
export function fileBlob(http: HttpClient, stored: string): Observable<Blob> {
  return http.get(readUrl(stored), { responseType: 'blob' }).pipe(
    switchMap(async (b) => {
      if (b.type.includes('json')) {
        const env = JSON.parse(await b.text()) as Envelope<unknown>;
        throw new ApiError(env?.message || '读取失败', env?.code ?? -1);
      }
      return b;
    }),
  );
}
