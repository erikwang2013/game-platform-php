# Xiaoxiaole Taman — Arsitektur Teknis
<!-- lang-nav -->

Languages: **中文** · [English](architecture.en.md) · [한국어](architecture.ko.md) · [Русский](architecture.ru.md) · [Deutsch](architecture.de.md) · [Français](architecture.fr.md) · [Español](architecture.es.md) · [Português](architecture.pt.md) · [हिन्दी](architecture.hi.md) · [العربية](architecture.ar.md) · [বাংলা](architecture.bn.md) · [Bahasa Indonesia](architecture.id.md) · [日本語](architecture.ja.md)


> Fitur pemain dan penerimaan lihat `functional-design.id.md`; jadwal lihat `plan.id.md`; visi tema lihat `design.id.md`.
>
> Dokumen ini hanya menjawab bagaimana memecah modul, bagaimana terhubung ke platform, dan di lapisan mana aturan dihitung. Tidak menulis kode implementasi.
>
> Posisi produk: H5 buatan sendiri (`game.type = self`), three-match 8×8 + rantai kekangan ekosistem, low-poly 2.5D Three.js.

---

## 0. Keputusan Arsitektur Relatif terhadap Rencana

Rencana adalah visi gameplay; keputusan berikut menyelesaikan kontradiksi "bisa dimainkan, bisa diuji, bisa terhubung ke dompet".

| ID | Keputusan | Alasan |
|----|------|------|
| D1 | **Katalog ≠ pion di papan yang sama**. 100+ spesies adalah katalog dan penampilan; pool refresh per level hanya mengambil **5–8 spesies** | Jika lusinan spesies muncul bersamaan di 8×8, hampir mustahil membentuk eliminasi |
| D2 | Pencocokan dua lapis: **sesama jenis berdasar `speciesId`**, **ekosistem berdasar `role` + tabel kekangan** | Rencana sekaligus menuntut "tiga apel" dan "ayam + serangga + serangga" |
| D3 | Prioritas aturan pada segmen yang sama: **gajah > ekosistem > sesama jenis**; saling eksklusif, tidak menghitung skor dua kali | Mencegah satu baris dimakan skor dua kali sekaligus |
| D4 | **Alat pertanian tidak masuk papan**, hanya di slot skill HUD; batu/genangan air/pohon adalah rintangan, tidak bisa ditukar | Bagian kelima rencana bertentangan dengan perpustakaan pion, gunakan skill+rintangan sebagai patokan |
| D5 | **Logika domain nol dependensi Three.js**, fungsi murni + snapshot; lapisan tampilan hanya berlangganan event | Aturan dapat di-unit-test, diputar ulang, dan nanti divalidasi server |
| D6 | Pembukaan menurunkan **seed RNG deterministik** dari `session_id`; drop/refresh semuanya lewat RNG tersebut | Seed yang sama bisa diputar ulang; menyisakan celah anti-cheat |
| D7 | Tanpa mesin fisika. Perpindahan/lompatan/eliminasi pakai easing, tidak memperkenalkan Cannon/Rapier | Rencana sudah menulis "animasi simulasi"; fisika tidak memberi manfaat untuk game grid |
| D8 | Kamera **orthographic 2.5D posisi tetap**, matikan kontrol orbit | Konsisten dengan rencana, hindari salah operasi dan pusing |
| D9 | Spesies berbagi **template geometri kubu + warna/aksesori**, tidak membuat model terpisah per tanaman | Trafik dan tenggat; perbedaan visual mengandalkan warna dan satu bagian penanda |
| D10 | Masuk level lewat `SelfProvider::bet`, selesaikan level `settle`, gagal di tengah tidak mengembalikan biaya masuk; belum langkah pertama bisa `refund` | Menyelaraskan dompet platform dan idempotensi round |

---

## 1. Konteks Sistem

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
  Vite + TypeScript + Three.js
  Mesin domain ──event──► Render / HUD
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

Game adalah **frontend statis**, sesi dan uang yang otoritatif ada di `service/`. Klien memegang status papan; server memegang saldo dan idempotensi round. Fase pertama tidak melakukan validasi server per-langkah, tetapi lapisan domain harus deterministik, agar pada fase kedua `seed + urutan operasi` dapat dikirim ke server untuk dihitung ulang.

---

## 2. Layering Klien

Dari atas ke bawah, dilarang dependensi terbalik antar lapisan (`render` tidak boleh di-import oleh `domain`).

