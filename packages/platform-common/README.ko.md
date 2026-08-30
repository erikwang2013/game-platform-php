# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · **한국어** · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

admin/와 service/가 공유하는 `common\service\*` 공통 레이어. Composer path 저장소로 로컬 소스를 참조합니다.

## 서비스

| 서비스 | 설명 |
|------|------|
| DepositLogService | 충전 감사 + 수익/전환 |
| GameDashboardService | 운영 대시보드 |
| ProbabilityService | 확률 분석 |
| GamePlayLogService | 게임 행동 로그 기록 |
| CircuitBreaker / Retry | 안정성 메커니즘(서킷 브레이커/재시도) |

호스트가 `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`를 제공하는 데 의존합니다.

## 설치

패키지 이름은 `erik/platform-common`. admin/와 service/ 모두 composer.json에 path 저장소(`../packages/platform-common`)를 설정했으므로 `composer install` 시 자동으로 설치됩니다. admin/ 또는 service/에서 개별 업데이트도 가능합니다:

```bash
composer update erik/platform-common
```

Packagist에 공개된 경우 직접 설치할 수도 있습니다:

```bash
composer require erik/platform-common
```

## 사용 방법

네임스페이스는 `common\`(PSR-4 → `src/`):

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## 원클릭 설치

플랫폼 원클릭 설치 마법사(`install/`)로 자동 완료됩니다. 마법사가 admin/와 service/의 `composer install`을 실행하면 path 저장소 의존성이 자동 설치되므로 수동 설정이 필요 없습니다.

## 남은 이중 복사

`app/model/*`, `app/common/*Service`, 대부분의 `app/service/*`, EventBus는 여전히 양쪽에 복사되어 있습니다.
