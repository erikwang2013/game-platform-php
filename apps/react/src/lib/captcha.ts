/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 点击验证码的纯逻辑（取图数据描述 + 坐标换算）；
 * 弹框 UI 在 components/CaptchaModal.tsx，这里不引 React，便于 node --test 直接跑。
 */

/** 验证码画布恒为 300×200（easy/medium/hard 三档一致）；图片未加载完时用它兜底 */
export const CANVAS_W = 300;
export const CANVAS_H = 200;

/** 一个已标注的点，坐标是图片原始像素（服务端按 300×200 原图校验） */
export type Click = { x: number; y: number };

/** 验证码回执：与业务字段同级追加进请求体，四个端点通用 */
export type CaptchaProof = { captcha_key: string; clicks: Click[] };

/** 后端 /api/v1/captcha/generate 响应；image 为裸 base64（服务端已剥离 data URI 前缀） */
export type CaptchaData = {
  key?: string;
  image?: string;
  extra?: { texts?: { text?: string; order?: number }[] };
};

export type CaptchaView = {
  /** 可直接用于 <img src> 的图片地址 */
  imgSrc?: string;
  /** 待点击文字，按服务端要求的点击顺序排列 */
  texts: { text?: string; order?: number }[];
  /** 必须标注的点数：服务端逐点比对，数量需精确相等（多标/少标都判失败） */
  required: number;
  hint: string;
};

export function describeCaptcha(captcha: CaptchaData | null): CaptchaView {
  const texts = (captcha?.extra?.texts ?? []).slice().sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
  const required = texts.length || 2;
  const image = captcha?.image;
  return {
    imgSrc: image ? (image.startsWith('data:') ? image : `data:image/png;base64,${image}`) : undefined,
    texts,
    required,
    hint: texts.length
      ? `按顺序点击：${texts.map((item) => item.text).join(' → ')}`
      : `点击图中 ${required} 个位置完成验证`,
  };
}

/**
 * 显示框内的点击坐标 → 图片原始像素坐标。
 * 显示宽度随弹框变化，必须按「显示框 → 画布」等比换算；写死 400×250 之类的尺寸会让坐标系统性偏移。
 */
export function toImageCoords(
  clientX: number,
  clientY: number,
  box: { left: number; top: number; width: number; height: number },
  size: { w: number; h: number },
): Click | null {
  if (!box.width || !box.height) return null;
  return {
    x: Math.round(((clientX - box.left) / box.width) * size.w),
    y: Math.round(((clientY - box.top) / box.height) * size.h),
  };
}
