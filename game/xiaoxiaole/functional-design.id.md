# Xiaoxiaole Taman — Desain Fungsional
<!-- lang-nav -->

Languages: **中文** · [English](functional-design.en.md) · [한국어](functional-design.ko.md) · [Русский](functional-design.ru.md) · [Deutsch](functional-design.de.md) · [Français](functional-design.fr.md) · [Español](functional-design.es.md) · [Português](functional-design.pt.md) · [हिन्दी](functional-design.hi.md) · [العربية](functional-design.ar.md) · [বাংলা](functional-design.bn.md) · [Bahasa Indonesia](functional-design.id.md) · [日本語](functional-design.ja.md)


> Spesifikasi yang bisa pemain lihat, operasikan, dan terima. Layering teknis lihat `architecture.id.md`; visi elemen lihat `design.id.md`; jadwal lihat `plan.id.md`.
>
> Satu kalimat: tukar pion berdekatan di sandbox taman stereoskopik, bersihkan papan dengan "tiga sama" atau "pemangsa memakan mangsa", selesaikan target level.

---

## 1. Definisi Produk

| Item | Isi |
|----|------|
| Nama | Xiaoxiaole Taman |
| Jenis | three-match 8×8 + kekangan ekosistem |
| Sudut pandang | sandbox orthogonal 2.5D tetap, tidak bisa diputar |
| Operasi | klik dua pion berdekatan untuk bertukar (hanya atas/bawah/kiri/kanan) |
| Bentuk platform | H5 buatan sendiri, dibuka dari lobby game `launch` |
| Pengalaman sukses | langsung bisa three-match begitu mulai; saat pertama kali "ayam makan serangga" merasakan aturan naik level; kombo jatuh berirama |

**V1 tidak mengerjakan:** peringkat real-time dalam ronde, tontonan teman, pembinaan pion, dunia terbuka, model halus GLTF, level kustom pemain.

---

## 2. Alur Pemain

```
Di lobby klik "Mulai"
  → halaman muat (baca session)
  → pilih level (daftar empat level + saldo, P4 baru tampilkan potongan biaya)
  → ronde
       HUD: target / langkah atau hitung mundur / skor / combo / slot skill
       Papan: klik pilih → klik berdekatan tukar
       Tanpa eliminasi: memantul kembali, tidak potong langkah
       Ada eliminasi: potong 1 langkah → animasi hilang → jatuh → isi pion → kombo otomatis
  → settlement menang / gagal
  → level berikutnya / coba ulang / kembali pilih level
```

Pertama kali masuk "Level Panen Raya" muncul 3 petunjuk lalu hilang, bisa dilewati, tidak muncul lagi (localStorage).

---

## 3. Loop Inti

1. Lihat target (kurang berapa tanaman / ayam-bebek / pohon / petak diinjak gajah).
2. Cari tiga sama, atau geser pengekang ke dekat dua mangsa.
3. Tukar → hilang → kombo jatuh.
4. Eliminasi ekosistem meninggalkan pupuk di petak asal, skor petak itu ×2 di eliminasi berikutnya.
5. 3 kali settlement ber-ekosistem berturut-turut, slot skill menyala, pakai sabit/cangkul/ember untuk membuka jalan.
6. Target penuh dan langkah/waktu masih sisa → menang.

---

## 4. Antarmuka

| Antarmuka | Elemen | Perilaku |
|------|------|------|
| Muat | nama game, progres | session tidak valid maka muncul petunjuk kembali ke lobby |
| Pilih level | empat kartu level: nama, ringkasan target, sudah terbuka atau belum | V1 empat level semua terbuka; P4 tampilkan biaya masuk |
| HUD ronde atas | nama level, progress bar target, sisa langkah atau hitung mundur, skor, combo | hitung mundur berjalan detik, membeku saat jeda |
| HUD ronde bawah | slot skill (maks 2), jeda | slot abu-abu saat belum terisi |
| Papan | petak 8×8 + pion | pilih memantul+outline; petak ilegal tanpa outline |
| Jeda | lanjut / mulai ulang / menyerah | mulai ulang habiskan satu percobaan; menyerah settlement gagal |
| Menang | skor, sisa langkah, apakah beri hadiah (P4) | level berikutnya / kembali pilih level |
| Gagal | alasan (langkah/waktu habis), selisih target | coba ulang / kembali pilih level |
| Saldo tidak cukup | teks + pergi deposit | hanya P4 |