```
app/          Perakitan, state machine, siklus hidup level
hud/          HTML Overlay: skor, langkah, target, skill, hasil
platform/     Parameter launch, dompet, play-log, saklar fitur
render/       Three.js: scene, papan, grid pion, input, VFX
runtime/      Command bus, antrean animasi, replay
domain/       Papan, pencocokan, kekangan, gravitasi, skor, katalog, aturan level
config/       Tabel kekangan, bobot refresh, resep geometri, JSON level
```

**Loop utama (tidak menghitung aturan di `requestAnimationFrame`)**: input → command → settlement sinkron domain (sekali swap menghitung semua kombo, menghasilkan daftar event) → runtime mengantre animasi sesuai event → baru menerima input berikutnya setelah animasi selesai.

Dengan begitu "logika satu frame, tampilan banyak frame", combo tidak berebut status dengan klik.

---

## 3. Struktur Direktori (disarankan)

```
game/xiaoxiaole/
├── design.md
├── architecture.md          ← dokumen ini
├── package.json             # vite, three, gsap, vitest, typescript
├── index.html
├── src/
│   ├── main.ts              # baca URL, mulai GameApp
│   ├── app/
│   │   ├── GameApp.ts
│   │   └── GameStateMachine.ts
│   ├── domain/
│   │   ├── catalog/         # PieceDef, Faction, Role
│   │   ├── board/           # Grid 8×8, Cell, PieceInstance
│   │   ├── match/           # MatchDetector (sesama jenis / ekosistem / gajah)
│   │   ├── eco/             # RestraintTable
│   │   ├── gravity/         # jatuh, blokir genangan air, refresh
│   │   ├── score/           # skor, pengali pupuk
│   │   ├── level/           # LevelDef, Objective, Win/Lose
│   │   └── rng/             # seeded mulberry32
│   ├── runtime/
│   │   ├── CommandBus.ts    # Select, Swap, UseSkill, Quit
│   │   ├── EventLog.ts      # event serializable, untuk replay
│   │   └── AnimationQueue.ts
│   ├── render/
│   │   ├── SceneRoot.ts
│   │   ├── CameraRig.ts
│   │   ├── BoardView.ts
│   │   ├── PieceFactory.ts  # geometri template
│   │   ├── InputRaycaster.ts
│   │   └── vfx/
│   ├── hud/
│   ├── platform/
│   │   ├── ApiClient.ts
│   │   └── Session.ts
│   └── levels/*.json
└── tests/domain/            # tanpa WebGL
```

Satu file tidak lebih dari 500 baris. Jika `MatchDetector` dan `PieceFactory` membengkak, pecah lagi berdasarkan jenis aturan / template kubu.

---

## 4. Model Domain

### 4.1 Definisi Pion (Katalog)

```
PieceDef
  id            speciesId        mis. wheat, hen, elephant
  faction       Faction          crop | veg | fruit | flora | insect | poultry | livestock | tree | tool | obstacle | apex
  role          Role             plant | prey | predator_mid | predator_high | apex | obstacle | skill
  tags[]                         crop, edible_by_poultry, tree_seedling, bone …
  rarity        common | rare | legendary
  template      GeometryTemplate nama template geometri
  tint          RGB              warna dalam template
  accessory     optional         bagian pembeda: paruh, kelopak, belalai dll.
```

Tanaman/sayur/buah/bunga/serangga/unggas/hewan ternak/pohon dalam rencana semuanya masuk katalog; **tool tidak di-generate ke petak**. Gajah `rarity = legendary`, `role = apex`.

### 4.2 Petak & Papan

```
Cell
  q, r               kolom, baris (0–7)
  height             kontur medan (hanya render, tidak ikut aturan)
  occupant           PieceInstance | null
  overlay            none | fertilizer | puddle | stone(hp) | tree(hp)
  locked             bool          level pesta gajah: gajah tidak bisa ditukar keluar

PieceInstance
  uid, speciesId, def
  special            none | fertilizer_token
```

- **Batu / pohon**: menempati petak, tidak bisa ditukar, tidak bisa dijatuhi/lewat. HP sesuai level.
- **Genangan air**: menumpuk di petak, memblokir gravitasi melewati petak tersebut (pion di atas berhenti satu petak di atas genangan).
- **Pupuk**: setelah eliminasi ekosistem tetap di petak itu; eliminasi berikutnya yang melibatkan petak itu skor ×2, lalu hilang.

### 4.3 Pool Refresh (Level)

