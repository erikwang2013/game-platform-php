/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { TestBed } from '@angular/core/testing';
import { describe, expect, it } from 'vitest';
import { Api, Row } from './api.service';
import { ImageUpload, Pre, chunkBody, chunks, displayUrl, failText, preprocessBody } from './upload';

const form = (p: URLSearchParams): Record<string, string> => Object.fromEntries(p);

const PRE: Pre = {
  chunkSize: 1_000_000,
  groupSubDir: '202610',
  resourceTempBaseName: 'tmpname',
  resourceExt: 'png',
  savedPath: '',
};

describe('aetherupload 协议拼装', () => {
  it('preprocess：字段名/值与后端 validator 一一对应（少一个键或改名都是 400）', () => {
    expect(form(preprocessBody({ name: '封面 图.png', size: 2048 }))).toEqual({
      resource_name: '封面 图.png',
      resource_size: '2048',
      // 秒传关了但该键必须 present
      resource_hash: '',
      locale: 'zh_CN',
      group: 'image',
    });
  });

  it('uploading：元数据原样带回，chunk_index 从 1 起（0 起会被服务端当成重传吞掉）', () => {
    expect(form(chunkBody(PRE, 1, 3))).toEqual({
      resource_ext: 'png',
      chunk_total: '3',
      chunk_index: '1',
      resource_temp_basename: 'tmpname',
      group: 'image',
      group_subdir: '202610',
      locale: 'zh_CN',
      resource_hash: '',
    });
  });

  it('切块：单块 / 多块 / 整除 / 空文件（chunk_total 至少 1）', () => {
    expect(chunks(500, 1_000_000)).toEqual([{ start: 0, end: 500 }]);
    expect(chunks(2_500_000, 1_000_000)).toEqual([
      { start: 0, end: 1_000_000 },
      { start: 1_000_000, end: 2_000_000 },
      { start: 2_000_000, end: 2_500_000 },
    ]);
    expect(chunks(2_000_000, 1_000_000)).toEqual([
      { start: 0, end: 1_000_000 },
      { start: 1_000_000, end: 2_000_000 },
    ]);
    expect(chunks(0, 1_000_000)).toEqual([{ start: 0, end: 0 }]);
  });

  it('切块：chunkSize 非正数（或回的是字符串）退化成单块，不死循环', () => {
    expect(chunks(100, 0)).toEqual([{ start: 0, end: 100 }]);
    expect(chunks(100, -1)).toEqual([{ start: 0, end: 100 }]);
    expect(chunks(100, '1000000' as unknown as number)).toEqual([{ start: 0, end: 100 }]);
  });

  it('成功判据是 error 恰为 0：字符串原样透出，缺字段/401 信封都不许当成功', () => {
    expect(failText({ error: 0, savedPath: 'a' })).toBe('');
    expect(failText({ error: 'invalid_resource_type' })).toBe('invalid_resource_type');
    // AdminAuth 拒绝：HTTP 200 + {code:401}，没有 error 字段
    expect(failText({ code: 401, message: '未登录' })).toBe('登录状态已失效，请重新登录');
    expect(failText({})).toBe('上传失败：服务端未返回 error 字段');
    expect(failText({ error: 7 })).toBe('上传失败（7）');
  });

  it('落库 URL：绝对地址（C 端也要看），origin 末尾斜杠不叠成 //', () => {
    expect(displayUrl('http://localhost:4300', 'image_202610_ab.png')).toBe(
      'http://localhost:4300/admin/v1/aetherupload/display/image_202610_ab.png',
    );
    expect(displayUrl('https://admin.games.test/', 'x')).toBe(
      'https://admin.games.test/admin/v1/aetherupload/display/x',
    );
  });
});

