/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { CountryOption } from '../core/api.service';
import { KycPage } from './kyc';

type Probe = { country: { set(v: string): void } };

/**
 * KYC「国家/地区」下拉的钉子。
 *
 * 改之前它是自由文本框（placeholder「如 CN」），而服务端对 country 只校验
 * `nullable|string|max:50`、**不校验在册** ⇒ 能填出「Chna」这种值。改成下拉后两侧都要钉：
 *   ① 选项来自 `GET /country/list`（不是页面里写死的表）；
 *   ② 旧值不在选项里时**仍要显示**（补一条）—— 否则 select 找不到匹配 option 会退成第一项，
 *      用户接着提交就把历史值抹掉了。
 * 变异：把这一处改回自由文本 ⇒ 本文件红。
 */
describe('KycPage 国家/地区下拉', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<KycPage>;
  let page: Probe;

  const row = (code: string): CountryOption => ({
    country_code: code,
    currency: 'USD',
    min_deposit: '1.0000',
  });

  const select = () =>
    fixture.nativeElement.querySelector('select[name="country"]') as HTMLSelectElement;

  const optionValues = () => [...select().querySelectorAll('option')].map((o) => o.value);

  /** 构造函数据并发两个请求：认证状态 + 国家列表（顺序不保证，按 URL 分别兑） */
  const flush = (codes: string[]): void => {
    http
      .expectOne('/api/v1/user/identity/status')
      .flush({ code: 0, message: 'ok', data: { status: 'not_submitted' } });
    http
      .expectOne('/api/v1/country/list')
      .flush({ code: 0, message: 'ok', data: { list: codes.map(row) } });
    fixture.detectChanges();
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', children: [] }]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(KycPage);
    page = fixture.componentInstance as unknown as Probe;
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('选项来自 /country/list（不是页面里写死的表），默认停在「未选择」', () => {
    flush(['CN', 'US', 'JP']);
    expect(optionValues()).toEqual(['', 'CN', 'US', 'JP']);
    expect(select().value).toBe('');
  });

  it('旧值不在选项里仍要显示：补一条，且它就是当前选中项', () => {
    flush(['CN', 'US']);
    page.country.set('Chna');
    fixture.detectChanges();
    expect(optionValues()).toEqual(['', 'CN', 'US', 'Chna']);
    expect(select().value).toBe('Chna');
  });

  it('旧值在册时不重复补，且仍选中它', () => {
    flush(['CN', 'US']);
    page.country.set('CN');
    fixture.detectChanges();
    expect(optionValues()).toEqual(['', 'CN', 'US']);
    expect(select().value).toBe('CN');
  });

  it('端点失败：降级成「只有未选择 + 提示」，不挡提交', () => {
    http
      .expectOne('/api/v1/user/identity/status')
      .flush({ code: 0, message: 'ok', data: { status: 'not_submitted' } });
    http.expectOne('/api/v1/country/list').flush({ code: 500, message: 'boom' });
    fixture.detectChanges();
    expect(optionValues()).toEqual(['']);
    expect(select().textContent).toContain('国家列表加载失败');
  });
});
