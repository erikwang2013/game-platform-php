/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * KYC「国家/地区」下拉的选项。
 *
 * 选项来自 `GET /country/list`（只有在册且启用的国家代码）。**当前值不在选项里时要补一条**：
 * 服务端对 KYC 的 country 只校验 `nullable|string|max:50`、不校验在册
 * （`IdentityController:61`），自由文本框时代可能已落库「Chna」这类串 —— 下拉若只认端点回的
 * 代码，旧值会被渲染成空（select 找不到匹配 option 就退成第一项），用户接着提交就把它抹掉了。
 */
export function countryOptions(codes: string[], current: string): string[] {
  const v = current.trim();
  return v && !codes.includes(v) ? [...codes, v] : codes;
}
