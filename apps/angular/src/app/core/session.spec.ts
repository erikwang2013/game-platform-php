/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { HttpRequest, HttpResponse } from '@angular/common/http';
import { firstValueFrom, of } from 'rxjs';
import { describe, expect, it, beforeEach } from 'vitest';
import { apiInterceptor, language, tokens } from './session';

/**
 * 拦截器出站头的钉子。
 *
 * 钉它的理由：`X-Language` 只存在于**出站请求**上，页面渲染看不出来 —— 没有它整棵树照样跑，
 * 症状只出现在「浏览器语言 ≠ 中文」的用户身上（服务端按 `Accept-Language` 吐别的语言，
 * 界面却还是中文）。仓内没有第二种观察者。
 *
 * 只测拦截器本身（纯函数，`(req, next) => Observable`），不起 TestBed：
 * 真机层由 /tmp 的 CDP 脚本管，两层分工与 wallet.spec.ts 同。
 */
describe('apiInterceptor', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  /** 跑一次拦截器，把出站的那个请求捞出来 */
  const outgoing = async (req: HttpRequest<unknown>): Promise<HttpRequest<unknown>> => {
    let seen: HttpRequest<unknown> | null = null;
    await firstValueFrom(
      apiInterceptor(req, (r) => {
        seen = r;
        return of(new HttpResponse({ status: 200 }));
      }),
    );
    if (!seen) throw new Error('拦截器没有放行请求');
    return seen;
  };

  const probe = () => outgoing(new HttpRequest('GET', '/api/v1/wallet/info'));

  it('未登录也带 X-Language，兜底值是 zh（不是 en）', async () => {
    const req = await probe();
    // 兜底写 'en' 会把服务端自己的 zh 默认压掉 ⇒ 中文界面弹英文响应文案；
    // 写成「没有就不发」则会掉到 Accept-Language，法语浏览器拿到法语文案。两处都不行。
    expect(req.headers.get('X-Language')).toBe('zh');
  });

  it('X-Language 跟着 localStorage 的 gp_language 走（与 apps/react 同一个键）', async () => {
    localStorage.setItem('gp_language', 'ja-JP');
    const req = await probe();
    expect(req.headers.get('X-Language')).toBe('ja-JP');
  });

  it('language.get() 空值兜底 zh，非空原样返回', () => {
    expect(language.get()).toBe('zh');
    localStorage.setItem('gp_language', 'fr');
    expect(language.get()).toBe('fr');
    // 空串也是「没选过」：store.get 回 ''，仍走兜底
    localStorage.setItem('gp_language', '');
    expect(language.get()).toBe('zh');
  });

  it('登录态仍带 Authorization（改名/加头不许弄丢 Bearer）', async () => {
    tokens.save('at-1', 'rt-1');
    const req = await probe();
    expect(req.headers.get('Authorization')).toBe('Bearer at-1');
    expect(req.headers.get('X-Language')).toBe('zh');
  });

  it('未登录不发 Authorization', async () => {
    const req = await probe();
    expect(req.headers.has('Authorization')).toBe(false);
  });
});
