-- ============================================================
-- 权限种子数据
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 初始化 RBAC 权限树和角色-权限关联
-- 超级管理员 (super_admin) 自动获得所有权限
-- ============================================================

-- 菜单权限 (type=1)
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000001, '0', '仪表盘',    'dashboard',     1, 'dashboard', '/dashboard',        1, NOW(), NOW()),
(21000000000000002, '0', '用户管理',  'user',           1, 'people',    '/admin/user',        2, NOW(), NOW()),
(21000000000000003, '0', '角色管理',  'role',           1, 'shield',    '/admin/role',        3, NOW(), NOW()),
(21000000000000004, '0', '权限管理',  'permission',     1, 'lock',      '/admin/permission',  4, NOW(), NOW()),
(21000000000000005, '0', '系统配置',  'config',         1, 'settings',  '/admin/config',      5, NOW(), NOW()),
(21000000000000006, '0', '操作日志',  'log',            1, 'article',   '/admin/log',         6, NOW(), NOW());

-- 按钮权限 (type=2)
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000011, 21000000000000002, '批量删除',     'batch.destroy', 2, '', '', 1, NOW(), NOW()),
(21000000000000012, 21000000000000002, '批量启用/禁用', 'batch.status', 2, '', '', 2, NOW(), NOW()),
(21000000000000013, 21000000000000002, '导入用户',     'import.users',  2, '', '', 3, NOW(), NOW()),
(21000000000000014, 21000000000000002, '导出Excel',     'export.excel',  2, '', '', 4, NOW(), NOW()),
(21000000000000015, 21000000000000002, '导出PDF',       'export.pdf',    2, '', '', 5, NOW(), NOW()),
(21000000000000016, 21000000000000002, '文件上传',     'upload',         2, '', '', 6, NOW(), NOW());

-- API 权限 (type=3) — 仪表盘
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000021, 21000000000000001, '查看仪表盘',   'get.admin/dashboard', 3, '', '', 1, NOW(), NOW());

-- API 权限 — 用户管理
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000031, 21000000000000002, '查看用户',     'get.admin/user',             3, '', '', 1, NOW(), NOW()),
(21000000000000032, 21000000000000002, '创建用户',     'post.admin/user',            3, '', '', 2, NOW(), NOW()),
(21000000000000033, 21000000000000002, '更新用户',     'put.admin/user',             3, '', '', 3, NOW(), NOW()),
(21000000000000034, 21000000000000002, '删除用户',     'delete.admin/user',          3, '', '', 4, NOW(), NOW()),
(21000000000000035, 21000000000000002, '批量删除用户', 'post.admin/user/batch/destroy', 3, '', '', 5, NOW(), NOW()),
(21000000000000036, 21000000000000002, '批量启禁用',   'post.admin/user/batch/status',  3, '', '', 6, NOW(), NOW());

-- API 权限 — 角色管理
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000041, 21000000000000003, '查看角色', 'get.admin/role',    3, '', '', 1, NOW(), NOW()),
(21000000000000042, 21000000000000003, '创建角色', 'post.admin/role',   3, '', '', 2, NOW(), NOW()),
(21000000000000043, 21000000000000003, '更新角色', 'put.admin/role',    3, '', '', 3, NOW(), NOW()),
(21000000000000044, 21000000000000003, '删除角色', 'delete.admin/role', 3, '', '', 4, NOW(), NOW());

-- API 权限 — 权限管理
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000051, 21000000000000004, '查看权限', 'get.admin/permission',    3, '', '', 1, NOW(), NOW()),
(21000000000000052, 21000000000000004, '创建权限', 'post.admin/permission',   3, '', '', 2, NOW(), NOW()),
(21000000000000053, 21000000000000004, '更新权限', 'put.admin/permission',    3, '', '', 3, NOW(), NOW()),
(21000000000000054, 21000000000000004, '删除权限', 'delete.admin/permission', 3, '', '', 4, NOW(), NOW());

-- API 权限 — 系统配置
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000061, 21000000000000005, '查看配置', 'get.admin/config',    3, '', '', 1, NOW(), NOW()),
(21000000000000062, 21000000000000005, '创建配置', 'post.admin/config',   3, '', '', 2, NOW(), NOW()),
(21000000000000063, 21000000000000005, '更新配置', 'put.admin/config',    3, '', '', 3, NOW(), NOW()),
(21000000000000064, 21000000000000005, '删除配置', 'delete.admin/config', 3, '', '', 4, NOW(), NOW());

-- API 权限 — 操作日志
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000071, 21000000000000006, '查看日志', 'get.admin/log', 3, '', '', 1, NOW(), NOW());

-- API 权限 — 个人中心
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000081, '0', '个人中心-更新信息', 'put.admin/profile',         3, '', '', 1, NOW(), NOW()),
(21000000000000082, '0', '个人中心-修改密码', 'put.admin/profile/password', 3, '', '', 2, NOW(), NOW()),
(21000000000000083, '0', '个人中心-登出',     'post.admin/profile/logout',  3, '', '', 3, NOW(), NOW());

-- API 权限 — 导出
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000091, '0', '导出Excel', 'post.admin/export/excel', 3, '', '', 1, NOW(), NOW()),
(21000000000000092, '0', '导出PDF',   'post.admin/export/pdf',   3, '', '', 2, NOW(), NOW());

-- API 权限 — 导入
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000093, '0', '导入用户', 'post.admin/import/users', 3, '', '', 1, NOW(), NOW());

-- API 权限 — 上传
INSERT INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000094, '0', '文件上传', 'post.admin/upload', 3, '', '', 1, NOW(), NOW());

-- ============================================================
-- 超级管理员角色 (ID=10000000000000001) 关联所有权限
-- ============================================================
INSERT INTO `erik_admin_role_permission` (`role_id`, `permission_id`)
SELECT 10000000000000001, `id` FROM `erik_admin_permission`
WHERE `id` NOT IN (
    SELECT `permission_id` FROM `erik_admin_role_permission` WHERE `role_id` = 10000000000000001
);