```
SpawnPool
  speciesIds[]       5–8 buah
  weights[]          sejajar dengan species
  maxApex            default 1
  apexUnlock         combo >= 5 di-generate oleh sistem, dilarang "terbentuk dari swap"
```

Level hanya mengambil pion dari pool ini lewat `rng`. Sekatalog apa pun besarnya, entropi papan tetap terkendali.

---

## 5. Mesin Aturan Inti (Desain Fungsional)

### 5.1 Operasi

1. Klik pion A → pilih (lompat + outline).
2. Klik lagi petak orthogonal B → coba tukar (dilarang tukar diagonal).
3. Klik yang tidak berdekatan / kosong → ganti pilihan atau batal.
4. Setelah tukar jika **tidak ada match valid apa pun**, putar kembali, tidak menghabiskan langkah.
5. Ada match maka habiskan 1 langkah, masuk settlement.

Petak rintangan tidak bisa menjadi target tukar. Petak terkunci (aturan level) sama.

### 5.2 Pemindaian Segmen

Untuk setiap papan setelah swap:

- Petak berurutan horizontal, vertikal, panjang ≥ 3 adalah satu **run**.
- Satu run hanya memakai satu aturan (D3).
- Beberapa run boleh berpotongan (bentuk L/T klasik), petak perpotongan hanya dihilangkan sekali.

### 5.3 Eliminasi Sesama Jenis

`speciesId` dalam run semuanya sama, dan bukan rintangan, bukan hak istimewa gajah (diproses terpisah).

- 3 buah: skor dasar.
- 4 buah: skor tambahan, dan di petak tengah jatuh **pupuk** (overlay sama dengan pupuk ekosistem).
- 5 buah: skor tambahan, slot skill +1 charge (lihat 5.7).

### 5.4 Eliminasi Ekosistem (Rantai Kekangan)

Penentuan: **tepat 1 pengekang + sisanya semua mangsa pengekang itu** (3 petak berarti 1+2). Tidak mensyaratkan mangsa sesama jenis.

| Pengekang | Kecocokan mangsa |
|--------|----------|
| Ayam, bebek, angsa | faction ∈ {flora, veg, fruit, insect}; **tidak termasuk crop (biji-bijian)** |
| Anjing | faction = poultry (ayam/bebek/angsa/merpati dll.) |
| Babi | faction ∈ {tree, flora, veg, fruit, insect, crop}; **tidak termasuk anjing** |
| Sapi, kuda | faction ∈ {flora, crop} atau tag `tree_seedling`; tidak termasuk serangga dan daging |
| Gajah | lihat 5.5, tidak lewat tabel ini |

Efek:

- Eliminasi seluruh segmen, mainkan animasi memangsa (pengekang dulu "makan", lalu pergi bersama mangsa, atau pengekang tinggal di papan—**fase pertama seragam seluruh segmen pergi**, menghindari pengekang tersisa merusak keseimbangan drop; jika pengalaman terasa lemah, fase kedua ganti ke saklar "pengekang tinggal").
- Skor ekosistem dasar lebih tinggi dari sesama jenis.
- Petak asli pengekang menghasilkan **pupuk**.
- `ecoChainStreak += 1`; dalam satu chain yang sama beberapa kali ekosistem hanya menambah satu simpul hitung streak (di akhir seluruh resolve +1, menghindari sekali drop memenuhi skill).

**Ayam tidak makan biji-bijian**: tanaman dan ayam boleh satu papan, tetapi tidak bisa membentuk run ekosistem; hanya bisa mengeliminasi tanaman lewat sesama jenis.

### 5.5 Gajah

- Papan global paling banyak 1; bobot refresh sangat rendah; hanya hadiah dari `combo >= 5`, atau dimasukkan `initialPieces` level.
- Run berisi 1 gajah + 2 pion non-alat, non-rintangan apa pun → kosongkan 3 petak ini (boleh berbeda kubu).
- Gajah **tidak bisa** mengeliminasi alat pertanian (alat tidak di papan, otomatis terpenuhi) dan rintangan (rintangan tidak masuk run).
- Level "pesta gajah": buka 1 gajah, `locked = true`, tidak bisa ditukar keluar dari petak asli; mangsa ditukar ke dekatnya membentuk run.

### 5.6 Kombo, Gravitasi, Refresh

```
resolve:
  detect runs
  jika tidak ada → idle
  terapkan skor, overlay, hp pada rintangan berdekatan
  emit Clear
  gravity: setiap kolom padatkan dari bawah ke atas, lewati rintangan padat stone/tree; puddle memblokir lewat
  refill: dari atas kolom isi sesuai SpawnPool (dibatasi maxApex)
  combo++
  goto detect
```

