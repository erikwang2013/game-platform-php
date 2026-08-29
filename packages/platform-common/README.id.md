# erik/platform-common
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)


Berbagi `common\service\*`, untuk dirujuk admin/ dan service/ melalui path repository Composer.

## Layanan

- DepositLogService — audit deposit + pendapatan/konversi
- GameDashboardService — dasbor operasional
- ProbabilityService — analisis probabilitas
- GamePlayLogService — penulisan log perilaku game

Dependensi host menyediakan `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Integrasi

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## Sisa Salinan Ganda

app/model/*, app/common/*Service, mayoritas app/service/*, EventBus masih disalin dua sisi.

## Maskot Proyek

![Maskot proyek: Dicey](../../docs/mascot.svg)

**Dicey** — Maskot platform. Dadu melambangkan permainan dan gameplay berbasis probabilitas, koin melambangkan ekonomi platform dan multi-gateway pembayaran, dan warna ungu mencerminkan branding admin. File SVG: `docs/mascot.svg`, dapat diskalakan tanpa batas untuk dokumen, logo, dan merchandise.
