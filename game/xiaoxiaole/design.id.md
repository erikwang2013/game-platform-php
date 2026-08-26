<!-- lang-nav -->

Languages: [中文](design.md) · [English](design.en.md) · [한국어](design.ko.md) · [Русский](design.ru.md) · [Deutsch](design.de.md) · [Français](design.fr.md) · [Español](design.es.md) · [Português](design.pt.md) · [हिन्दी](design.hi.md) · [العربية](design.ar.md) · [বাংলা](design.bn.md) · **Bahasa Indonesia** · [日本語](design.ja.md)

Baik, sebagai perancang game dan pimpinan teknis 3D Anda, saya buatkan perencanaan desain *Three.js Match-3* yang lengkap. Rencana ini tidak melibatkan kode, fokus pada **perluasan elemen**, **matriks aturan**, **mekanisme penggabungan gameplay**, serta **gagasan pembangunan scene Three.js**.

---

### I. Perluasan Elemen Game (Desain Perpustakaan Pion)

Agar papan lebih kaya, berdasarkan yang Anda berikan, saya rinci elemen menjadi **6 kubu besar**, total **24 jenis** pion dasar + **4 jenis** item khusus:

| Kubu | Elemen | Catatan tambahan |
| :--- | :--- | :--- |
| **🌾 Tanaman** | pion eliminasi dasar | Padi, gandum, jagung, sorgum, jelai, oat, gandum hitam, millet, wijen, kacang tanah, kapas, rapeseed, teh, millet kuning, jewawut, soba, kedelai, kacang hijau, kacang merah, kacang hitam, kacang kuda, kacang polong, ubi jalar, kentang, talas, ubi, singkong |
| **🥬 Sayuran** | pion eliminasi dasar | Sawi putih, lobak, mentimun, tomat, cabai, terong, bawang daun, jahe, bawang putih, selada, wortel, pare, daun ketumbar, bawang kecil, sawi, seledri, bayam, kembang kol, labu kuning, labu, daun bawang |
| **🥬 Buah** | pion eliminasi dasar | Apel, pir, persik, aprikot, prem, stroberi, semangka, anggur, jujube asam, ceri gurun, kurma, kenari, almond, ara, jeruk keprok, pisang, kesemek, delima, kiwi, ceri, ceri manis |
| **🥬 Bunga/Tanaman Hias** | pion eliminasi dasar | Mawar, bunga matahari, mawar Cina, bunga perilla, henna, jengger ayam, kembang sepatu, kamelia, peony, melati, wisteria, anggrek kupu-kupu, krisan, bunga plum, anggrek, teratai, pisang raja, rehmannia, goji, ekor anjing, dandelion, rumput banteng, selada air awan |
| **🐜 Hewan** | pion eliminasi dasar | Semut, lebah, kepik berbintik tujuh, ulat, jangkrik, tawon, jangkrik lapangan, belalang, kadal, tikus, pendengar, lintah, katak, kodok, udang, ikan, rubah, tupai, kupu-kupu, belalang sembah, laba-laba, kunang-kunang |
| **🐓 Unggas/Aves** | predator menengah | Ayam, bebek, angsa, merpati, burung gereja, burung murai, burung walet, gagak, burung hantu, elang |
| **🐕 Ternak/Hewan Besar** | pion tingkat lanjut | Babi, anjing, sapi, kuda, domba, kelinci, kucing, keledai, bagal, unta |
| **🌳 Pohon/Alam** | rintangan/pion khusus | Pinus, willow, poplar, locust, paulownia, wutong, cemara, ginkgo, elm, bambu, birch, maple |
| **🔧 Alat Pertanian** | item skill | Sabit, cangkul, ember, palu, garu, nampan, keranjang punggung, topi jerami, jubah jerami, senter, batu giling, gerobak, sepeda, kapak, pikulan, bajak, batu penggiling |

---

### II. Perluasan Aturan Inti (Desain "Rantai Kekangan Ekosistem")

Logika aturan Anda pada dasarnya adalah **"eliminasi terarah"**. Di atas three-match tradisional (tiga sama langsung hilang), kami menyematkan **"pencocokan memangsa/mengekang"**. Saat pemain menyusun **pengekang** dan **yang dikekang** menjadi tiga sejajar (atau bentuk tertentu), eliminasi tingkat lanjut terpicu.

Berikut **matriks kekangan lengkap** yang saya perluas untuk Anda (A mengekang B):

