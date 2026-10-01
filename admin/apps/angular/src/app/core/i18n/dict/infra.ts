/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 基础设施页词条（infra.ts：CDN 厂商 / 国家配置）。
 *
 * 键名沿用 `admin/apps/flutter` 的 translations.dart —— 那边正好有 `cdn.*` 与 `country_config.*`
 * 两族同名页面，同名同义的（cdn.name / cdn.provider / cdn.config / country_config.country_code …）
 * 逐字沿用那边的键名；列头与「测试回执」是那边没有的，按本页语义新写。
 * 中文一侧**逐字等于抽取前的界面原文**。
 */
export const INFRA: Record<string, [string, string]> = {
  'infra.title': ['Infrastructure', '基础设施'],
  'infra.subtitle': ['CDN providers / Country configs', 'CDN 厂商 / 国家配置'],

  // ---- CDN 厂商 ----
  'cdn.title': ['CDN Providers', 'CDN 厂商'],
  'cdn.noun': ['CDN provider', 'CDN 厂商'],
  'cdn.name': ['Display name', '显示名称'],
  'cdn.name_hint': ['Max 50 characters', '最长 50'],
  'cdn.provider': ['Provider', '厂商'],
  /** create 的 validator 里 status 是 required|in:0,1（update 不收）⇒ label 上直说 */
  'cdn.status_required': ['Status (required on create)', '状态（新建必填）'],
  'cdn.sort': ['Sort', '排序'],
  'cdn.sort_hint': ['Smaller comes first', '越小越靠前'],
  'cdn.config': ['Credentials (JSON)', '凭据配置（JSON）'],
  'cdn.config_hint': [
    '{"bucket":"...","access_key":"..."}; the list never returns the stored value, empty = keep unchanged',
    '{"bucket":"...","access_key":"..."}；列表不回传原值，留空 = 不修改',
  ],
  'cdn.test': ['Connectivity test', '连通测试'],
  /** 列头（枚举含义写进表头，值原样显示） */
  'cdn.head.status': ['Status (0 disabled / 1 enabled)', '状态(0禁用/1启用)'],
  'cdn.head.created': ['Created', '创建时间'],
  'cdn.head.updated': ['Updated', '更新时间'],
  /** 连通测试的就地回执（成功走测试结论、失败是 422 的 message） */
  'cdn.testing': ['Testing "{who}"…', '正在测试「{who}」…'],
  'cdn.test_success': ['Connection OK', '连通正常'],
  'cdn.test_ok': ['"{who}": {message}', '「{who}」：{message}'],
  'cdn.test_fail': ['Connectivity test failed for "{who}": {error}', '「{who}」连通测试失败：{error}'],

  // ---- 国家配置 ----
  'country_config.title': ['Country Configs', '国家配置'],
  'country_config.noun': ['Country config', '国家配置'],
  'country_config.country_code': ['Country code', '国家代码'],
  'country_config.country_code_hint': ['ISO 3166-1 alpha-2, e.g. CN', 'ISO 3166-1 alpha-2，如 CN'],
  'country_config.currency': ['Currency code', '货币代码'],
  'country_config.currency_hint': ['ISO 4217, e.g. CNY', 'ISO 4217，如 CNY'],
  'country_config.payment_methods': ['Payment methods (JSON)', '支付方式（JSON）'],
  'country_config.payment_methods_hint': [
    '["stripe","paypal"] or {"stripe":{"enabled":true}}',
    '["stripe","paypal"] 或 {"stripe":{"enabled":true}}',
  ],
  'country_config.withdraw_methods': ['Withdraw methods (JSON)', '提现方式（JSON）'],
  'country_config.withdraw_methods_hint': ['["paypal","bank","crypto"]', '["paypal","bank","crypto"]'],
  'country_config.min_deposit': ['Min deposit', '最低充值额'],
  'country_config.min_deposit_hint': [
    'Decimal amount, e.g. 10.0000; empty = keep unchanged (new rows default to 1.0000)',
    '十进制金额，如 10.0000；留空 = 不改（新建默认 1.0000）',
  ],
};
