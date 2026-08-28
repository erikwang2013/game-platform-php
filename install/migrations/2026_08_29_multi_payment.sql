-- ============================================================
-- 多支付接入迁移：国际主流 + 按国支付 + USDT/加密货币
-- 2026-08-29
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_29_multi_payment.sql
-- ============================================================

-- 1. game_deposit_order 增加支付链接与过期时间
ALTER TABLE `game_deposit_order`
    ADD COLUMN `checkout_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '网关支付链接（Stripe PaymentIntent / NOWPayments payment_url / Coinbase hosted_url）' AFTER `transaction_id`,
    ADD COLUMN `expires_at` DATETIME DEFAULT NULL COMMENT '支付链接过期时间' AFTER `checkout_url`;

-- 2. game_payment_method 增加国家可见性/金额区间/币种限定
ALTER TABLE `game_payment_method`
    ADD COLUMN `countries` JSON NOT NULL COMMENT '可见国家码JSON数组，空数组或["*"]=全球' AFTER `sort`,
    ADD COLUMN `currency` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '限定币种（空=任意）' AFTER `countries`,
    ADD COLUMN `min_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '最小充值金额（订单币种，0=不限）' AFTER `currency`,
    ADD COLUMN `max_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '最大充值金额（订单币种，0=不限）' AFTER `min_amount`;

-- 3. 加密支付方式种子（provider 与 country_config.payment_methods 中 "crypto" 匹配，type='crypto'）
INSERT IGNORE INTO `game_payment_method` (`id`, `name`, `type`, `provider`, `config`, `status`, `sort`, `countries`, `currency`, `min_amount`, `max_amount`) VALUES
(50000000000000051, 'USDT (TRC20)', 'crypto', 'nowpayments', '{"network":"TRC20"}', 1, 10, '[]', '', 0.0000, 0.0000),
(50000000000000052, 'USDT (ERC20)', 'crypto', 'nowpayments', '{"network":"ERC20"}', 1, 20, '[]', '', 0.0000, 0.0000),
(50000000000000053, 'Crypto Wallet (Coinbase)', 'crypto', 'coinbase', '{"coin":"USDC"}', 1, 30, '[]', '', 0.0000, 0.0000);

-- 4. CN 国家配置：USDT 加密支付排第一（大陆无 Stripe），alipay/wechat 经 Stripe APM 受限
UPDATE `game_country_config` SET `payment_methods` = '["crypto","alipay","wechat"]' WHERE `country_code` = 'CN';
