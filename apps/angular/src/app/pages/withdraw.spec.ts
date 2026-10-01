/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { WithdrawApplied } from '../core/api.service';
import { WithdrawPage } from './withdraw';

type Sig<T> = { (): T; set(v: T): void };
type Probe = { done: Sig<WithdrawApplied | null> };

/**
 * 提现结果面板的**金额渲染**钉子（订单号那一格不是金额，不在断言范围）。
 *
 * 钉它的理由：面板里三处金额原先写的是裸插值 `{{ d.platform_amount }}` —— 直接把
 * `DECIMAL(20,8)` 的后端串（`1234.56780000`）摆到用户眼前，既没有千分位也与同页余额格
 * 的格式不一致；收编进 `money()` 之后，这三处**只能靠本文件**证明真的走了格式化 ——
 * 页面没有别的观察者（`/tmp` 的真机脚本不进 CI，功能撤了也不会有人被提醒）。
 *
 * 断言分两层：**正文**是 `money()` 的产物（去逗号后按判据值比对）、**title** 是 `moneyRaw()`
 * 的原样后端串 —— 两者一起钉住「显示无损 + 悬停看真值」这条契约；只钉正文的话，
 * 把 title 挂成 money() 的结果也照样绿。
 */
describe('WithdrawPage 提现结果面板金额', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<WithdrawPage>;
  let page: WithdrawPage;

  /** 判据值都挑「旧实现会出错」的量级：>2^53、scale-8 最小非零量、尾零、负零 */
  const RAW = {
    platform_amount: '12345678901234567890.12',
    fee: '0.00000001',
    actual_amount: '628.5000',
    balance_after: '-0.00000000',
  };

  /** 按 `.muted` 标签文字定位那一格，返回它的金额 span */
  const cell = (label: string): HTMLElement => {
    const rows = Array.from(fixture.nativeElement.querySelectorAll('.kv')) as HTMLElement[];
    const row = rows.find(
      (r) => (r.querySelector('.muted')?.textContent ?? '').trim() === label,
    );
    if (!row) throw new Error(`结果面板里没有「${label}」这一格`);
    const v = row.querySelector('.mono');
    if (!v) throw new Error(`「${label}」这一格没有值元素`);
    return v as HTMLElement;
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
    fixture = TestBed.createComponent(WithdrawPage);
    page = fixture.componentInstance;
    // 构造函数里拉一次 KYC 档位（取不到就整条不显示，不挡提现）
    http.expectOne('/api/v1/user/identity/status').flush({ code: 0, message: 'ok', data: null });
    // 结果面板只在 done() 有值时才渲染 —— 真机那条路要过验证码弹框，这里直接把状态灌进去
    (page as unknown as Probe).done.set({
      order_id: '1',
      order_no: 'W20260101001',
      status: 'pending',
      created_at: '2026-01-01 10:00:00',
      ...RAW,
    });
    fixture.detectChanges();
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('正文走 money()：千分位 + 小数位无损（去逗号后逐字比对）', () => {
    expect(cell('提现金额').textContent!.trim().replace(/,/g, '')).toBe(
      '12345678901234567890.12',
    );
    // 手续费是 scale-8 最小非零量：截到 2 位会显示成 0.00（非零显示成零）
    expect(cell('手续费').textContent!.trim().replace(/,/g, '')).toBe('0.00000001');
    // 尾零去掉但至少留 2 位
    expect(cell('实际到账').textContent!.trim().replace(/,/g, '')).toBe('628.50');
  });

  it('标题位是千分位形态（正文确实过了分组，不是原样回显）', () => {
    // 上面那条把逗号去掉了，防的是「分组那条挂了也绿」；这里单独钉分组本身
    expect(cell('提现金额').textContent!.trim()).toBe('12,345,678,901,234,567,890.12');
  });

  it('负零按零显示（-0.00000000 → 0.00），但 title 仍是后端原始串', () => {
    expect(cell('账户余额').textContent!.trim()).toBe('0.00');
    expect(cell('账户余额').getAttribute('title')).toBe('-0.00000000');
  });

  it('每格金额的 title 都是**后端原始串**（完整精度），与正文同源同格', () => {
    expect(cell('提现金额').getAttribute('title')).toBe(RAW.platform_amount);
    expect(cell('手续费').getAttribute('title')).toBe(RAW.fee);
    expect(cell('实际到账').getAttribute('title')).toBe(RAW.actual_amount);
    expect(cell('账户余额').getAttribute('title')).toBe(RAW.balance_after);
  });

  it('非金额的格子不挂 title（订单号/提交时间不该被顺手货币化）', () => {
    expect(cell('订单号').getAttribute('title')).toBeNull();
    expect(cell('订单号').textContent!.trim()).toBe('W20260101001');
    expect(cell('提交时间').getAttribute('title')).toBeNull();
  });
});
