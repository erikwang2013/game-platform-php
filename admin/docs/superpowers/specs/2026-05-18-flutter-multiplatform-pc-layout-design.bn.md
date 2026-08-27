# Flutter 多平台 PC 风格布局 — 设计规格
<!-- lang-nav -->

Languages: [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) · [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) · [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) · [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) · [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) · [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · **বাংলা** · [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


日期: 2026-05-18

## লক্ষ্য

macOS ও Windows ডেস্কটপ প্ল্যাটফর্ম সক্রিয় করা, এবং iOS (iPhone + iPad), macOS, Windows, Linux — সব প্ল্যাটফর্মে PC অ্যাডমিন প্যানেল শৈলীর লেআউট (সাইডবার + টপবার + কনটেন্ট এরিয়া) নিশ্চিত করা; মোবাইল ডিভাইসে ড্রয়ার মেনু দিয়ে অভিযোজন।

## প্ল্যাটফর্ম কৌশল

| প্ল্যাটফর্ম | অবস্থা | বিবরণ |
|------|------|------|
| Linux | ইতোমধ্যে সক্রিয় | কোনো কাজ নেই |
| macOS | সক্রিয় করতে হবে | `flutter config --enable-macos-desktop` |
| Windows | সক্রিয় করতে হবে | `flutter config --enable-windows-desktop` |
| iOS | ইতোমধ্যে আছে | iPhone (মোবাইল লেআউট) এবং iPad (ডেস্কটপ লেআউট) উভয়ই কভার করে |
| Web | ইতোমধ্যে আছে | কোনো কাজ নেই |

iPad-এর আলাদা প্ল্যাটফর্ম টার্গেট নেই; এটি রেসপনসিভ ব্রেকপয়েন্টের TABLET ধাপে পৌঁছে ডেস্কটপ লেআউট পায়।

## রেসপনসিভ ব্রেকপয়েন্ট

| ব্রেকপয়েন্ট | পরিসর | লেআউট মোড |
|------|------|----------|
| PHONE | 0 - 767 | ড্রয়ার মেনু (AppBar + Drawer) |
| TABLET | 768 - 1199 | ভাঁজযোগ্য সাইডবার (ডিফল্ট ভাঁজ করা 64px) |
| DESKTOP | 1200 - 2460 | সাইডবার (ডিফল্ট প্রসারিত 240px) |

iPad পোর্ট্রেট মোডে ন্যূনতম প্রস্থ 768px, TABLET ধাপে পৌঁছায় এবং সাইডবার লেআউট পায়।
iPhone-এর প্রস্থ 768px-এর কম, PHONE ধাপে পৌঁছায় এবং ড্রয়ার মেনু পায়।

## ফাইল পরিবর্তন

### 1. main.dart — ব্রেকপয়েন্ট কনফিগারেশন

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- বাকি কোড অপরিবর্তিত

### 2. admin_layout.dart — রেসপনসিভ নেভিগেশন স্যুইচ

- `_isPhone`: PHONE ব্রেকপয়েন্টে পৌঁছানো নির্ধারণ করে
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer; Drawer-এর ভেতরের NavigationDrawer ডেস্কটপ সাইডবারের সাথে একই মেনু আইটেম ব্যবহার করে
- `_buildDesktopLayout()`: বিদ্যমান Row লেআউট (সাইডবার + টপবার + কনটেন্ট এরিয়া)
- TABLET-এ সাইডবার ডিফল্ট ভাঁজ করা, DESKTOP-এ ডিফল্ট প্রসারিত

### 3. app_theme.dart — ডার্ক থিম পূরণ

- কম্পোনেন্ট স্টাইল প্রাইভেট কনস্ট্যান্ট `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme` হিসেবে নিষ্কাশন
- লাইট ও ডার্ক থিম একই কম্পোনেন্ট স্টাইল পুনঃব্যবহার করে
- ডার্ক থিম Material 3 + একই seed + dark ব্রাইটনেস ব্যবহার করে
