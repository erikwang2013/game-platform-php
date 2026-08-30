# admin_app — Frontend Web Panel Admin (Flutter)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · **Bahasa Indonesia** · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Frontend web panel admin berbasis Flutter 3.x, dengan tata letak admin PC klasik (sidebar + topbar + area konten). Mencakup semua halaman manajemen yang diperlukan untuk mengoperasikan platform game: dasbor, pengguna, peran dan izin, game, pembayaran, penarikan, VIP, pencapaian, pengumuman, CDN, kontrol risiko, verifikasi identitas, log operasi, dan lainnya.

## Daftar Fitur

| Modul | Deskripsi |
|------|------|
| Dasbor | Ringkasan data operasional platform |
| Laporan | Ringkasan laporan/harian/ekspor CSV |

| Login | Login admin (termasuk 2FA) |
| Manajemen pengguna | Pencarian dan pengelolaan pengguna |
| Pengguna platform | Detail pengguna, status, dan operasi saldo |
| Peran dan izin | Penetapan peran dan izin |
| Konfigurasi sistem | Konfigurasi parameter platform |
| Manajemen game | Daftar game, publikasi/penonaktifan, dan kategori |
| Manajemen pembayaran | Pesanan deposit, metode pembayaran, dan log callback |
| Manajemen penarikan | Tinjauan dan pembayaran penarikan |
| Manajemen VIP | Konfigurasi level dan keuntungan VIP |
| Manajemen pencapaian | Definisi pencapaian dan melihat progres |
| Manajemen pengumuman | Publikasi dan penghentian pengumuman |
| Manajemen CDN | Konfigurasi vendor CDN dan domain |
| Kontrol risiko | Aturan risiko dan catatan pemblokiran |
| Verifikasi identitas | Tinjauan data nama asli |
| Log operasi | Log audit tindakan admin |
| Profil | Profil admin dan pengaturan keamanan |

## Persyaratan

- Flutter SDK 3.x

## Instalasi dan Menjalankan

```bash
cd admin/apps/flutter

# Instal dependensi
flutter pub get

# Jalankan dalam pengembangan (Chrome)
flutter run -d chrome

# Tentukan alamat backend (default http://localhost:8787)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8787

# Build web produksi (output ke build/web/)
flutter build web
```

## Penggunaan

1. Mulai layanan backend admin terlebih dahulu: `cd admin && php start.php start -d` (port default 8787)
2. Masuk dengan akun admin yang dibuat oleh wizard instalasi (mendukung 2FA)
3. Frontend pengguna ada di `apps/flutter/platform/` dan menggunakan layanan backend yang sama (port default 8788)
