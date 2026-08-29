-- ============================================================
-- CDN 管理端配置迁移：game_cdn_provider 表 + 五厂商种子
-- 2026-08-29
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_29_cdn_provider.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `game_cdn_provider` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '雪花ID',
    `name` VARCHAR(50) NOT NULL COMMENT '显示名称',
    `provider` VARCHAR(30) NOT NULL COMMENT '厂商: cloudflare/cloudfront/aliyun/tencent/huawei',
    `config` TEXT NULL COMMENT '加密JSON配置（凭据/桶/域名）',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
    `sort` INT NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CDN厂商配置';

-- 默认 CDN 厂商（凭据为空，status=0 停用，管理端填写凭据后启用）
INSERT IGNORE INTO `game_cdn_provider` (`id`, `name`, `provider`, `config`, `status`, `sort`) VALUES
(50000000000000061, 'Cloudflare R2', 'cloudflare', '{"bucket":"static","domain":"cdn.example.com","account_id":"","api_token":"","zone_id":"","s3":{"region":"auto","access_key_id":"","secret_access_key":""}}', 0, 10),
(50000000000000062, 'AWS CloudFront', 'cloudfront', '{"bucket":"static","domain":"d111111abcdef8.cloudfront.net","distribution_id":"","s3":{"region":"us-east-1","access_key_id":"","secret_access_key":""}}', 0, 20),
(50000000000000063, '阿里云 OSS', 'aliyun', '{"bucket":"static","domain":"cdn.aliyun.example.com","access_key_id":"","access_key_secret":"","region":"oss-cn-hangzhou"}', 0, 30),
(50000000000000064, '腾讯云 COS', 'tencent', '{"bucket":"static","domain":"cdn.tencent.example.com","secret_id":"","secret_key":"","region":"ap-guangzhou"}', 0, 40),
(50000000000000065, '华为云 OBS', 'huawei', '{"bucket":"static","domain":"cdn.huawei.example.com","ak":"","sk":"","region":"cn-north-4"}', 0, 50);
