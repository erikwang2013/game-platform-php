/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import fs from '@ohos.file.fs';
import { t } from '../i18n/Translations';
import photoAccessHelper from '@ohos.file.photoAccessHelper';

/** 选中的图片：原始字节 + 文件名（服务端按文件名后缀查白名单，故必须带后缀） */
export interface PickedImage {
  bytes: ArrayBuffer;
  fileName: string;
}

/**
 * 拉起系统图库选一张图并读出字节。
 *
 * PhotoViewPicker 是**系统级**选择器：不需要申请相册权限（用户亲手选中的那几张由系统临时授权），
 * 故 module.json5 不必加任何 requestPermissions —— 这一点与本树"只有 INTERNET 一条权限"一致。
 *
 * 用户取消 → 返回 null（不是错误，调用方别提示失败）。
 */
export async function pickImage(): Promise<PickedImage | null> {
  const options = new photoAccessHelper.PhotoSelectOptions();
  options.MIMEType = photoAccessHelper.PhotoViewMIMETypes.IMAGE_TYPE;
  options.maxSelectNumber = 1;

  const uris = (await new photoAccessHelper.PhotoViewPicker().select(options)).photoUris;
  if (uris.length === 0) {
    return null;
  }

  const uri = uris[0];
  const file = fs.openSync(uri, fs.OpenMode.READ_ONLY);
  try {
    const size = fs.statSync(file.fd).size;
    const bytes = new ArrayBuffer(size);
    const read = fs.readSync(file.fd, bytes);
    // 截断读（信号打断等）不能静默放过：传上去半张图，MIME 检查照样通过，坏在用户眼里
    if (read !== size) {
      // 两个占位符串起来换：t1 只吃一个，为这一个调用点再加个 t2 不值当
      throw new Error(t('upload.read_failed')
        .replace('{read}', `${read}`).replace('{size}', `${size}`));
    }
    const picked: PickedImage = { bytes: bytes, fileName: fileNameOf(uri) };
    return picked;
  } finally {
    fs.closeSync(file);
  }
}

/** 从 uri 末段取文件名（`file://media/Photo/12/IMG_x/abc.jpg` → `abc.jpg`） */
function fileNameOf(uri: string): string {
  const cut = uri.lastIndexOf('/');
  const name = cut >= 0 ? uri.substring(cut + 1) : uri;
  return name.length > 0 ? name : 'image.jpg';
}