Eliminasi berdekatan mengurangi HP rintangan: batu setiap eliminasi sesama jenis/ekosistem di dekatnya -1, HP=0 pecah; pohon default hanya **cangkul** atau level "tiga babi menggempur area" atau ekosistem babi (mangsa termasuk pohon) mengurangi HP. Level penghancur pohon HP=5.

Skill ember: pilih satu petak genangan → hapus overlay, kolom itu segera melakukan satu kali gravity.

### 5.7 Slot Skill (Alat Pertanian)

| Skill | Buka | Efek |
|------|------|------|
| Sabit | 3 resolve berturut-turut mengandung ekosistem | Klik satu baris atau kolom, bersihkan semua pion berperan **plant** di garis itu (crop/veg/fruit/flora), tidak habiskan langkah, habiskan charge |
| Cangkul | sama, atau pre-set level | Klik batu/pohon, langsung HP=0 atau -3 (konfigurasi level) |
| Ember | pre-set level atau charge | Keringkan satu petak genangan |

Aturan charge: `ecoResolveCount` mencapai 3 → slot +1, hitungan di-nol-kan. Batas slot 2. Sabit/cangkul/ember ditentukan muncul yang mana oleh `allowedSkills[]` level.

### 5.8 Skor

```
same_3     = 10 * n * combo * fertilizerMul
eco        = 25 * n * combo * fertilizerMul
elephant   = 40 * n * combo
skill_clear= 8  * n
obstacle   = 20 * brokenCount
```

`combo` mulai dari 1, setiap chain +1, swap manual pemain berikutnya reset. Pupuk hanya berlaku pada "saat petak itu dieliminasi" itu.

---

## 6. Fungsi Level

| Level | Pool | Menang | Kalah | Keunikan |
|------|----|------|------|------|
| Panen Raya | crop/veg/fruit + poultry bobot tinggi | 20 langkah mengeliminasi 50 plant | langkah habis | Ayam/bebek/angsa mengganggu eliminasi sesama jenis tanaman |
| Mengusir | poultry + dog, tanpa tanaman | waktu terbatas gunakan ekosistem anjing mengeliminasi 15 ayam/bebek | timeout | Eliminasi sesama jenis unggas tidak dihitung target, wajib ekosistem |
| Penghancur | tanaman + sedikit pig + 3 pohon (HP5) | babi menggempur 3 pohon | langkah habis | Tiga babi garis lurus memicu **serangan 3×3** (aturan level, bukan global) |
| Pesta Gajah | pool campuran + gajah terkunci buka | aturan gajah mengeliminasi 30 pion | gajah terpindah abnormal (tidak seharusnya terjadi) atau langkah habis | Lindungi gajah; sistem tidak me-refresh gajah kedua |

HUD umum: progres target, langkah atau hitung mundur, combo, slot skill, jeda/keluar.

Menang/kalah ditentukan setelah satu resolve (termasuk semua animasi kombo) selesai, menghindari salah menilai di tengah animasi.

---

## 7. Lapisan Tampilan Three.js

| Modul | Tanggung jawab |
|------|------|
| SceneRoot | WebGLRenderer, tone mapping, resize, dpr batas 2 |
| CameraRig | OrthographicCamera, pitch sekitar 45°, lookAt pusat papan, larang OrbitControls |
| Lights | Directional (matahari) + Hemisphere (ambient) + Rim lemah; tanpa shadow real-time atau hanya papan menerima shadow resolusi rendah |
| BoardView | Petak 8×8; kontur Y pakai perlin prebaked height map (petak logika tetap datar) |
| PieceFactory | Rakit Group sesuai `template`: bola/silinder/kerucut/kubus; MeshPhongMaterial; object pool |
| InputRaycaster | Hanya hit mesh pion di `Idle/Selected` |
| VFX | Outline pilihan (cincin cahaya gambar sendiri, fase pertama tidak pakai OutlinePass fullscreen); tukar GSAP; skala eliminasi + partikel Points; serbuk sari/kunang-kunang pakai sedikit Points loop |
| HUD | DOM, tidak masuk WebGL, memudahkan i18n dan aksesibilitas |

Template geometri (D9): `grain` `produce` `fruit` `flower` `bug` `bird` `beast` `tree` `apex` `rock`. Katalog hanya mengubah tint dan accessory.

