# game_platform — Platform Pengguna (Flutter Web)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · **Bahasa Indonesia** · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Frontend web platform pengguna (sisi C) berbasis Flutter 3.x, yang memberikan pengalaman lengkap platform agregasi game kepada pengguna: registrasi dan login, lobi game, dompet, deposit, penarikan, penukaran, papan peringkat, kupon, notifikasi, obrolan, teman, dan tiket dukungan.

## Daftar Fitur

| Modul | Deskripsi |
|------|------|
| Login/Registrasi | Nama pengguna+kata sandi / OAuth / 2FA |
| Lobi Game | Daftar/kategori/pencarian game |
| Dompet | Saldo dan transaksi koin platform/game |
| Deposit | Pilih metode pembayaran, alihkan ke pembayaran gateway |
| Penarikan | Ajukan penarikan, lacak status peninjauan |
| Penukaran | Penukaran real-time koin platform ⇄ koin game |
| Papan peringkat | Harian/mingguan/bulanan/seluruh waktu |
| Kupon | Klaim dan gunakan |
| Notifikasi | Pesan dalam aplikasi (deposit/penarikan/kupon, dll.) |
| Obrolan | Pesan WebSocket real-time |
| Teman | Sistem teman |
| Tiket | Buat dan balas tiket dukungan |
| Profil | Edit profil/pengaturan keamanan |

## Persyaratan

- Flutter SDK 3.x

## Instalasi dan Menjalankan

```bash
cd apps/flutter/platform

# Instal dependensi
flutter pub get

# Jalankan dalam pengembangan (Chrome)
flutter run -d chrome

# Tentukan alamat backend (default http://localhost:8788)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8788

# Build web produksi (output ke build/web/)
flutter build web
```

## Penggunaan

1. Mulai layanan backend terlebih dahulu: `cd service && php start.php start -d` (port default 8788)
2. Daftar akun lalu masuk (mendukung nama pengguna+kata sandi, OAuth, dan 2FA)
3. Setelah deposit, mainkan game dengan koin platform dan tukarkan dengan koin game; koin game dapat dikembalikan ke dompet untuk penarikan
4. Backend admin ada di direktori `admin/` (termasuk frontend Flutter Web `admin/apps/flutter/`)