| Pengekang (A) | Cara mengekang | Yang dikekang (B) | Keterangan aturan perluasan |
| :--- | :--- | :--- | :--- |
| **Ayam, bebek, angsa** | Mematuk / memangsa | Bunga, sayur-buah, serangga (semut/kepik/ulat) | Tambahan: mereka **tidak makan** biji-bijian (padi-padian), karena butirannya terlalu keras, perlu eliminasi terpisah. |
| **Anjing** | Menggigit | Ayam, bebek, angsa, merpati | Anjing tidak hanya menggigit unggas, tambahan **anjing juga menggerogoti tulang (sesuai tulang babi/sapi/kuda)**, tapi di game disederhanakan, mengekang semua unggas kecil-menengah. |
| **Babi** | Menggempur / merusak | Pohon, bunga, sayur-buah, serangga, **semua tanaman biji-bijian** | Babi adalah penghancur, tambahan: babi **tidak menggempur** anjing (karena anjing bisa menggigit babi), membentuk lingkaran kekangan tertutup. |
| **Sapi, kuda** | Menggigit / menginjak | Bunga, **tanaman biji-bijian**, bibit pohon buah | Tambahan sapi-kuda sebagai hewan besar pemakan rumput, khusus mengekang tanaman, tapi tidak makan serangga dan daging. |
| **Gajah** | Penindasan absolut (menginjak/menghempas) | **Semua elemen kecuali gajah (termasuk babi anjing sapi kuda)** | Gajah adalah kekuatan puncak. Demi keseimbangan, tambahan: gajah **tidak bisa** mengeliminasi "alat pertanian" (item), dan peluang gajah muncul di papan sangat rendah (pion langka). |
| **Sabit (item)** | Menuai | Semua tanaman biji-bijian, bunga | Sekali bersihkan semua tanaman di baris atau kolom. |
| **Cangkul (item)** | Menghancurkan | Pohon, batu (rintangan) | Khusus membersihkan rintangan berdarah tebal. |

---

### III. Desain Mekanisme Gameplay (Cara Mengoperasikan "Match-3")

Di scene 3D Three.js, kami memakai mode gabungan **"klik tukar + penentuan ekosistem"**:

1. **Operasi dasar**: pemain klik dua pion 3D yang berdekatan untuk bertukar posisi.
2. **Logika penentuan (kunci)**:
    - **Eliminasi sesama jenis**: setelah tukar, jika horizontal/vertikal membentuk **≥3 pion sama**, lakukan eliminasi dasar (misal tiga apel).
    - **Eliminasi ekosistem (spesial)**: setelah tukar, jika horizontal/vertikal membentuk **"pengekang + dua pion yang dikekang mana pun"** (misal: ayam + serangga + serangga), **tidak mensyaratkan tiga sama**, langsung memicu "animasi memangsa", ayam memakan serangga, bonus poin tambahan, dan petak itu menghasilkan **"kotoran pupuk"** (buff, skor eliminasi berikutnya dua kali lipat).
    - **Hak istimewa gajah**: gajah dan **dua pion berbeda mana pun** berjajar, memicu efek "menggertak", langsung mengosongkan tiga petak itu, mengabaikan ras.
3. **Reaksi berantai (Combo)**: setelah pion hilang, pion atas jatuh mengisi. Jika jatuh menghasilkan "rantai kekangan ekosistem" baru, otomatis memicu kombo (tanpa operasi pemain), mewujudkan sensasi seru.

---

### IV. Perencanaan Scene & Visual Three.js (Tanpa Kode)

Agar match-3 3D lebih bertekstur daripada 2D, rencananya:

