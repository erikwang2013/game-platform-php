# Xiaoxiaole Taman — API Integrasi Platform
<!-- lang-nav -->

Languages: **中文** · [English](api.en.md) · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · [Español](api.es.md) · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> Dokumen ini adalah seluruh kontrak antarmuka antara *Xiaoxiaole Taman* dan platform game. Layering teknis lihat `architecture.id.md`, jadwal lihat `plan.id.md`, fitur pemain lihat `functional-design.id.md`.

---

## 1. Rantai Peluncuran

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / Pencapaian / VIP
        │  Buka api_endpoint?session_id=&token=
        ▼
game/xiaoxiaole/  (sumber daya statis, Nginx)
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

Game adalah **frontend statis**, sesi dan uang yang otoritatif ada di `service/`. Klien memegang status papan; server memegang saldo dan idempotensi round. Fase pertama tidak melakukan validasi server per-langkah, tetapi lapisan domain harus deterministik, agar pada fase kedua `seed + urutan operasi` dapat dikirim ke server untuk dihitung ulang.

---

## 2. Daftar Antarmuka

| Antarmuka | Metode | Arah | Keterangan |
|------|------|------|------|
| `/api/game/launch` | POST | platform → service | Memulai sesi game, mengembalikan `session_id, api_endpoint, type=self` |
| `/api/provider/balance` | GET | game → service | Kueri saldo koin game |
| `/api/provider/bet` | POST | game → service | Pembukaan level memotong biaya masuk |
| `/api/provider/settle` | POST | game → service | Settlement menyelesaikan level, beri hadiah |
| `/api/provider/refund` | POST | game → service | Keluar sebelum langkah pertama, uang kembali |

Panggilan `/api/provider/*` dari sisi game melalui `PlatformAdapter`, ditandatangani HMAC/JWT.

---

## 3. Alur Peluncuran

1. Platform `POST /api/game/launch` mengembalikan `session_id, api_endpoint, type=self`.
2. Buka `api_endpoint?session_id=&token=` (token adalah tiket game jangka pendek, atau pakai ulang JWT).
3. Game `GET /api/provider/balance` menampilkan koin game.
4. Pemain klik "Mulai level ini" → `POST /api/provider/bet`, `round_id = session_id + ':' + levelId + ':' + attempt`.
5. Domain `seed = hash(session_id + round_id)`.
6. Selesaikan level → `settle`, gagal tidak settle; keluar tanpa beroperasi → `refund`.

---

## 4. Pelaporan Play-log

`launch` (sudah ada) + game melaporkan event berikut (bisa masuk ClickHouse `GamePlayLogService` dulu):

| Event | Waktu |
|------|------|
| `level_start` | Memasuki level |
| `level_win` | Menyelesaikan level |
| `level_fail` | Gagal |
| `skill_use` | Menggunakan skill |

---

## 5. Saklar Fitur (FeatureFlag)

| Saklar | Default | Keterangan |
|------|------|------|
| `xxl.eco_chain` | on | Rantai kekangan ekosistem |
| `xxl.elephant` | off | Aturan gajah |
| `xxl.skills` | on | Skill alat pertanian |
| `xxl.entry_bet` | off | Biaya masuk/dompet |

Saat dimatikan, level terdegradasi menjadi three-match murni sesama jenis, memudahkan peluncuran bertahap.

---

## 6. Dompet & Idempotensi Round

- `SelfProvider::bet/settle/refund` sudah ada, game memanggil sesuai `round_id`; tetapkan batas atas hadiah per round.
- Satu round hanya bet/settle sekali; session yang timeout dibatalkan; skor tinggi abnormal hanya dicatat log tanpa hadiah otomatis (bisa set batas atas settle).
- Gagal tidak mengembalikan biaya masuk; keluar tanpa satu pun pertukaran → `refund`.

---

## 7. Fase Kedua: Hitung Ulang di Server

Unggah urutan operasi, server menjalankan porting PHP dari `domain` yang sama atau worker Node untuk menghitung ulang (`seed + urutan operasi` → validasi papan dan skor).