Anggaran performa: 64 pion + petak < 200 draw call (sebisa mungkin merge petak); partikel < 400; perangkat kelas bawah matikan partikel dan kontur.

---

## 8. State Machine

```
Boot → Title → Playing
Substate Playing:
  Idle → Selected → SwapAnim → ResolveLogic → ClearAnim → GravityAnim → RefillAnim
       ↺ jika masih ada match kembali ke ResolveLogic (combo)
       → Idle
  Playing → SkillTargeting → SkillAnim → ResolveLogic
  Playing → Won | Lost → Result → Title | next
  Playing → Paused → Playing
```

Input ilegal dibuang saat bukan Idle/Selected/SkillTargeting.

**Command**: `Select` `Swap` `UseSkill` `Pause` `Quit` `AckResult`
**Event** (ditulis ke EventLog): `Swapped` `RejectedSwap` `Matches` `Cleared` `Fell` `Refilled` `Combo` `SkillUsed` `Won` `Lost`

---

## 9. Integrasi Platform

Kontrak antarmuka lengkap (launch / balance / bet / settle / refund / play-log / saklar fitur) lihat **[api.id.md](api.id.md)**. Poin utama:

- Peluncuran: `POST /api/game/launch` mengembalikan `session_id, api_endpoint, type=self`, buka `api_endpoint?session_id=&token=`.
- Dompet: `SelfProvider::bet/settle/refund`, `round_id = session_id + ':' + levelId + ':' + attempt`; domain `seed = hash(session_id + round_id)`.
- Saklar fitur: `xxl.eco_chain` `xxl.elephant` `xxl.skills` `xxl.entry_bet`, saat mati terdegradasi menjadi three-match sesama jenis murni.
- Keamanan: fase pertama papan otoritatif klien + dompet otoritatif server, satu round hanya bet/settle sekali; fase kedua unggah urutan operasi dihitung ulang server.

---

## 10. Non-Fungsional

| Item | Indikator |
|----|------|
| First screen | low-poly + tanpa GLTF, target interaktif dalam 3 detik (termasuk Vite gzip) |
| Frame rate | desktop 60fps; kartu grafis terintegrasi bisa matikan VFX |
| Pengujian | unit test `domain/**` mencakup match/gravitasi/kekangan/menang kalah; tidak menguji WebGL |
| i18n | teks HUD pakai key, ikut middleware `Language` platform |
| Aksesibilitas | pilih arah keyboard + Enter tukar (fase kedua); buta warna: template bentuk diutamakan dari warna polos |
| Volume | tanpa FBX; three + gsap setelah gzip diusahakan < 250KB kode |

---

## 11. Fase

| Fase | Ruang lingkup | Penerimaan |
|----|------|------|
| P0 | three-match sesama jenis, 8×8, tukar/gravitasi/refresh, scene orthogonal, 3 pion template | bisa main satu ronde tanpa target |
| P1 | Katalog + SpawnPool + target empat level/step HUD | level Panen Raya bisa menang |
| P2 | tabel kekangan + eliminasi ekosistem + pupuk + combo | ayam+dua serangga bisa dihilangkan; biji-bijian tidak bisa dihilangkan ayam |
| P3 | batu/pohon/genangan + sabit/cangkul/ember | level Penghancur bisa membongkar pohon |
| P4 | gajah + petak terkunci + bet/settle platform | level Pesta; rekonsiliasi saldo |
| P5 | partikel, efek suara, object pool, profil perangkat rendah, replay | anggaran performa tercapai |

P0 tidak terhubung dompet, cukup lokal `?debug=1`. P4 baru terhubung `SelfProvider`.

---

## 12. Ikhtisar Tanggung Jawab Modul

| Modul | Input | Output | Dependensi |
|------|------|------|------|
| Catalog | JSON katalog | PieceDef | tidak ada |
| RestraintTable | konfigurasi kekangan | isEcoRun(run) | Catalog |
| Board | command | snapshot baru | Catalog, RNG |
| MatchDetector | snapshot | runs[] | RestraintTable |
| Gravity | snapshot | snapshot + Fell | Board |
| Level | statistik eliminasi | progres/menang kalah | event Board |
| Score | event | skor | Level (pengali) |
| GameStateMachine | command/selesai animasi | status | domain di atas |
| PieceFactory | PieceDef | Object3D | hanya render |
| PlatformAdapter | menang kalah/taruhan | HTTP | tanpa dependensi sirkular domain |
