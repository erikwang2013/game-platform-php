/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { describe, expect, it } from 'vitest';
import { chunkBody, chunks, failText, fileUrl, preprocessBody, readUrl } from './upload';

/**
 * aetherupload 两步协议的钉子（C 端）。契约真源：
 * service/config/plugin/erikwang2013/aetherupload-webman/{app,route}.php 与插件 UploadController。
 *
 * 钉的是三件「静默坏掉、界面看不出来」的事：
 * ① 成功判据必须是 error **恰为 0** —— 按 falsy 判会把 UserAuth 的 401 信封当成功，
 *    于是 savedPath 取到 undefined 拼进落库地址；
 * ② 分块下标**从 1 起**（0 会被服务端当成重传吞掉）；
 * ③ 落库值是**相对**地址（C 端与 admin 树刻意不同）。
 */
describe('preprocessBody', () => {
  const b = preprocessBody({ name: 'id.png', size: 1234 });

  it('字段名与插件 validator 对齐，且 resource_hash 必须 present 为空串', () => {
    expect(b.get('resource_name')).toBe('id.png');
    expect(b.get('resource_size')).toBe('1234');
    expect(b.get('group')).toBe('image');
    // 空串 ≠ 缺键：服务端对它做 present 校验，删了这行会 422
    expect(b.has('resource_hash')).toBe(true);
    expect(b.get('resource_hash')).toBe('');
  });

  it('locale 用本树真实存在的目录名（C 端只有 en/zh，admin 那边是 zh_CN）', () => {
    expect(b.get('locale')).toBe('zh');
  });
});

describe('chunkBody', () => {
  const pre = {
    chunkSize: 1000,
    groupSubDir: '202610',
    resourceTempBaseName: 'tmp1',
    resourceExt: 'png',
    savedPath: '',
  };

  it('chunk_index 从 1 起（0 会被服务端当成重传吞掉）', () => {
    expect(chunkBody(pre, 1, 3).get('chunk_index')).toBe('1');
    expect(chunkBody(pre, 3, 3).get('chunk_index')).toBe('3');
  });

  it('带回 preprocess 给的临时名与分组', () => {
    const c = chunkBody(pre, 1, 2);
    expect(c.get('resource_temp_basename')).toBe('tmp1');
    expect(c.get('group_subdir')).toBe('202610');
    expect(c.get('chunk_total')).toBe('2');
  });
});

describe('chunks', () => {
  it('按 chunkSize 切片，末块取余', () => {
    expect(chunks(2500, 1000)).toEqual([
      { start: 0, end: 1000 },
      { start: 1000, end: 2000 },
      { start: 2000, end: 2500 },
    ]);
  });

  it('刚好整除时不多出一块空片', () => {
    expect(chunks(2000, 1000)).toEqual([
      { start: 0, end: 1000 },
      { start: 1000, end: 2000 },
    ]);
  });

  it('空文件也回一块（chunk_total 至少为 1）', () => {
    expect(chunks(0, 1000)).toEqual([{ start: 0, end: 0 }]);
  });

  it('chunkSize 非正数时退化成单块，不进入死循环', () => {
    // 服务端若把 chunkSize 回成 0/'0'/undefined，start += 0 会永远循环
    for (const bad of [0, -1, Number.NaN]) {
      expect(chunks(1500, bad), String(bad)).toEqual([{ start: 0, end: 1500 }]);
    }
  });
});

describe('failText', () => {
  it('error 恰为 0（数字或字符串）才算成功', () => {
    expect(failText({ error: 0 })).toBe('');
    expect(failText({ error: '0' })).toBe('');
  });

  it('字符串错误原文透出，不改写', () => {
    expect(failText({ error: '上传发生错误' })).toBe('上传发生错误');
  });

  it('UserAuth 的 401 信封（HTTP 200 + code:401）不算成功', () => {
    expect(failText({ code: 401, message: '未登录', data: [] })).toContain('登录');
  });

  it('既没有 error 也不是 401 信封 ⇒ 判失败，绝不判成功', () => {
    // 这条是核心：返回空串 = 调用方会拿 undefined 的 savedPath 去拼落库地址
    expect(failText({})).not.toBe('');
    expect(failText({ savedPath: 'x.png' })).not.toBe('');
  });
});

describe('落库地址', () => {
  it('存的是相对地址（C 端读取口 UserFileController 认这个前缀）', () => {
    expect(fileUrl('image_202610_ab.png')).toBe('/api/v1/user/file/image_202610_ab.png');
  });

  it('读取时相对地址补前缀、绝对地址原样（第三方 OAuth 头像）', () => {
    expect(readUrl('/api/v1/user/file/a.png')).toBe('/api/v1/user/file/a.png');
    expect(readUrl('https://cdn.example.com/a.png')).toBe('https://cdn.example.com/a.png');
  });
});
