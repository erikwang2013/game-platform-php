# تخطيط Flutter متعدد المنصات بأسلوب PC — مواصفات التصميم
<!-- lang-nav -->

Languages: **中文** · [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) · [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) · [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) · [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) · [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) · [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


التاريخ: 2026-05-18

## الهدف

تفعيل منصتي سطح المكتب macOS وWindows، وضمان استخدام جميع المنصات — iOS (iPhone + iPad) وmacOS وWindows وLinux — لتخطيط بأسلوب لوحة إدارة PC (شريط جانبي + شريط علوي + منطقة محتوى)، مع تكييف قائمة السحب للهواتف.

## استراتيجية المنصات

| المنصة | الحالة | الوصف |
|------|------|------|
| Linux | مفعّلة | لا حاجة لإجراء |
| macOS | يجب تفعيل | `flutter config --enable-macos-desktop` |
| Windows | يجب تفعيل | `flutter config --enable-windows-desktop` |
| iOS | موجودة | تغطي iPhone (تخطيط الهاتف) وiPad (تخطيط سطح المكتب) |
| Web | موجودة | لا حاجة لإجراء |

لا يوجد هدف منصة مستقل لـ iPad؛ يتم تحقيق تخطيط سطح المكتب عبر نقاط التوقف التفاعلية عند وصول عرض الشاشة لدرجة TABLET.

## نقاط التوقف التفاعلية

| نقطة التوقف | النطاق | وضع التخطيط |
|------|------|----------|
| PHONE | 0 - 767 | قائمة السحب (AppBar + Drawer) |
| TABLET | 768 - 1199 | شريط جانبي قابل للطي (مطوي افتراضيًا 64px) |
| DESKTOP | 1200 - 2460 | شريط جانبي (موسّع افتراضيًا 240px) |

الحد الأدنى لعرض iPad في الوضع الرأسي هو 768px، فيصيب TABLET ويحصل على تخطيط الشريط الجانبي.
عرض iPhone دائمًا أقل من 768px، فيصيب PHONE ويحصل على قائمة السحب.

## تغييرات الملفات

### 1. main.dart — إعداد نقاط التوقف

- `PHONE`: 0-767، `TABLET`: 768-1199، `DESKTOP`: 1200-2460
- باقي الكود دون تغيير

### 2. admin_layout.dart — تبديل التخطيط التفاعلي

- `_isPhone`: يتحقق من نقطة توقف PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer، حيث يعيد NavigationDrawer داخل Drawer استخدام نفس عناصر القائمة كالشريط الجانبي لسطح المكتب
- `_buildDesktopLayout()`: تخطيط Row الحالي (شريط جانبي + شريط علوي + منطقة محتوى)
- في TABLET يُطوى الشريط الجانبي افتراضيًا، وفي DESKTOP يُوسّع افتراضيًا

### 3. app_theme.dart — استكمال السمة الداكنة

- استخراج أنماط المكونات كثوابت خاصة `_dataTableTheme` و`_cardTheme` و`_inputDecorationTheme` و`_dividerTheme`
- السمتان الفاتحة والداكنة تشتركان في نفس أنماط المكونات
- السمة الداكنة تستخدم Material 3 + نفس seed + سطوع dark