| Modul | Pilihan teknis/skema desain |
| :--- | :--- |
| **Sudut kamera** | Pakai **sudut orthogonal 45 derajat (OrthographicCamera)** atau **perspektif sudut tetap**. Pastikan papan terlihat seperti "sandbox stereoskopik", memudahkan melihat tumpukan depan-belakang. Disarankan sudut tetap 2.5D, tanpa kontrol orbit (mencegah pusing). |
| **Tata letak papan** | Pakai **grid 8x8**, tetapi setiap petak diberi **variasi tinggi sumbu Y** (meniru suasana bukit ladang). Pion dilapisi silinder/kubus sebagai alas, bawahnya ada cakram pantulan. |
| **Skema model 3D** | Tidak memuat FBX/GLTF kompleks eksternal (trafik besar). **Semua pakai kombinasi geometri dasar Three.js (Group)**:<br>- **Apel**: bola + tangkai silinder.<br>- **Ayam**: ellipsoid (badan) + kerucut (paruh) + bola (kepala).<br>- **Gajah**: kubus besar (badan) + silinder memanjang (belalai) + telinga pipih.<br>- **Pohon**: kerucut (mahkota) + silinder (batang).<br>Pakai gaya **Low Poly** dengan **material cahaya lembut (MeshPhongMaterial)**, warna cerah. |
| **Lampu & suasana** | Cahaya utama: directional light (meniru sinar matahari). Cahaya bantu: ambient (mencerahkan bagian gelap). Cahaya belakang: area light (menggaris tepi). Latar pakai langit bola gradasi, dengan partikel melayang (meniru serbuk sari atau kunang-kunang). |
| **Umpan balik interaksi** | Pakai **Raycaster (deteksi sinar)** untuk klik pick. Saat klik pion, pion **melonjak ke atas + outline bercahaya (OutlinePass)**; saat tukar mainkan **interpolasi gerak halus (TWEEN atau GSAP)**; saat eliminasi mainkan **efek skala menghilang + ledakan partikel** (pakai PointsMaterial). |

---

### V. Perluasan Item Khusus & Rintangan

Agar game punya kedalaman strategi, tambahkan mekanisme berikut:

1. **Rintangan (tidak bisa dipindah)**:
    - **Batu**: menempati petak, perlu item "cangkul" atau tiga kali eliminasi biasa berdekatan untuk memecahkannya.
    - **Genangan air**: pion tidak bisa jatuh melewatinya, perlu item "ember" untuk mengeringkannya.
2. **Cara mendapatkan "alat pertanian"**:
    - Tidak muncul langsung di papan. Saat pemain **memicu "kekangan ekosistem" 3 kali berturut-turut**, slot skill di bawah layar terisi, klik untuk melepaskan skill alat pertanian terkait (misal sabit geser membersihkan satu baris).

---

### VI. Perencanaan Level & Target

| Jenis level | Kondisi menang | Batasan unik |
| :--- | :--- | :--- |
| **Level Panen Raya** | Dalam 20 langkah, eliminasi **50 biji-bijian/sayur-buah**. | Ayam bebek angsa muncul banyak di papan, mengganggu eliminasi tanaman Anda. |
| **Level Mengusir** | Dalam waktu terbatas, gunakan **anjing** mengeliminasi **15 ekor ayam/bebek**. | Papan hanya me-refresh unggas dan anjing, tanpa tanaman. |
| **Level Penghancur** | Gunakan **babi** menggempur **3 pohon besar** (darah pohon 5 poin). | Peluang babi rendah, perlu mengumpulkan tiga babi untuk memicu serangan gempuran area. |
| **Pesta Gajah** | Gunakan gajah mengeliminasi **30 pion** mana pun. | Buka diberi satu gajah, lindungi dia, tidak bisa ditukar keluar. |

---

### VII. Ringkasan Sorotan Rencana

1. **Aturan konsisten**: mengubah rantai makanan biologis (ayam makan serangga, anjing gigit ayam, gajah tak terkalahkan) menjadi "kondisi pasangan" match-3 dengan sempurna, lebih edukatif dan strategis daripada "match-3" belaka.
2. **Daya tampil 3D**: memanfaatkan mesin fisika Three.js (atau animasi simulasi), membuat pion memiliki "jatuh karena gravitasi", "memantul elastis", kesan tiga dimensi jauh lebih kuat daripada sprite 2D.
3. **Jaminan keseimbangan**: karena gajah terlalu kuat, jadikan dia refresh langka (tiap ronde paling banyak 1 sekaligus), dan tidak bisa dihasilkan lewat tukar biasa, hanya bisa dihasilkan sistem sebagai hadiah setelah combo 5 kali, mencegah game tidak seimbang.

Rencana ini mempertahankan "mudah dikuasai" match-3 klasik sekaligus menyematkan inti "perang hewan ekosistem" yang unik, dan sepenuhnya layak secara teknis di Three.js (kombinasi Geometry murni + Shader dasar). Anda bisa langsung memulai pengembangan berdasarkan cetak biru ini. Jika ada detail yang perlu digali lebih dalam (misal kurva animasi spesifik atau paduan warna partikel), beri tahu saya kapan saja. 🐘🌾

