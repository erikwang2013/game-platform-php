#!/usr/bin/env bash
# ============================================================
# 数据库恢复脚本
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
#
# 用法: bash database/backup/restore.sh <备份文件.sql.gz>
# 示例: bash database/backup/restore.sh database/backup/backup_20260520_120000.sql.gz
# ============================================================

set -euo pipefail

if [ $# -lt 1 ]; then
    echo "用法: bash database/backup/restore.sh <备份文件.sql.gz>"
    echo "示例: bash database/backup/restore.sh database/backup/backup_20260520_120000.sql.gz"
    exit 1
fi

BACKUP_FILE="$1"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "错误: 文件不存在 — $BACKUP_FILE"
    exit 1
fi

cd "$(dirname "$0")/../.."

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-open_admin}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"

echo "=========================================="
echo "  数据库恢复"
echo "  源文件: $BACKUP_FILE"
echo "  目标库: $DB_DATABASE @ $DB_HOST:$DB_PORT"
echo "=========================================="
echo ""
read -rp "确认恢复？这将覆盖现有数据！[y/N] " confirm

if [ "${confirm,,}" != "y" ]; then
    echo "已取消"
    exit 0
fi

echo "[$(date)] Starting restore..."

gunzip -c "$BACKUP_FILE" | MYSQL_PWD="$DB_PASSWORD" mysql \
    -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE"

echo "[$(date)] Restore complete!"
