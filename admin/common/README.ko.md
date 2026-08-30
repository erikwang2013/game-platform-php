# common/ — 관리 백엔드 공용 라이브러리
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · **한국어** · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

관리 백엔드(admin/)의 공용 코드 디렉터리. `common\service\*`는 공유 패키지 **erik/platform-common**(`packages/platform-common`)으로 추출되었으며, 이 디렉터리에는 PHP 클래스를 두지 마세요(패키지 오토로드를 가릴 수 있습니다). 자세한 내용은 `packages/platform-common/README.md`를 참조하세요.

## 기능 설명

| 분류 | 위치 | 설명 |
|------|------|------|
| 모델 | `app\model\*` | 데이터 모델(사용자/주문/게임 등) |
| 서비스 | `common\service\*` | 공유 비즈니스 서비스(erik/platform-common 패키지 내): DepositLogService(충전 감사+수익/전환), GameDashboardService(운영 대시보드), ProbabilityService(확률 분석), GamePlayLogService(게임 행동 로그 기록) |
| 미들웨어 | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## 설치

admin 프로젝트의 일부로 의존성은 `admin/composer.json`에 선언되어 있으며(path 저장소 `../packages/platform-common` 포함) `composer install` 시 자동으로 설치됩니다. 별도 설치는 필요 없습니다:

```bash
cd admin && composer install
```

## 사용 방법

- `app\...` 네임스페이스는 admin 프로젝트 자체 코드에 대응합니다. 예: `use app\model\User;`
- `common\...` 네임스페이스는 공유 패키지 erik/platform-common(PSR-4 → `src/`)에 대응합니다. 예:

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
