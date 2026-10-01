/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import http from '@ohos.net.http';
import { apiService } from './ApiService';
import { API_BASE_URL } from '../common/AppConfig';
import { FormPayload, UploadTicket } from '../model/DataModels';
// 本文件全是**请求期**求值的静态方法 ⇒ 直接调 t() 安全（服务端 error 原文不译，只译本地兜底话术）
import { t } from '../i18n/Translations';

/**
 * 上传分组 —— 与 admin/config/plugin/erikwang2013/aetherupload-webman/app.php 的 groups.image 同一取值：
 * 白名单 jpg/jpeg/png/gif/webp（**不含 svg**：可内嵌脚本，直出浏览器＝存储型 XSS），单文件 ≤5MB。
 */
const GROUP: string = 'image';
/** 服务端文案语言（插件按 locale 查翻译目录） */
const LOCALE: string = 'zh_CN';

const PREPROCESS_PATH: string = '/admin/v1/aetherupload/preprocess';
const UPLOADING_PATH: string = '/admin/v1/aetherupload/uploading';

/**
 * 图片上传（aetherupload 两步协议）—— 返回**可直接落库/展示的绝对地址**。
 *
 * 1. preprocess（普通表单）：拿服务端生成的临时名 / 后缀 / 子目录；
 * 2. uploading（multipart）：二进制塞进 `resource_chunk`。
 *
 * **单块**：本仓 chunk_size=1000000 只是插件给客户端的建议值 —— 服务端只按**累计**大小卡
 * resource_maxsize(5MB)（filterBySize/checkSize），不校验单块是否超过 chunkSize ⇒ 5MB 以内的图
 * 一次传完，chunk_total=1、chunk_index 从 **1** 起（0 会被"分块重传"分支当成已有分块）。
 *
 * 失败一律 `throw new Error(服务端 error 原文)`：插件 reportError() 把 error 换成**给人看的字符串**，
 * 调用方原样显示即可，别翻译、别当错误码比对。
 */
export class UploadService {
  static async uploadImage(bytes: ArrayBuffer, fileName: string): Promise<string> {
    const ticket = await UploadService.preprocess(fileName, bytes.byteLength);

    // savedPath 非空 = 秒传命中，直接可用（本仓 instant_completion=false，恒为空，仍按契约判一次）
    const instant = UploadService.savedPathOf(ticket);
    if (instant.length > 0) {
      return UploadService.displayUrl(instant);
    }

    const done = await UploadService.sendChunk(bytes, fileName, ticket);
    const failure = UploadService.errorText(done);
    if (failure.length > 0) {
      throw new Error(failure);
    }
    const savedPath = UploadService.savedPathOf(done);
    if (savedPath.length === 0) {
      throw new Error(t('upload.no_saved_path'));
    }
    return UploadService.displayUrl(savedPath);
  }

  /**
   * 第一步：普通表单。`resource_hash` 必须是**空串**（协议要求 present；秒传关着，空串不会写脏 Redis 键），
   * 空 hash 的代价是完整性校验与秒传都不可用 —— 本场景（展示件）无意义，见 app.php 的 lax_mode。
   */
  private static async preprocess(fileName: string, size: number): Promise<UploadTicket> {
    const body: FormPayload = {};
    body.resource_name = fileName;
    body.resource_size = size;
    body.resource_hash = '';
    body.locale = LOCALE;
    body.group = GROUP;
    const ticket = await apiService.postRaw<UploadTicket>(PREPROCESS_PATH, body);
    const failure = UploadService.errorText(ticket);
    if (failure.length > 0) {
      throw new Error(failure);
    }
    return ticket;
  }

  /**
   * 第二步：multipart 单块。文本字段与文件同批送出 —— 服务端对 multipart 体走 parseUploadFiles()，
   * 文本字段一样落进 post，`input()` 取得到。
   */
  private static async sendChunk(bytes: ArrayBuffer, fileName: string, ticket: UploadTicket): Promise<UploadTicket> {
    const parts: Array<http.MultiFormData> = [];
    const chunk: http.MultiFormData = {
      name: 'resource_chunk',
      // 这个声明值不参与判定：服务端用 mime_content_type() 嗅探真实字节
      contentType: 'application/octet-stream',
      // 有文件名才算"文件段"（无它会被当成普通文本字段，$request->file() 拿到 null）
      remoteFileName: fileName,
      data: bytes
    };
    parts.push(chunk);
    parts.push(UploadService.field('resource_ext', ticket.resourceExt));
    parts.push(UploadService.field('resource_temp_basename', ticket.resourceTempBaseName));
    parts.push(UploadService.field('group_subdir', ticket.groupSubDir));
    parts.push(UploadService.field('group', GROUP));
    parts.push(UploadService.field('locale', LOCALE));
    parts.push(UploadService.field('resource_hash', ''));
    parts.push(UploadService.field('chunk_total', '1'));
    parts.push(UploadService.field('chunk_index', '1'));
    return await apiService.postMultipart<UploadTicket>(UPLOADING_PATH, parts);
  }

  /** 一个普通文本字段（没有 remoteFileName 就不是文件段） */
  private static field(name: string, value: string): http.MultiFormData {
    const part: http.MultiFormData = { name: name, contentType: 'text/plain', data: value };
    return part;
  }

  /**
   * 落库值：**绝对地址**（C 端列表/详情也要按这个地址取图，故不能存相对路径）。
   * display 路由**公开**（config/plugin/.../app.php 的 middleware_display 为空）：`<img src>` 带不了
   * Authorization 头，而 savedPath 里是文件内容的 md5，不可猜。
   */
  private static displayUrl(savedPath: string): string {
    return `${API_BASE_URL}/admin/v1/aetherupload/display/${savedPath}`;
  }

  /** savedPath 缺失（响应被截断等）时按空串收，由调用方判空 */
  private static savedPathOf(ticket: UploadTicket): string {
    return ticket.savedPath ?? '';
  }

  /** 服务端 error 原文（0 = 成功）；缺字段时模板串会得到 "undefined"，那不是给人看的，换兜底文案 */
  private static errorText(ticket: UploadTicket): string {
    const raw = ticket.error;
    if (raw === 0) {
      return '';
    }
    const text = `${raw}`;
    return text.length > 0 && text !== 'undefined' ? text : t('upload.failed');
  }
}
