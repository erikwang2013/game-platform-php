# erik/platform-common
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
