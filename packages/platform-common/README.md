# erik/platform-common

## 项目吉祥物

<img src="../../docs/mascot.svg" width="120" alt="Dicey"/>

**小骰（Dicey）** — 平台吉祥物。骰子代表游戏与概率玩法，金币代表平台经济与多支付网关，紫色主色调呼应后台品牌。SVG 源文件：`docs/mascot.svg`，可无限缩放用于文档、Logo 与周边。
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

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

