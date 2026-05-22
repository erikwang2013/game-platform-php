#!/usr/bin/env bash
# ============================================================
# 数据库备份脚本
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
#
# 用法: bash database/backup/backup.sh
# 输出: database/backup/backup_YYYYMMDD_HHMMSS.sql.gz
# 定时: 0 2 * * * cd /path/to/open-admin && bash database/backup/backup.sh
# ============================================================

set -euo pipefail

cd "$(dirname "$0")/../.."

# 从 .env 或默认值读取数据库配置
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-open_admin}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"

BACKUP_DIR="database/backup"
BACKUP_FILE="${BACKUP_DIR}/backup_$(date +%Y%m%d_%H%M%S).sql.gz"

# 保留最近 30 天的备份
RETENTION_DAYS=30

mkdir -p "$BACKUP_DIR"

echo "[$(date)] Starting backup: $DB_DATABASE → $BACKUP_FILE"

MYSQL_PWD="$DB_PASSWORD" mysqldump \
    -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
    --single-transaction \
    --routines \
    --triggers \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE" | gzip > "$BACKUP_FILE"

echo "[$(date)] Backup complete: $(du -h "$BACKUP_FILE" | cut -f1)"

# 清理旧备份
find "$BACKUP_DIR" -name "backup_*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete 2>/dev/null || true
echo "[$(date)] Cleaned backups older than $RETENTION_DAYS days"