describe('ImageUpload（preprocess → uploading 两步链路）', () => {
  const setup = (answer: (url: string) => Row) => {
    const calls: { url: string; body: unknown }[] = [];
    const fake = {
      raw: (_m: string, url: string, body: unknown) => {
        calls.push({ url, body });
        return Promise.resolve(answer(url));
      },
    };
    TestBed.configureTestingModule({ providers: [{ provide: Api, useValue: fake }] });
    return { uploads: TestBed.inject(ImageUpload), calls };
  };

  const ok = (url: string): Row =>
    url.endsWith('/preprocess')
      ? { error: 0, chunkSize: 1_000_000, groupSubDir: '202610', resourceTempBaseName: 't', resourceExt: 'png', savedPath: '' }
      : { error: 0, savedPath: 'image_202610_ab.png' };

  const file = (size: number, name = 'a.png'): File =>
    new File([new Uint8Array(size)], name, { type: 'image/png' });

  it('单块：两次 POST，回绝对 URL；multipart 里带文件名（没文件名 PHP 当普通字段收）', async () => {
    const { uploads, calls } = setup(ok);
    const url = await uploads.image(file(500));

    expect(calls.map((c) => c.url)).toEqual([
      '/admin/v1/aetherupload/preprocess',
      '/admin/v1/aetherupload/uploading',
    ]);
    const fd = calls[1]!.body as FormData;
    expect(fd.get('chunk_index')).toBe('1');
    expect(fd.get('chunk_total')).toBe('1');
    const part = fd.get('resource_chunk') as File;
    expect(part.size).toBe(500);
    expect(part.name).toBe('a.png');
    expect(part.type).toBe('image/png'); // slice 不继承 type，必须显式传
    expect(url).toBe(`${location.origin}/admin/v1/aetherupload/display/image_202610_ab.png`);
  });

  it('超过 chunkSize 的图按服务端 chunkSize 切成多块，每块一块 multipart', async () => {
    const { uploads, calls } = setup(ok);
    await uploads.image(file(2_500_000));

    expect(calls.length).toBe(4); // preprocess + 3 块
    const parts = calls.slice(1).map((c) => c.body as FormData);
    expect(parts.map((p) => p.get('chunk_index'))).toEqual(['1', '2', '3']);
    expect(parts.map((p) => (p.get('resource_chunk') as Blob).size)).toEqual([1_000_000, 1_000_000, 500_000]);
  });

  it('preprocess 就回 savedPath（秒传命中）⇒ 只发一次请求直接结束', async () => {
    const { uploads, calls } = setup((url) =>
      url.endsWith('/preprocess')
        ? { error: 0, chunkSize: 1_000_000, groupSubDir: 'x', resourceTempBaseName: 't', resourceExt: 'png', savedPath: 'image_202610_hit.png' }
        : { error: 0, savedPath: '不该走到这里' },
    );
    await expect(uploads.image(file(500))).resolves.toBe(
      `${location.origin}/admin/v1/aetherupload/display/image_202610_hit.png`,
    );
    expect(calls.length).toBe(1);
  });

  it('服务端 error 字符串 → 抛出原文（前端不改写），且失败后不再传块', async () => {
    const { uploads, calls } = setup(() => ({ error: 'invalid_resource_type' }));
    await expect(uploads.image(file(500))).rejects.toThrow('invalid_resource_type');
    expect(calls.length).toBe(1);
  });

  it('中间块失败：不吞错、不拼出半个 URL', async () => {
    const { uploads } = setup((url) =>
      url.endsWith('/preprocess')
        ? { error: 0, chunkSize: 1_000_000, groupSubDir: '202610', resourceTempBaseName: 't', resourceExt: 'png', savedPath: '' }
        : { error: 'write_resource_fail' },
    );
    await expect(uploads.image(file(2_500_000))).rejects.toThrow('write_resource_fail');
  });

  it('uploading 回了空 savedPath（不是上传接口的响应）→ 报错不当成功', async () => {
    const { uploads } = setup((url) => (url.endsWith('/preprocess') ? ok(url) : { error: 0, savedPath: '' }));
    await expect(uploads.image(file(500))).rejects.toThrow('上传失败：服务端未返回文件路径');
  });
});
