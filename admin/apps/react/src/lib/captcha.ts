/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

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