Keyboard (P5): panah mengubah pilihan, Enter tukar dengan petak arah yang dipilih. V1 hanya mouse/sentuh.

---

## 5. Aturan Operasi (sudut pandang pemain)

- Hanya bisa menukar pion **orthogonal berdekatan** dan keduanya bisa dipindah.
- Petak batu, pohon, genangan air tidak bisa menjadi objek tukar. Gajah terkunci tidak bisa ditukar keluar (mangsa ditukar masuk).
- Setelah tukar horizontal/vertikal tidak ada "tiga sama valid" → tukar kembali, **tidak potong langkah, tidak potong waktu**.
- Ada tiga sama valid → potong 1 langkah (level hitung mundur tidak potong langkah, hanya jalan waktu).
- Semua kombo selesai dimainkan baru menerima klik berikutnya; klik papan di tengah kombo tidak valid.
- Tiga sama diagonal tidak dihitung. Perpotongan bentuk L/T hanya menghilangkan tiap petak sekali.

---

## 6. Tiga Jenis Eliminasi (Fungsi)

Prioritas: **gajah > ekosistem > sesama jenis**. Satu baris hanya dihitung skor sekali dengan prioritas tertinggi.

### 6.1 Sesama Jenis

Tiga atau lebih **spesies sama** berjajar satu garis. Contoh: apel-apel-apel.

| Panjang | Hasil yang pemain lihat |
|------|----------------|
| 3 | mengecil hilang, skor dasar |
| 4 | hilang, petak tengah muncul pupuk |
| 5+ | hilang, slot skill +1 charge (dibatasi skill yang diizinkan level) |

### 6.2 Ekosistem (Memangsa)

Satu garis berisi **tepat 1 pengekang**, sisanya semua mangsanya, mangsa tidak perlu sama. Contoh: ayam-semut-kepik.

| Pengekang | Bisa makan | Tidak bisa makan |
|--------|------|--------|
| Ayam, bebek, angsa | bunga, sayur, buah, serangga | biji-bijian |
| Anjing | ayam, bebek, angsa, merpati dll. unggas | tanaman, serangga |
| Babi | pohon, bunga, sayur, buah, serangga, biji-bijian | anjing |
| Sapi, kuda | bunga, biji-bijian, bibit pohon | serangga, daging |
| Gajah | lihat 6.3 | rintangan, alat pertanian |

Pemain melihat: animasi memangsa → tiga petak kosong (V1 pengekang ikut pergi) → petak asal pengekang meninggalkan pupuk.

### 6.3 Gajah

Satu garis berisi 1 gajah + dua petak lain pion dapat-eliminasi apa pun → tiga petak kosong, mengabaikan kubu. Papan paling banyak 1 gajah. Tidak "terbentuk" dari tukar biasa; combo mencapai 5 dijatuhkan sistem ke petak atas kosong, atau ditempatkan di pembukaan level.

---

## 7. Katalog Muncul V1 (bukan 100 spesies di rencana)

Semua spesies rencana dipertahankan sebagai data katalog, tetapi **ronde V1 hanya me-refresh di bawah ini**, menjamin bisa dipahami dan bisa dihabiskan.

| Spesies | Kubu | Level muncul | Pengenalan pemain |
|------|------|----------|----------|
| Gandum wheat | biji-bijian | Panen Raya, Penghancur, Pesta | bulir gandum keemasan |
| Padi rice | biji-bijian | Panen Raya | bulir hijau |
| Jagung corn | biji-bijian | Panen Raya | tongkol kuning |
| Sawi putih cabbage | sayuran | Panen Raya | bola daun hijau muda |
| Tomat tomato | sayuran | Panen Raya | bola merah |
| Apel apple | buah | Panen Raya, Penghancur, Pesta | bola merah + tangkai |
| Mawar rose | bunga | Penghancur | kelopak merah |
| Semut ant | serangga | Panen Raya (bobot rendah) | hitam kecil |
| Kepik ladybug | serangga | Panen Raya | titik merah |
| Ayam hen | unggas | Panen Raya, Mengusir, Pesta | ellipsoid+paruh |
| Bebek duck | unggas | Panen Raya, Mengusir | paruh pipih |
| Angsa goose | unggas | Mengusir | leher panjang |
| Merpati pigeon | unggas | Mengusir | abu-abu |
| Anjing dog | ternak | Mengusir, Pesta | berkaki empat |
| Babi pig | ternak | Penghancur, Pesta | ellipsoid merah muda |
| Pinus pine | pohon/rintangan | Penghancur | mahkota kerucut, tidak bisa ditukar |
| Gajah elephant | puncak | Pesta; level lain combo 5 hadiah | kubus besar+belalai |

