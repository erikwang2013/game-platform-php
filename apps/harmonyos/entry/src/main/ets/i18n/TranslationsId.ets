/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Bahasa Indonesia 表（语言短码 `id`）—— 由 `TranslationsEn.ets` 的 196 键同构生成。
 *
 * **措辞无母语者复核**：只做了机械校验（键集/占位符/结构/编译），译文质量未复核。未覆盖的键回落 `TranslationsEn.ets`（本树查表顺序：当前语言 → EN）。
 *
 * 键与分节布局**不在本文件里手写**：生成时从 `TranslationsZh.ets` 读取，故键集/顺序
 * 与真值表恒等（占位符 `{name}` 逐键保留）。与 `TranslationsEn.ets` 同款：
 * `Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildId(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = 'Memuat...';
  m['common.no_data'] = 'Tidak ada data';
  m['common.load_failed'] = 'Gagal memuat';
  m['common.retry'] = 'Coba lagi';
  m['common.back'] = '← Kembali';
  m['common.confirm'] = 'Konfirmasi';
  m['common.cancel'] = 'Batal';
  m['common.save'] = 'Simpan';
  m['common.submitting'] = 'Mengirim...';
  m['common.unnamed'] = 'Tanpa nama';
  m['common.no_description'] = 'Tanpa deskripsi';
  m['common.unlimited'] = 'Tanpa batas';
  m['common.currency'] = 'Mata uang';
  m['common.order_no'] = 'Nomor pesanan';
  m['common.balance_after'] = 'Saldo';
  m['common.deposit'] = 'Deposit';
  m['common.withdraw'] = 'Penarikan';
  m['common.exchange'] = 'Tukar';
  m['common.rate_line'] = '1 koin platform = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} koin platform';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = 'Lobi';
  m['nav.wallet'] = 'Dompet';
  m['nav.profile'] = 'Profil';
  m['app.name'] = 'Platform Game';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = 'Masukkan nama pengguna';
  m['login.password_placeholder'] = 'Masukkan kata sandi';
  m['login.password_format_register'] = 'Kata sandi 8-32 karakter, harus berisi huruf besar, huruf kecil, dan angka';
  m['login.password_min_login'] = 'Kata sandi minimal 6 karakter';
  m['login.network_error'] = 'Kesalahan jaringan, periksa koneksi Anda';
  m['login.subtitle_register'] = 'Buat akun dan mulai bermain';
  m['login.subtitle_login'] = 'Selamat datang kembali';
  m['login.hide_password'] = 'Sembunyikan';
  m['login.show_password'] = 'Tampilkan';
  m['login.register_button'] = 'Daftar';
  m['login.login_button'] = 'Masuk';
  m['login.have_account'] = 'Sudah punya akun?';
  m['login.no_account'] = 'Belum punya akun?';
  m['login.go_login'] = 'Masuk';
  m['login.go_register'] = 'Daftar sekarang';
  m['login.require_2fa'] = 'Akun ini mengaktifkan autentikasi dua faktor (2FA). Masukkan kode 6 digit dari aplikasi autentikator Anda, atau kode cadangan 10 karakter.';
  m['login.tfa_code_placeholder'] = 'Kode 6 digit / kode cadangan 10 karakter';
  m['login.tfa_back'] = 'Kembali';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = 'Verifikasi keamanan';
  m['captcha.load_failed'] = 'Gagal memuat captcha';
  m['captcha.undo'] = 'Urungkan';
  m['captcha.refresh'] = 'Gambar lain';
  m['captcha.submitting'] = 'Mengirim…';
  m['captcha.hint'] = 'Klik karakter pada gambar secara berurutan: {list}';
  m['captcha.clicked'] = 'Diklik {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = 'Lobi Game';
  m['hall.search_placeholder'] = 'Cari game...';
  m['hall.loading'] = 'Memuat game...';
  m['hall.no_match'] = 'Tidak ada game yang cocok';
  m['hall.empty'] = 'Belum ada game';
  m['hall.enter'] = 'Masuk';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = 'ID game tidak ada';
  m['game.launch_failed'] = 'Gagal menjalankan';
  m['game.title'] = 'Detail game';
  m['game.loading'] = 'Memuat detail...';
  m['game.supported_currencies'] = 'Mata uang yang didukung';
  m['game.launching'] = 'Menjalankan...';
  m['game.launch'] = 'Mulai bermain';
  m['game.not_found'] = 'Game tidak ditemukan';
  m['game.launched'] = '{name} dijalankan';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = 'Dompet saya';
  m['wallet.loading'] = 'Memuat dompet...';
  m['wallet.available_balance'] = 'Saldo tersedia';
  m['wallet.total_earned'] = 'Total diperoleh';
  m['wallet.total_spent'] = 'Total dibelanjakan';
  m['wallet.transactions'] = 'Transaksi';
  m['wallet.no_transactions'] = 'Belum ada transaksi';
  m['wallet.tx_exchange_in'] = 'Diterima dari penukaran';
  m['wallet.tx_exchange_out'] = 'Dibayar untuk penukaran';
  m['wallet.tx_game_earn'] = 'Kemenangan game';
  m['wallet.tx_game_spend'] = 'Pengeluaran game';
  m['wallet.frozen'] = 'Dibekukan {amount}';
  m['wallet.balance_after'] = 'Saldo {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = 'Gagal memuat metode pembayaran';
  m['deposit.amount_required'] = 'Masukkan jumlah deposit';
  m['deposit.amount_decimals2'] = 'Maksimal 2 angka desimal';
  m['deposit.method_required'] = 'Pilih metode pembayaran';
  m['deposit.create_failed'] = 'Gagal membuat pesanan deposit';
  m['deposit.loading_methods'] = 'Memuat metode pembayaran...';
  m['deposit.order_created'] = 'Pesanan dibuat';
  m['deposit.status_pending'] = 'Menunggu pembayaran';
  m['deposit.amount_label'] = 'Jumlah deposit';
  m['deposit.platform_amount'] = 'Koin platform yang dikreditkan';
  m['deposit.expires_at'] = 'Bayar sebelum';
  m['deposit.checkout_url'] = 'Tautan pembayaran';
  m['deposit.no_checkout_url'] = '(gateway tidak mengembalikan tautan pembayaran)';
  m['deposit.checkout_hint'] = 'Aplikasi ini tidak dapat membuka browser secara langsung. Tekan lama tautan di atas untuk menyalinnya, lalu selesaikan pembayaran di browser Anda.';
  m['deposit.again'] = 'Deposit lagi';
  m['deposit.no_methods'] = 'Tidak ada metode pembayaran yang tersedia';
  m['deposit.method_label'] = 'Metode pembayaran';
  m['deposit.currency_hint'] = 'Mata uang harus sesuai dengan metode pembayaran yang dipilih, jika tidak server akan menolak pesanan';
  m['deposit.integer_amount'] = 'Jumlah bulat';
  m['deposit.submit'] = 'Buat pesanan deposit';
  m['deposit.amount_label_currency'] = 'Jumlah ({currency})';
  m['deposit.integer_only'] = 'Jumlah dalam {currency} harus berupa bilangan bulat';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = 'Kartu bank';
  m['withdraw.method_crypto'] = 'Mata uang kripto';
  m['withdraw.status_pending'] = 'Menunggu peninjauan';
  m['withdraw.status_reviewing'] = 'Sedang ditinjau';
  m['withdraw.status_manual_review'] = 'Peninjauan manual';
  m['withdraw.status_approved'] = 'Disetujui';
  m['withdraw.status_processing'] = 'Diproses';
  m['withdraw.status_completed'] = 'Selesai';
  m['withdraw.status_paid'] = 'Sudah dibayar';
  m['withdraw.status_rejected'] = 'Ditolak';
  m['withdraw.status_cancelled'] = 'Dibatalkan';
  m['withdraw.status_failed'] = 'Gagal';
  m['withdraw.amount_required'] = 'Masukkan jumlah penarikan';
  m['withdraw.amount_invalid'] = 'Format jumlah tidak valid, maksimal 4 angka desimal';
  m['withdraw.account_required'] = 'Isi data penerima';
  m['withdraw.failed'] = 'Gagal mengirim permintaan penarikan';
  m['withdraw.submitted'] = 'Permintaan penarikan terkirim';
  m['withdraw.amount_label'] = 'Jumlah penarikan';
  m['withdraw.fee'] = 'Biaya';
  m['withdraw.actual_amount'] = 'Jumlah bersih';
  m['withdraw.created_at'] = 'Dikirim pada';
  m['withdraw.again'] = 'Tarik lagi';
  m['withdraw.method_label'] = 'Metode penarikan';
  m['withdraw.amount_platform_label'] = 'Jumlah penarikan (koin platform)';
  m['withdraw.min_amount_hint'] = 'Minimal 0.0001';
  m['withdraw.account_info'] = 'Data penerima';
  m['withdraw.account_placeholder'] = 'Email PayPal / nomor kartu bank dan bank / alamat dompet';
  m['withdraw.submit'] = 'Kirim permintaan penarikan';
  m['withdraw.review_hint'] = 'Penarikan memerlukan peninjauan manual/risiko; batas dan biaya mengikuti yang dikembalikan server.';
  m['withdraw.available'] = 'Tersedia {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = 'Gagal memuat game';
  m['exchange.amount_required'] = 'Masukkan jumlah penukaran';
  m['exchange.amount_invalid'] = 'Format jumlah tidak valid, masukkan angka';
  m['exchange.select_first'] = 'Pilih game dan mata uang terlebih dahulu';
  m['exchange.quote_failed'] = 'Gagal mendapatkan kuotasi';
  m['exchange.quote_first'] = 'Minta kuotasi terlebih dahulu';
  m['exchange.failed'] = 'Penukaran gagal';
  m['exchange.success'] = 'Penukaran selesai';
  m['exchange.buy'] = 'Beli';
  m['exchange.sell'] = 'Jual';
  m['exchange.id_label'] = 'Nomor referensi';
  m['exchange.pay_platform'] = 'Koin platform yang dibayar';
  m['exchange.sell_game'] = 'Koin game yang dijual';
  m['exchange.receive_game_after_spread'] = 'Koin game yang diterima (setelah potongan spread)';
  m['exchange.receive_platform_after_spread'] = 'Koin platform yang diterima (setelah potongan spread)';
  m['exchange.spread'] = 'Spread';
  m['exchange.rate'] = 'Kurs';
  m['exchange.again'] = 'Tukar lagi';
  m['exchange.no_games'] = 'Tidak ada game yang tersedia untuk ditukar';
  m['exchange.direction'] = 'Arah';
  m['exchange.buy_full'] = 'Beli (koin platform → koin game)';
  m['exchange.sell_full'] = 'Jual (koin game → koin platform)';
  m['exchange.select_game'] = 'Pilih game';
  m['exchange.select_currency'] = 'Pilih mata uang';
  m['exchange.no_currencies'] = 'Game ini tidak memiliki mata uang yang dapat ditukar';
  m['exchange.platform_amount_label'] = 'Jumlah koin platform';
  m['exchange.sell_amount_hint'] = 'Saat menjual, masukkan jumlah koin game di sini; server menerimanya dengan nama bidang yang sama';
  m['exchange.quoting'] = 'Mendapatkan kuotasi...';
  m['exchange.quote'] = 'Minta kuotasi';
  m['exchange.game_before_spread'] = 'Koin game yang ditukar (sebelum potongan spread)';
  m['exchange.actual_game'] = 'Koin game yang benar-benar dikreditkan';
  m['exchange.platform_before_spread'] = 'Setara koin platform (sebelum potongan spread)';
  m['exchange.actual_platform'] = 'Koin platform yang benar-benar dikreditkan';
  m['exchange.spread_pct'] = 'Tarif spread';
  m['exchange.quote_hint'] = 'Kuotasi hanya untuk tampilan; server menghitung ulang harga saat beli/jual.';
  m['exchange.confirm_buy'] = 'Konfirmasi beli';
  m['exchange.confirm_sell'] = 'Konfirmasi jual';
  m['exchange.currency_line'] = '{name} ({symbol})';
  m['exchange.game_amount_label'] = 'Jumlah koin game ({symbol})';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = 'Masukkan nama panggilan';
  m['profile.saved'] = 'Tersimpan';
  m['profile.save_failed'] = 'Gagal menyimpan';
  m['profile.logout_confirm_title'] = 'Konfirmasi keluar';
  m['profile.logout_confirm_message'] = 'Yakin ingin keluar?';
  m['profile.logout_confirm_ok'] = 'Keluar';
  m['profile.logged_out'] = 'Anda telah keluar';
  m['profile.delete_failed'] = 'Gagal menghapus akun';
  m['profile.delete_unverified'] = 'Hasil penghapusan tidak dapat dikonfirmasi; masuk lagi dan periksa';
  m['profile.delete_pending'] = 'Permintaan penghapusan telah dikirim, tetapi profil masih dapat dibaca; muat ulang untuk memastikan';
  m['profile.deleted'] = 'Akun dihapus';
  m['profile.title'] = 'Profil';
  m['profile.loading'] = 'Memuat profil...';
  m['profile.field_username'] = 'Nama pengguna';
  m['profile.field_email'] = 'Email';
  m['profile.field_phone'] = 'Telepon';
  m['profile.field_country'] = 'Negara';
  m['profile.field_language'] = 'Bahasa';
  m['profile.ui_language'] = 'Bahasa tampilan';
  m['profile.field_created_at'] = 'Terdaftar pada';
  m['profile.field_last_login'] = 'Login terakhir';
  m['profile.edit_nickname'] = 'Ubah nama panggilan';
  m['profile.saving'] = 'Menyimpan...';
  m['profile.logout'] = 'Keluar';
  m['profile.delete_account'] = 'Hapus akun';
  m['profile.delete_warning'] = 'Setelah dihapus, akun ini tidak dapat masuk lagi dan profil akan dianonimkan. Saldo yang tersisa harus ditarik hingga nol terlebih dahulu, jika tidak server akan menolak penghapusan.';
  m['profile.delete_password_placeholder'] = 'Masukkan kata sandi Anda saat ini';
  m['profile.delete_confirm_placeholder'] = 'Ketik yes untuk mengonfirmasi penghapusan';
  m['profile.deleting'] = 'Menghapus...';
  m['profile.delete_confirm_ok'] = 'Konfirmasi penghapusan';

  return m;
}

export const IDN: Record<string, string> = buildId();
