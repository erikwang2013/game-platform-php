# Flutter मल्टी-प्लेटफ़ॉर्म PC शैली लेआउट — डिज़ाइन विनिर्देश
<!-- lang-nav -->

Languages: [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) · [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) · [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) · [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) · [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) · **हिन्दी** · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) · [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


日期: 2026-05-18

## लक्ष्य

macOS और Windows डेस्कटॉप प्लेटफ़ॉर्म सक्षम करें, यह सुनिश्चित करते हुए कि iOS (iPhone + iPad), macOS, Windows, Linux सभी प्लेटफ़ॉर्म PC प्रशासन कंसोल शैली का लेआउट उपयोग करते हैं (साइडबार + टॉपबार + सामग्री क्षेत्र), और मोबाइल उपकरणों के लिए ड्रॉअर मेनू अनुकूलन।

## प्लेटफ़ॉर्म रणनीति

| प्लेटफ़ॉर्म | स्थिति | विवरण |
|------|------|------|
| Linux | पहले से सक्षम | कोई कार्रवाई आवश्यक नहीं |
| macOS | सक्षम करना आवश्यक | `flutter config --enable-macos-desktop` |
| Windows | सक्षम करना आवश्यक | `flutter config --enable-windows-desktop` |
| iOS | पहले से मौजूद | iPhone (मोबाइल लेआउट) और iPad (डेस्कटॉप लेआउट) दोनों को कवर करता है |
| Web | पहले से मौजूद | कोई कार्रवाई आवश्यक नहीं |

iPad का कोई स्वतंत्र प्लेटफ़ॉर्म लक्ष्य नहीं है; यह प्रतिक्रियाशील ब्रेकपॉइंट के माध्यम से TABLET स्तर पर हिट होकर डेस्कटॉप लेआउट प्राप्त करता है।

## प्रतिक्रियाशील ब्रेकपॉइंट

| ब्रेकपॉइंट | सीमा | लेआउट मोड |
|------|------|----------|
| PHONE | 0 - 767 | ड्रॉअर मेनू (AppBar + Drawer) |
| TABLET | 768 - 1199 | संक्षेपणीय साइडबार (डिफ़ॉल्ट रूप से 64px संक्षेपित) |
| DESKTOP | 1200 - 2460 | साइडबार (डिफ़ॉल्ट रूप से 240px विस्तारित) |

iPad पोर्ट्रेट की न्यूनतम चौड़ाई 768px है, जो TABLET पर हिट होकर साइडबार लेआउट प्राप्त करती है।
iPhone की चौड़ाई हमेशा 768px से कम होती है, जो PHONE पर हिट होकर ड्रॉअर मेनू प्राप्त करती है।

## फ़ाइल परिवर्तन

### 1. main.dart — ब्रेकपॉइंट कॉन्फ़िगरेशन

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- शेष कोड अपरिवर्तित

### 2. admin_layout.dart — प्रतिक्रियाशील नेविगेशन स्विच

- `_isPhone`: PHONE ब्रेकपॉइंट पर हिट
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, Drawer के भीतर NavigationDrawer डेस्कटॉप साइडबार के समान मेनू आइटम का पुनः उपयोग करता है
- `_buildDesktopLayout()`: मौजूदा Row लेआउट (साइडबार + टॉपबार + सामग्री क्षेत्र)
- TABLET के अंतर्गत साइडबार डिफ़ॉल्ट रूप से संक्षेपित, DESKTOP के अंतर्गत डिफ़ॉल्ट रूप से विस्तारित

### 3. app_theme.dart — डार्क थीम पूर्णता

- कंपोनेंट शैलियों को निजी स्थिरांक `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme` के रूप में निकालें
- लाइट और डार्क थीम एक ही कंपोनेंट शैलियों का पुनः उपयोग करते हैं
- डार्क थीम Material 3 + समान seed + dark चमक का उपयोग करती है
