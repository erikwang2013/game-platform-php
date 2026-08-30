# game_platform — 사용자 플랫폼 (Flutter Web)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · **한국어** · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

C측 사용자 플랫폼의 Web 프런트엔드. Flutter 3.x 기반으로 사용자에게 회원가입/로그인, 게임 로비, 지갑, 충전, 출금, 환전, 랭킹, 쿠폰, 알림, 채팅, 친구, 고객센터 티켓 등 게임 통합 플랫폼의 완전한 경험을 제공합니다.

## 기능 목록

| 모듈 | 설명 |
|------|------|
| 로그인/회원가입 | 아이디·비밀번호 / OAuth / 2FA |
| 게임 로비 | 게임 목록/카테고리/검색 |
| 지갑 | 플랫폼 코인/게임 코인 잔액과 거래 내역 |
| 충전 | 결제 수단 선택 후 게이트웨이 결제로 이동 |
| 출금 | 출금 신청, 심사 상태 추적 |
| 환전 | 플랫폼 코인 ⇄ 게임 코인 실시간 환전 |
| 랭킹 | 일간/주간/월간/전체 |
| 쿠폰 | 받기와 사용 |
| 알림 | 앱 내 메시지(충전/출금/쿠폰 등) |
| 채팅 | WebSocket 실시간 메시지 |
| 친구 | 친구 시스템 |
| 티켓 | 고객센터 티켓 생성과 답변 |
| 프로필 | 프로필 편집/보안 설정 |

## 환경 요구 사항

- Flutter SDK 3.x

## 설치 및 실행

```bash
cd apps/flutter/platform

# 의존성 설치
flutter pub get

# 개발 실행(Chrome)
flutter run -d chrome

# 백엔드 주소 지정(기본값 http://localhost:8788)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8788

# Web 프로덕션 빌드(build/web/로 출력)
flutter build web
```

## 사용 방법

1. 먼저 백엔드를 시작: `cd service && php start.php start -d`(기본 포트 8788)
2. 계정을 등록하고 로그인(아이디·비밀번호, OAuth, 2FA 지원)
3. 충전 후 플랫폼 코인으로 게임을 즐기고 게임 코인으로 환전할 수 있습니다. 게임 코인은 지갑으로 되돌려 출금도 가능합니다
4. 관리 백엔드는 `admin/` 디렉터리(Flutter Web 프런트엔드 `admin/apps/flutter/` 포함)
