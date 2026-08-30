# admin_app — 관리자 웹 프론트엔드 (Flutter)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · **한국어** · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Flutter 3.x 기반 관리자 웹 프론트엔드로, 전형적인 PC 관리자 레이아웃(사이드바 + 상단바 + 콘텐츠 영역)을 사용합니다. 게임 플랫폼 운영에 필요한 모든 관리 페이지를 포함합니다: 대시보드, 사용자, 역할 권한, 게임, 결제, 출금, VIP, 업적, 공지, CDN, 리스크 관리, 실명 인증, 운영 로그 등.

## 기능 목록

| 모듈 | 설명 |
|------|------|
| 대시보드 | 플랫폼 운영 데이터 개요 |
| 리포트 | 데이터 리포트 요약/일일/CSV 내보내기 |

| 로그인 | 관리자 로그인(2FA 지원) |
| 사용자 관리 | 플랫폼 사용자 검색 및 관리 |
| 플랫폼 사용자 | 사용자 상세, 상태 및 잔액 조작 |
| 역할 권한 | 역할 및 권한 할당 |
| 시스템 설정 | 플랫폼 파라미터 설정 |
| 게임 관리 | 게임 목록, 게시/중지 및 카테고리 |
| 결제 관리 | 입금 주문, 결제 수단 및 콜백 로그 |
| 출금 관리 | 출금 심사 및 지급 |
| VIP 관리 | VIP 레벨 및 혜택 설정 |
| 업적 관리 | 업적 정의 및 진행 상황 조회 |
| 공지 관리 | 공지 게시 및 게시 중지 |
| CDN 관리 | CDN 벤더 설정 및 도메인 관리 |
| 리스크 관리 | 리스크 규칙 및 차단 기록 |
| 실명 인증 | 실명 정보 심사 |
| 운영 로그 | 관리자 작업 감사 로그 |
| 개인 센터 | 관리자 프로필 및 보안 설정 |

## 요구 사항

- Flutter SDK 3.x

## 설치 및 실행

```bash
cd admin/apps/flutter

# 의존성 설치
flutter pub get

# 개발 모드로 실행 (Chrome)
flutter run -d chrome

# 백엔드 주소 지정 (기본값 http://localhost:8787)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8787

# 웹 프로덕션 빌드 (출력: build/web/)
flutter build web
```

## 사용 방법

1. 먼저 관리자 백엔드 서비스를 시작합니다: `cd admin && php start.php start -d` (기본 포트 8787)
2. 설치 마법사에서 생성한 관리자 계정으로 로그인합니다 (2FA 지원)
3. 사용자용 프론트엔드는 `apps/flutter/platform/`에 있으며 동일한 백엔드 서비스(기본 포트 8788)를 사용합니다