Alat pertanian (sabit, cangkul, ember) **tidak di papan**, hanya di HUD. Alat pertanian lain di rencana tidak rilis di V1.

---

## 8. Spesifikasi Level

Menang/kalah ditentukan setelah **seluruh rangkaian animasi kombo** selesai.

### 8.1 Level Panen Raya

- Pool: gandum, padi, jagung, sawi putih, tomat, apel, ayam, bebek; semut/kepik bobot rendah.
- Menang: 20 langkah mengeliminasi **50** pion berperan tanaman (biji-bijian+sayur+buah+bunga). Ayam bebek yang dihilangkan tidak dihitung.
- Kalah: langkah 0 dan target belum penuh.
- Skill: sabit (bisa dipakai setelah terisi).
- Tutorial: ①klik berdekatan tukar ②tiga sama akan hilang ③ayam bisa makan dua serangga/sayur/buah di dekatnya, tapi tidak makan gandum.

### 8.2 Level Mengusir

- Pool: ayam, bebek, angsa, merpati, anjing. Tanpa tanaman.
- Menang: dalam **90 detik** pakai **eliminasi ekosistem anjing** membersihkan 15 ekor unggas.
- Kalah: waktu habis.
- **Three-match tiga ayam tidak dihitung target** (harus menyelesaikan ekosistem anjing makan unggas).
- Skill: tidak ada. Jeda membekukan waktu.

### 8.3 Level Penghancur

- Pool: gandum, apel, mawar, babi (bobot rendah). Tetap 3 pohon pinus, HP=5, tidak bisa ditukar, tidak bisa dijatuhi/lewat.
- Menang: HP 3 pohon nol.
- Kalah: 25 langkah habis.
- Pohon kehilangan darah: ekosistem babi (pohon dalam run mangsa) -2; tiga babi satu garis memicu **gempuran 3×3** (pohon dalam jangkauan -5); cangkul ke satu pohon -3; three-match biasa berdekatan -1.
- Skill: cangkul.

### 8.4 Pesta Gajah

- Pool: gandum, apel, ayam, anjing, babi. Pembukaan 1 gajah terkunci dekat tengah.
- Menang: aturan **gajah** mengeliminasi 30 petak (sesama jenis/ekosistem tidak dihitung target ini).
- Kalah: 30 langkah habis.
- Tidak me-refresh gajah kedua. Pemain menukar mangsa ke samping atau atas-bawah gajah.
- Skill: tidak ada.

---

## 9. Rintangan, Pupuk, Skill

| Fungsi | Persepsi pemain | Aturan |
|------|----------|------|
| Batu | abu-abu tidak bisa diklik | HP3; ada eliminasi berdekatan -1; cangkul sekali hancurkan |
| Pohon | model besar tidak bisa diklik | lihat Penghancur |
| Genangan air | permukaan petak mengkilap | pion di atas berhenti satu petak di atas genangan; setelah ember mengeringkan, jatuh pulih |
| Pupuk | tumpukan gelap di permukaan petak | petak itu dieliminasi berikutnya skor ×2, lalu hilang |
| Sabit | ikon bar bawah | pilih satu baris atau kolom, hanya bersihkan tanaman, tidak potong langkah, habiskan 1 charge |
| Cangkul | ikon bar bawah | klik 1 batu atau pohon |
| Ember | ikon bar bawah | klik 1 petak genangan |

