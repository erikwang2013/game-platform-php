/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { Api, CaptchaChallenge, CaptchaProof, Click } from './api.service';
import { CaptchaBox } from './captcha';

/**
 * 弹框验证码的钉子。钉三件容易退化的东西：
 * ① 坐标按「显示框 → 图片原始像素」等比换算（画布 300×200 由 naturalWidth/Height 给出；
 *    写死 400×250 会让坐标系统性偏移、验证码永远点不对）；
 * ② 确认门控：点数不足不可确认，点满后多余的点击不再记录，撤销回退一格；
 * ③ 确认只交出 {captcha_key, clicks}（取图/换一张在组件内，网络请求由调用方发起）。
 */
describe('CaptchaBox', () => {
  const challenge: CaptchaChallenge = { key: 'k1', image: 'AAAA', texts: ['川', '囧'] };

  type Mark = Click & { px: number; py: number };
  type Probe = {
    cap: { set(v: CaptchaChallenge | null): void };
    marks: () => Mark[];
    required: () => number;
    canConfirm: () => boolean;
    hit(ev: MouseEvent): void;
    undo(): void;
    confirm(): void;
    proof: { subscribe(fn: (p: CaptchaProof) => void): unknown };
  };

  let probe: Probe;
  let emitted: CaptchaProof[];

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [{ provide: Api, useValue: { captcha: () => of(challenge) } }],
    });
    // inject(Api)/effect() 写在字段初始化器与构造器里 ⇒ 必须在注入上下文里构造
    const box = TestBed.runInInjectionContext(() => new CaptchaBox());
    probe = box as unknown as Probe;
    emitted = [];
    probe.proof.subscribe((p) => emitted.push(p));
  });

  /** 假 img：显示框为 left/top + w×h，原始画布另给（默认 300×200） */
  function fakeImg(box: { w: number; h: number; left?: number; top?: number }, nw = 300, nh = 200) {
    return {
      getBoundingClientRect: () => ({
        left: box.left ?? 0,
        top: box.top ?? 0,
        width: box.w,
        height: box.h,
      }),
      naturalWidth: nw,
      naturalHeight: nh,
    };
  }

  function click(img: unknown, clientX: number, clientY: number): MouseEvent {
    return { currentTarget: img, clientX, clientY } as unknown as MouseEvent;
  }

  it('点击坐标按显示框 → 画布原始像素等比换算', () => {
    probe.cap.set(challenge);

    // 图被缩到 150×100 显示，点其中心 → 原始像素 (150, 100)
    probe.hit(click(fakeImg({ w: 150, h: 100 }), 75, 50));
    expect(probe.marks()[0]).toMatchObject({ x: 150, y: 100, px: 50, py: 50 });

    // 显示框有偏移时先减去偏移：画布 400×200 上左移 10px 的点 → 原始 x = 200
    probe.hit(click(fakeImg({ w: 400, h: 200, left: 10, top: 20 }, 400, 200), 210, 120));
    expect(probe.marks()[1]).toMatchObject({ x: 200, y: 100 });
  });

  it('点数不足不可确认；点满后多余的点击与撤销都按预期收口', () => {
    probe.cap.set(challenge);
    expect(probe.required()).toBe(2);
    expect(probe.canConfirm()).toBe(false);

    probe.hit(click(fakeImg({ w: 300, h: 200 }), 10, 10));
    expect(probe.canConfirm()).toBe(false);

    probe.hit(click(fakeImg({ w: 300, h: 200 }), 20, 20));
    expect(probe.canConfirm()).toBe(true);

    probe.hit(click(fakeImg({ w: 300, h: 200 }), 30, 30));
    expect(probe.marks()).toHaveLength(2);

    probe.undo();
    expect(probe.marks()).toHaveLength(1);
    expect(probe.canConfirm()).toBe(false);
  });

  it('点满才交出 captcha_key 与全部点位', () => {
    probe.cap.set(challenge);
    probe.hit(click(fakeImg({ w: 300, h: 200 }), 10, 10));
    probe.confirm();
    expect(emitted).toHaveLength(0);

    probe.hit(click(fakeImg({ w: 300, h: 200 }), 20, 20));
    probe.confirm();
    expect(emitted).toEqual([
      {
        captcha_key: 'k1',
        clicks: [
          { x: 10, y: 10 },
          { x: 20, y: 20 },
        ],
      },
    ]);
  });
});
