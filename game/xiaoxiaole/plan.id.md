# Xiaoxiaole Taman — Rencana Pengembangan
<!-- lang-nav -->

Languages: **中文** · [English](plan.en.md) · [한국어](plan.ko.md) · [Русский](plan.ru.md) · [Deutsch](plan.de.md) · [Français](plan.fr.md) · [Español](plan.es.md) · [Português](plan.pt.md) · [हिन्दी](plan.hi.md) · [العربية](plan.ar.md) · [বাংলা](plan.bn.md) · [Bahasa Indonesia](plan.id.md) · [日本語](plan.ja.md)


> Mengubah visi (`design.id.md`) menjadi hal yang bisa dijadwalkan. Detail fitur mengacu `functional-design.id.md`, batasan teknis mengacu `architecture.id.md`.

---

## 1. Cara Memakai Tiga Dokumen

| Dokumen | Menjawab pertanyaan | Tidak menjawab |
|------|------------|--------|
| `design.id.md` | tema taman, fantasi kekangan, karakter 3D | per level refresh berapa spesies, klausul penerimaan |
| `functional-design.id.md` | pemain klik apa, bagaimana dihitung menang, siapa muncul V1 | bagaimana struktur direktori, pakai mesin fisika atau tidak |
| `architecture.id.md` | layering, modul, dompet platform, RNG deterministik | 90 detik atau 20 langkah (sudah diputuskan di fungsional) |

Pengembangan hanya mengakui dua dokumen terakhir; saat visi bertentangan dengan dua dokumen terakhir, pakai dua dokumen terakhir (pengecualian yang sudah diputuskan ditulis di bagian 12 desain fungsional).

---

## 2. Ruang Lingkup V1

**Dianggap rilis jika selesai:** empat level bisa menang, tiga jenis eliminasi, skill & rintangan Penghancur, H5 bisa dibuka dari lobby. Dompet bisa dimatikan (saklar fitur `xxl.entry_bet`).

**Jelas dipangkas atau ditunda:** 100 spesies sekaligus di papan, alat pertanian sebagai pion, mesin fisika, GLTF, tontonan, peringkat dalam ronde, level jalur utama genangan, pengekang selesai makan tinggal di papan, validasi server per langkah.

---

## 3. Tonggak

| Tonggak | Tanggal target (relatif mulai) | Hasil bisa dimainkan | Yang keluar |
|--------|----------------------|----------|----------|
| M0 Kerangka | minggu 1 | lokal buka sandbox kosong | Vite, scene orthogonal Three, petak 8×8 |
| M1 Bisa hilang | minggu 2 | tiga sama akan hilang dan jatuh | F01–F03, unit test domain |
| M2 Ada level | minggu 3 | Panen Raya bisa menang bisa kalah | F04 F05 F15 F16 |
| M3 Ekosistem | minggu 4 | ayam makan serangga, level Mengusir | F06 F07 F08 |
| M4 Alat | minggu 5 | Penghancur bongkar pohon | F09 F10 F11 |
| M5 Integrasi | minggu 6 | bisa masuk dari lobby, level gajah, potong biaya opsional | F12 F13 F14 |
| M6 Poles | minggu 7 | partikel/efek suara/profil perangkat rendah | F17 |

Satu minggu dihitung satu orang full-time. Paralel (domain + render) bisa ditekan sekitar 5 minggu.

---

## 4. Fase & Dependensi

```
P0 three-match sesama jenis ────────┐
P1 pilih level & Panen Raya ────────┼─ P2 ekosistem & Mengusir ─ P3 rintangan alat ─ P4 gajah+dompet ─ P5 poles
render sandbox (bisa paralel dengan P0) ┘
```

- P0 tidak bergantung PHP. `?debug=1` main lokal.
- P1 tidak bergantung dompet.
- P2 bergantung perluasan pemindaian match P0, tidak mengubah cara operasi.
- P3 bergantung overlay petak.
- P4 bergantung `POST /api/game/launch` dan `SelfProvider` yang sudah ada di platform; sisi game tambah ticket, bet, settle.
- P5 tanpa dependensi fungsi, saklar perangkat rendah bisa disisipkan kapan saja.

---

## 5. Paket Kerja (per orang)

**A Domain (tanpa antarmuka)**
JSON katalog → snapshot papan → match (sesama jenis/ekosistem/gajah) → gravitasi → menang kalah level → skor. Vitest mendahului tampilan.

**B Tampilan**
Scene, kamera, dari 10 template kerjakan 3 dulu (bulir buah/ayam), Raycaster, easing tukar & eliminasi. HUD pakai DOM.

**C Konten level**
Empat level JSON: pool refresh, target, langkah/batas waktu, whitelist skill, rintangan pembukaan.

**D Platform**
Parameter URL launch, tampilan saldo, bet/settle, strategi refund gagal, event play-log.

Urutan disarankan: test P0 A merah-hijau → B sambung snapshot → C Panen Raya → test ekosistem A → C tiga level lain → D.

---

## 6. Yang Harus Diubah Sisi Platform (P4 baru dikerjakan)

Kontrak antarmuka lihat **[api.id.md](api.id.md)**. Titik perubahan sisi platform:

| Item | Status saat ini | Tindakan rencana |
|----|------|----------|
| Catatan game | `GameController::launch` sudah menulis session | backend tambah satu baris type=self, api_endpoint menunjuk H5 ini |
| Dompet | `SelfProvider::bet/settle` sudah ada | game memanggil sesuai round_id; tetapkan batas atas hadiah per round |
| Saklar fitur | `FeatureFlag` sudah ada | `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet` |
| Hosting statis | Nginx sudah distribusi | `/games/xiaoxiaole/` menunjuk hasil build |
| Buka dari lobby | Flutter `launchUrl` | endpoint dirangkai `session_id` |

P0–P3 **tidak perlu mengubah PHP**.

---

## 7. Risiko

| Risiko | Dampak | Penanganan |
|------|------|------|
| Aturan ekosistem pemain tidak paham | Mengusir tidak bisa lewat | petunjuk tutorial ketiga; preview bisa-hilang diletakkan ke P5 |
| Jenis refresh masih terlalu banyak | tidak ada pion bisa dihilangkan | satu level hard cap 8 jenis |
| Gajah terlalu kuat | Pesta langsung kosong | target hanya dihitung aturan gajah; papan terkunci 1 |
| Klien mengubah skor tipu hadiah | dompet | P4 batas hadiah; verifikasi rekaman ditunda |
| Perangkat rendah drop frame | pengalaman | dpr batas 2; partikel bisa dimatikan |

---

## 8. Sudah Diputuskan (tidak ditanya lagi)

- Setelah ekosistem pengekang **ikut pergi bersama**.
- Level Mengusir **batas waktu 90 detik**, tidak pakai langkah.
- Genangan tidak masuk empat level jalur utama.
- V1 hanya me-refresh tabel bagian 7 desain fungsional, spesies lain hanya masuk file katalog.

Jika ingin mengubah empat aturan ini, ubah dulu `functional-design.id.md` baru gerakkan kode.

---

## 9. Langkah Berikutnya (tunggu aba-aba)

1. Tulis daftar tugas implementasi sesuai P0 (level file, test dahulu), atau
2. Langsung bangun kerangka Vite + `domain` + scene kosong.

Tidak menulis implementasi fungsi spesifik dalam rencana ini.
