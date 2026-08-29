# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · **Français** · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)


Partage `common\service\*`, référencé par admin/ et service/ via le dépôt de chemin Composer.

## Services

- DepositLogService — audit des recharges + revenus/conversion
- GameDashboardService — tableau de bord opérationnel
- ProbabilityService — analyse de probabilités
- GamePlayLogService — écriture des journaux de comportement de jeu

Dépend de l'hôte pour `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log`.

## Intégration

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## Doubles restants

app/model/*, app/common/*Service, la plupart des app/service/*, EventBus restent dupliqués des deux côtés.

## Mascotte du projet

![Mascotte du projet : Dicey](../../docs/mascot.svg)

**Dicey** — Mascotte de la plateforme. Le dé représente les jeux et le gameplay basé sur la probabilité, la pièce l'économie de la plateforme et les passerelles de paiement multiples, et le violet reflète l'identité du panneau d'administration. Fichier SVG : `docs/mascot.svg`, redimensionnable à l'infini pour la documentation, les logos et les produits dérivés.
