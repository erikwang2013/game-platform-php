# erik/platform-common

## 프로젝트 마스코트

<img src="../../docs/mascot.svg" width="120" alt="Dicey"/>

**다이시(Dicey)** — 플랫폼 마스코트. 주사위는 게임과 확률 기반 게임플레이를, 코인은 플랫폼 경제와 다중 결제 게이트웨이를, 보라색 메인 컬러는 관리자 브랜드를 상징합니다. SVG 파일: `docs/mascot.svg`, 문서·로고·굿즈에 무제한 확대 가능.
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · **한국어** · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

공유 `common\service\*`, admin/와 service/가 Composer path 저장소로 참조합니다.

## 서비스

- DepositLogService — 충전 감사 + 수익/전환
- GameDashboardService — 운영 대시보드
- ProbabilityService — 확률 분석
- GamePlayLogService — 게임 행동 로그 기록

호스트가 `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`를 제공하는 데 의존합니다.

## 연동

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## 남은 이중 복사

app/model/*, app/common/*Service, 대부분의 app/service/*, EventBus는 여전히 양쪽에 복사되어 있습니다.

