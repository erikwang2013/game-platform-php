# erik/platform-common
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)


Разделяет `common\service\*`, на который admin/ и service/ ссылаются через Composer path-репозиторий.

## Сервисы

- DepositLogService — аудит пополнений + доход/конверсия
- GameDashboardService — операционный дашборд
- ProbabilityService — анализ вероятностей
- GamePlayLogService — запись журнала игрового поведения

Зависит от предоставляемых хостом `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Подключение

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## Остающиеся дубликаты

app/model/*, app/common/*Service, большинство app/service/* и EventBus по-прежнему продублированы в обеих частях.
