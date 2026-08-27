#!/usr/bin/env bash
# 全量 API 测试入口: admin + service。
# 前置: 两个服务已按 docs/test-reports/api.md 中的命令启动。
set -u
cd "$(dirname "$0")/../.."
PRELOAD=/tmp/gp-env-preload.php
[ -f "$PRELOAD" ] || { echo "缺少测试环境预载文件 $PRELOAD (见 docs/test-reports/api.md)"; exit 2; }

echo "########## admin (管理端) ##########"
php -d auto_prepend_file="$PRELOAD" tests/api/admin_test.php
ADMIN_RC=$?

echo
echo "########## service (服务端) ##########"
php -d auto_prepend_file="$PRELOAD" tests/api/service_test.php
SVC_RC=$?

echo
echo "admin rc=$ADMIN_RC, service rc=$SVC_RC"
[ $ADMIN_RC -eq 0 ] && [ $SVC_RC -eq 0 ] && exit 0
exit 1
