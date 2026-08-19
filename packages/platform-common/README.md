# erik/platform-common

共享 `common\service\*`，供 admin/ 与 service/ 通过 Composer path 仓库引用。

## 服务

- DepositLogService — 充值审计 + 营收/转化
- GameDashboardService — 运营仪表盘
- ProbabilityService — 概率分析
- GamePlayLogService — 游戏行为日志写入

依赖宿主提供 `app\model\*`、`app\common\SnowflakeService`、`support\Db`、`support\Log`。

## 接入

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## 剩余双份

app/model/*、app/common/*Service、多数 app/service/*、EventBus 仍双侧复制。
