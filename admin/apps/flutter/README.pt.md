# admin_app — Frontend web do painel administrativo (Flutter)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · **Português** · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

O frontend web do painel administrativo, baseado em Flutter 3.x, com o layout clássico de back-office para PC (barra lateral + barra superior + área de conteúdo). Cobre todas as páginas de gestão necessárias para operar a plataforma de jogos: painel, usuários, papéis e permissões, jogos, pagamentos, saques, VIP, conquistas, anúncios, CDN, controle de riscos, verificação de identidade, registros de operações, etc.

## Lista de funcionalidades

| Módulo | Descrição |
|------|------|
| Painel | Visão geral dos dados operacionais |
| Relatórios | Resumo de relatórios/diário/exportação CSV |

| Login | Login do administrador (com 2FA) |
| Gestão de usuários | Pesquisa e gestão de usuários |
| Usuários da plataforma | Detalhes, status e operações de saldo |
| Papéis e permissões | Atribuição de papéis e permissões |
| Configuração do sistema | Configuração de parâmetros da plataforma |
| Gestão de jogos | Lista, publicação/parada e categorias de jogos |
| Gestão de pagamentos | Depósitos, métodos de pagamento e logs de callback |
| Gestão de saques | Revisão e pagamento de saques |
| Gestão VIP | Configuração de níveis e benefícios VIP |
| Gestão de conquistas | Definições de conquistas e progresso |
| Gestão de anúncios | Publicação e retirada de anúncios |
| Gestão CDN | Configuração de provedores CDN e domínios |
| Controle de riscos | Regras de risco e registros de bloqueio |
| Verificação de identidade | Revisão de dados de nome real |
| Registro de operações | Auditoria das ações do administrador |
| Perfil | Perfil do administrador e configurações de segurança |

## Requisitos

- Flutter SDK 3.x

## Instalação e execução

```bash
cd admin/apps/flutter

# Instalar dependências
flutter pub get

# Executar em desenvolvimento (Chrome)
flutter run -d chrome

# Especificar o endereço do backend (padrão http://localhost:8787)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8787

# Build web de produção (saída em build/web/)
flutter build web
```

## Uso

1. Inicie primeiro o serviço backend do painel: `cd admin && php start.php start -d` (porta padrão 8787)
2. Faça login com a conta de administrador criada pelo assistente de instalação (2FA suportado)
3. O frontend do usuário está em `apps/flutter/platform/` e compartilha o mesmo serviço backend (porta padrão 8788)