Charge: dalam satu settlement penuh yang dipicu satu operasi pemain, selama pernah muncul eliminasi ekosistem, hitungan +1; mencapai 3 dapat 1 slot, batas 2. 5 three-match sama juga +1 slot (berbagi slot dengan charge ekosistem).

V1 Panen Raya tanpa batu/genangan; Penghancur tanpa genangan. Genangan tetap di katalog, tidak menghalangi empat level jalur utama.

---

## 10. Skor & Ekonomi

```
Sesama jenis  10 × jumlah hilang × combo × pupuk
Ekosistem     25 × jumlah hilang × combo × pupuk
Gajah         40 × jumlah hilang × combo
Skill bersih  8 × jumlah hilang
Rusak rintangan 20 × jumlah pecah
```

combo: eliminasi pertama operasi ini = 1, setiap putaran kombo lagi +1; operasi manual pemain berikutnya kembali ke 1.

**Dompet P4:**

- Pilih level mulai potong biaya masuk (default 1 koin game per level).
- Menang settlement sesuai bintang: sisa sumber daya ≥50% tiga bintang, ≥20% dua bintang, selain itu satu bintang; hadiah 2 / 3 / 5 (bisa dikonfigurasi).
- Gagal tidak mengembalikan biaya masuk.
- Keluar tanpa satu pun pion ditukar → uang kembali.
- Saldo tidak cukup tidak bisa mulai ronde.

V1 (P0–P3) tanpa potong biaya, bisa langsung main lokal.

---

## 11. Daftar Fungsi & Penerimaan

| ID | Fungsi | Penerimaan | Fase |
|----|------|------|------|
| F01 | klik tukar 8×8 | berdekatan bisa tukar, diagonal tidak, tanpa hilang memantul kembali | P0 |
| F02 | three-match sesama jenis+gravitasi+isi | tiga gandum hilang, atas jatuh, atas diisi pion baru | P0 |
| F03 | kombo | jatuh otomatis hilang lagi, angka combo +1 | P0 |
| F04 | pilih empat level | klik masuk HUD target terkait | P1 |
| F05 | target Panen Raya | 20 langkah 50 tanaman, hitungan hanya tanaman | P1 |
| F06 | eliminasi ekosistem | ayam+dua serangga hilang; ayam+dua gandum tidak hilang | P2 |
| F07 | pupuk | setelah ekosistem petak itu hilang lagi skor dua kali lipat sekali | P2 |
| F08 | target Mengusir | three-match ayam tidak dihitung; anjing makan ayam dihitung; 90s | P2 |
| F09 | pohon & cangkul | pohon tidak bisa ditukar; cangkul/babi bisa membongkar | P3 |
| F10 | tiga babi 3×3 | tiga babi satu garis, pohon dalam jangkauan langsung pecah | P3 |
| F11 | sabit | bersihkan satu baris tanaman, tidak potong langkah | P3 |
| F12 | gajah terkunci | gajah tidak bisa ditukar keluar; gajah+dua pion kosongkan tiga petak | P4 |
| F13 | target Pesta | hanya aturan gajah dihitung 30 | P4 |
| F14 | biaya masuk/beri hadiah | rekonsiliasi saldo, settlement berulang tidak beri dua kali | P4 |
| F15 | tutorial | tiga kalimat petunjuk, skip permanen | P1 |
| F16 | jeda/mulai ulang/menyerah | waktu membeku; menyerah dihitung gagal | P1 |
| F17 | perangkat rendah matikan partikel | setelah saklar frame rate stabil bisa main | P5 |

---

## 12. Batas (wajib dikunci)

1. Katalog boleh besar, **jenis refresh satu level ≤ 8**.
2. Alat pertanian tidak di papan.
3. Ayam tidak makan biji-bijian: satu garis "ayam+gandum+gandum" bukan ekosistem, juga bukan sesama jenis, memantul kembali.
4. Anjing tidak makan tanaman; babi tidak menggempur anjing.
5. Papan sekaligus paling banyak 1 gajah.
6. Selama kombo dimainkan input dibuang.
7. Menang/kalah tidak ditentukan di tengah animasi.
8. V1 pengekang pergi bersama mangsa.
9. Level Mengusir waktu 90 detik, tidak pakai langkah.
10. Genangan tidak masuk empat level jalur utama.
