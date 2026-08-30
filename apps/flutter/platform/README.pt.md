# game_platform — Plataforma de usuários (Flutter Web)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · **Português** · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

O frontend web da plataforma de usuários (lado C), baseado em Flutter 3.x, oferece aos usuários a experiência completa da plataforma de agregação de jogos: cadastro e login, lobby de jogos, carteira, depósito, saque, câmbio, rankings, cupons, notificações, chat, amigos e tickets de suporte.

## Funcionalidades

| Módulo | Descrição |
|------|------|
| Login/cadastro | Usuário+senha / OAuth / 2FA |
| Lobby de jogos | Lista/categorias/busca de jogos |
| Carteira | Saldos e transações de moedas da plataforma/jogo |
| Depósito | Escolher método de pagamento, redirecionar para o gateway |
| Saque | Solicitar saque, acompanhamento do status |
| Câmbio | Câmbio em tempo real moeda da plataforma ⇄ moeda de jogo |
| Rankings | Diário/semanal/mensal/geral |
| Cupons | Obter e usar |
| Notificações | Mensagens no app (depósito/saque/cupons, etc.) |
| Chat | Mensagens WebSocket em tempo real |
| Amigos | Sistema de amigos |
| Tickets | Criar e responder tickets de suporte |
| Perfil | Edição de perfil/configurações de segurança |

## Requisitos

- Flutter SDK 3.x

## Instalação e execução

```bash
cd apps/flutter/platform

# Instalar dependências
flutter pub get

# Executar em desenvolvimento (Chrome)
flutter run -d chrome

# Especificar o endereço do backend (padrão http://localhost:8788)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8788

# Build web de produção (saída em build/web/)
flutter build web
```

## Uso

1. Inicie primeiro o backend: `cd service && php start.php start -d` (porta padrão 8788)
2. Cadastre uma conta e faça login (usuário+senha, OAuth e 2FA são suportados)
3. Após depositar, jogue com moedas da plataforma e troque por moedas de jogo; as moedas de jogo podem ser convertidas de volta à carteira para saque
4. O backend admin está no diretório `admin/` (incluindo o frontend Flutter Web `admin/apps/flutter/`)
