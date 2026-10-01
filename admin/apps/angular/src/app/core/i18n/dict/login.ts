/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 登录页词条（login.ts：账号密码 + 点击式验证码弹框）。
 *
 * 与 `dict/frame.ts` 同一套约定：`键: [英文, 中文]`、占位符 `{name}`。**中文一侧逐字等于抽取前的
 * 界面原文**（本批只做抽取、不动文案），英文沿用 flutter translations.dart 的 `login.*` 说法。
 *
 * ⚠ 三处刻意按**本树**原文写，别照 flutter 反改：`login.username`（本树标签是「账号」，
 * flutter 是「用户名」）、`login.captcha_prompt`（本树是「请按图片提示依次点击」）、
 * `login.captcha_clicked`（本树带计数参数，flutter 只有一个词）。页头那个标题复用 `app.brand`。
 */
export const LOGIN: Record<string, [string, string]> = {
  'login.subtitle': ['Inkwell console · Admin', 'Inkwell 控制台 · 管理端'],

  // ---- 表单 ----
  'login.username': ['Username', '账号'],
  'login.username_hint': ['Admin account', '管理员账号'],
  'login.password': ['Password', '密码'],
  'login.password_hint': ['Login password', '登录密码'],
  'login.login': ['Log in', '登 录'],
  'login.submitting': ['Signing in…', '登录中…'],

  // ---- 点击式验证码弹框 ----
  'login.captcha': ['Security check', '安全验证'],
  'login.captcha_prompt': ['Click the image as prompted', '请按图片提示依次点击'],
  'login.captcha_alert': ['Captcha image', '点击验证码'],
  'login.captcha_clicked': ['Clicked {n} of {need}', '已点击 {n} 点（需 {need} 点）'],
  'login.captcha_undo': ['Undo', '撤销'],
  'login.captcha_refresh': ['New image', '换一张'],
  'login.captcha_confirm': ['Confirm sign-in', '确认登录'],
  'login.captcha_loading': ['Loading captcha…', '验证码加载中…'],
  'login.captcha_empty': [
    'The captcha service returned nothing, please retry later',
    '验证码服务返回为空，请稍后重试',
  ],
};
