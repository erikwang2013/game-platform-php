/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Español 表（语言短码 `es`）—— 由 `TranslationsEn.ets` 的 196 键同构生成。
 *
 * **措辞无母语者复核**：只做了机械校验（键集/占位符/结构/编译），译文质量未复核。未覆盖的键回落 `TranslationsEn.ets`（本树查表顺序：当前语言 → EN）。
 *
 * 键与分节布局**不在本文件里手写**：生成时从 `TranslationsZh.ets` 读取，故键集/顺序
 * 与真值表恒等（占位符 `{name}` 逐键保留）。与 `TranslationsEn.ets` 同款：
 * `Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildEs(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = 'Cargando...';
  m['common.no_data'] = 'Sin datos';
  m['common.load_failed'] = 'Error al cargar';
  m['common.retry'] = 'Reintentar';
  m['common.back'] = '← Volver';
  m['common.confirm'] = 'Confirmar';
  m['common.cancel'] = 'Cancelar';
  m['common.save'] = 'Guardar';
  m['common.submitting'] = 'Enviando...';
  m['common.unnamed'] = 'Sin nombre';
  m['common.no_description'] = 'Sin descripción';
  m['common.unlimited'] = 'Sin límite';
  m['common.currency'] = 'Moneda';
  m['common.order_no'] = 'Número de pedido';
  m['common.balance_after'] = 'Saldo';
  m['common.deposit'] = 'Recarga';
  m['common.withdraw'] = 'Retiro';
  m['common.exchange'] = 'Canje';
  m['common.rate_line'] = '1 moneda de plataforma = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} monedas de plataforma';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = 'Sala';
  m['nav.wallet'] = 'Cartera';
  m['nav.profile'] = 'Perfil';
  m['app.name'] = 'Plataforma de juegos';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = 'Introduce el nombre de usuario';
  m['login.password_placeholder'] = 'Introduce la contraseña';
  m['login.password_format_register'] = 'La contraseña debe tener 8-32 caracteres e incluir mayúsculas, minúsculas y números';
  m['login.password_min_login'] = 'La contraseña debe tener al menos 6 caracteres';
  m['login.network_error'] = 'Error de red, comprueba tu conexión';
  m['login.subtitle_register'] = 'Crea una cuenta y empieza a jugar';
  m['login.subtitle_login'] = 'Bienvenido de nuevo';
  m['login.hide_password'] = 'Ocultar';
  m['login.show_password'] = 'Mostrar';
  m['login.register_button'] = 'Registrarse';
  m['login.login_button'] = 'Iniciar sesión';
  m['login.have_account'] = '¿Ya tienes una cuenta?';
  m['login.no_account'] = '¿No tienes cuenta?';
  m['login.go_login'] = 'Iniciar sesión';
  m['login.go_register'] = 'Regístrate ahora';
  m['login.require_2fa'] = 'Esta cuenta tiene activada la autenticación en dos pasos (2FA). Introduce el código de 6 dígitos de tu app de autenticación o un código de respaldo de 10 caracteres.';
  m['login.tfa_code_placeholder'] = 'Código de 6 dígitos / código de respaldo de 10 caracteres';
  m['login.tfa_back'] = 'Volver';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = 'Verificación de seguridad';
  m['captcha.load_failed'] = 'Error al cargar el captcha';
  m['captcha.undo'] = 'Deshacer';
  m['captcha.refresh'] = 'Otra imagen';
  m['captcha.submitting'] = 'Enviando…';
  m['captcha.hint'] = 'Haz clic en los caracteres de la imagen en orden: {list}';
  m['captcha.clicked'] = 'Clics {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = 'Sala de juegos';
  m['hall.search_placeholder'] = 'Buscar juegos...';
  m['hall.loading'] = 'Cargando juegos...';
  m['hall.no_match'] = 'No hay juegos coincidentes';
  m['hall.empty'] = 'Aún no hay juegos';
  m['hall.enter'] = 'Entrar';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = 'Falta el ID del juego';
  m['game.launch_failed'] = 'Error al iniciar';
  m['game.title'] = 'Detalles del juego';
  m['game.loading'] = 'Cargando detalles...';
  m['game.supported_currencies'] = 'Monedas compatibles';
  m['game.launching'] = 'Iniciando...';
  m['game.launch'] = 'Empezar a jugar';
  m['game.not_found'] = 'Juego no encontrado';
  m['game.launched'] = '{name} iniciado';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = 'Mi cartera';
  m['wallet.loading'] = 'Cargando cartera...';
  m['wallet.available_balance'] = 'Saldo disponible';
  m['wallet.total_earned'] = 'Total ganado';
  m['wallet.total_spent'] = 'Total gastado';
  m['wallet.transactions'] = 'Transacciones';
  m['wallet.no_transactions'] = 'Aún no hay transacciones';
  m['wallet.tx_exchange_in'] = 'Recibido por canje';
  m['wallet.tx_exchange_out'] = 'Pagado por canje';
  m['wallet.tx_game_earn'] = 'Ganancia de juego';
  m['wallet.tx_game_spend'] = 'Gasto de juego';
  m['wallet.frozen'] = 'Congelado {amount}';
  m['wallet.balance_after'] = 'Saldo {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = 'Error al cargar los métodos de pago';
  m['deposit.amount_required'] = 'Introduce el importe de la recarga';
  m['deposit.amount_decimals2'] = 'Máximo 2 decimales';
  m['deposit.method_required'] = 'Selecciona un método de pago';
  m['deposit.create_failed'] = 'Error al crear el pedido de recarga';
  m['deposit.loading_methods'] = 'Cargando métodos de pago...';
  m['deposit.order_created'] = 'Pedido creado';
  m['deposit.status_pending'] = 'Pendiente de pago';
  m['deposit.amount_label'] = 'Importe de la recarga';
  m['deposit.platform_amount'] = 'Monedas de plataforma acreditadas';
  m['deposit.expires_at'] = 'Pagar antes de';
  m['deposit.checkout_url'] = 'Enlace de pago';
  m['deposit.no_checkout_url'] = '(la pasarela no devolvió ningún enlace de pago)';
  m['deposit.checkout_hint'] = 'Esta aplicación no puede abrir un navegador directamente. Mantén pulsado el enlace de arriba para copiarlo y completa el pago en tu navegador.';
  m['deposit.again'] = 'Recargar de nuevo';
  m['deposit.no_methods'] = 'No hay métodos de pago disponibles';
  m['deposit.method_label'] = 'Método de pago';
  m['deposit.currency_hint'] = 'La moneda debe coincidir con el método de pago elegido; de lo contrario, el servidor rechazará el pedido';
  m['deposit.integer_amount'] = 'Importe entero';
  m['deposit.submit'] = 'Crear pedido de recarga';
  m['deposit.amount_label_currency'] = 'Importe ({currency})';
  m['deposit.integer_only'] = 'Los importes en {currency} deben ser números enteros';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = 'Tarjeta bancaria';
  m['withdraw.method_crypto'] = 'Criptomoneda';
  m['withdraw.status_pending'] = 'Pendiente de revisión';
  m['withdraw.status_reviewing'] = 'En revisión';
  m['withdraw.status_manual_review'] = 'Revisión manual';
  m['withdraw.status_approved'] = 'Aprobado';
  m['withdraw.status_processing'] = 'Procesando';
  m['withdraw.status_completed'] = 'Completado';
  m['withdraw.status_paid'] = 'Pagado';
  m['withdraw.status_rejected'] = 'Rechazado';
  m['withdraw.status_cancelled'] = 'Cancelado';
  m['withdraw.status_failed'] = 'Fallido';
  m['withdraw.amount_required'] = 'Introduce el importe del retiro';
  m['withdraw.amount_invalid'] = 'Formato de importe no válido, máximo 4 decimales';
  m['withdraw.account_required'] = 'Introduce los datos del beneficiario';
  m['withdraw.failed'] = 'Error al enviar la solicitud de retiro';
  m['withdraw.submitted'] = 'Solicitud de retiro enviada';
  m['withdraw.amount_label'] = 'Importe del retiro';
  m['withdraw.fee'] = 'Comisión';
  m['withdraw.actual_amount'] = 'Importe neto';
  m['withdraw.created_at'] = 'Enviado el';
  m['withdraw.again'] = 'Retirar de nuevo';
  m['withdraw.method_label'] = 'Método de retiro';
  m['withdraw.amount_platform_label'] = 'Importe del retiro (monedas de plataforma)';
  m['withdraw.min_amount_hint'] = 'Mínimo 0.0001';
  m['withdraw.account_info'] = 'Datos del beneficiario';
  m['withdraw.account_placeholder'] = 'Correo de PayPal / número de tarjeta y banco / dirección de la cartera';
  m['withdraw.submit'] = 'Enviar solicitud de retiro';
  m['withdraw.review_hint'] = 'Los retiros requieren revisión manual/de riesgos; los límites y las comisiones son los que devuelve el servidor.';
  m['withdraw.available'] = 'Disponible {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = 'Error al cargar los juegos';
  m['exchange.amount_required'] = 'Introduce la cantidad a canjear';
  m['exchange.amount_invalid'] = 'Formato de cantidad no válido, introduce un número';
  m['exchange.select_first'] = 'Selecciona primero un juego y una moneda';
  m['exchange.quote_failed'] = 'Error al obtener la cotización';
  m['exchange.quote_first'] = 'Solicita primero una cotización';
  m['exchange.failed'] = 'Error en el canje';
  m['exchange.success'] = 'Canje completado';
  m['exchange.buy'] = 'Comprar';
  m['exchange.sell'] = 'Vender';
  m['exchange.id_label'] = 'Número de referencia';
  m['exchange.pay_platform'] = 'Monedas de plataforma pagadas';
  m['exchange.sell_game'] = 'Monedas de juego vendidas';
  m['exchange.receive_game_after_spread'] = 'Monedas de juego recibidas (spread descontado)';
  m['exchange.receive_platform_after_spread'] = 'Monedas de plataforma recibidas (spread descontado)';
  m['exchange.spread'] = 'Spread';
  m['exchange.rate'] = 'Tasa';
  m['exchange.again'] = 'Canjear de nuevo';
  m['exchange.no_games'] = 'No hay juegos disponibles para canjear';
  m['exchange.direction'] = 'Dirección';
  m['exchange.buy_full'] = 'Comprar (monedas de plataforma → monedas de juego)';
  m['exchange.sell_full'] = 'Vender (monedas de juego → monedas de plataforma)';
  m['exchange.select_game'] = 'Seleccionar un juego';
  m['exchange.select_currency'] = 'Seleccionar una moneda';
  m['exchange.no_currencies'] = 'Este juego no tiene monedas canjeables';
  m['exchange.platform_amount_label'] = 'Cantidad de monedas de plataforma';
  m['exchange.sell_amount_hint'] = 'Al vender, aquí se introduce la cantidad de monedas de juego; el servidor la recibe con el mismo nombre de campo';
  m['exchange.quoting'] = 'Obteniendo cotización...';
  m['exchange.quote'] = 'Obtener cotización';
  m['exchange.game_before_spread'] = 'Monedas de juego canjeadas (antes de descontar el spread)';
  m['exchange.actual_game'] = 'Monedas de juego realmente acreditadas';
  m['exchange.platform_before_spread'] = 'Equivalente en monedas de plataforma (antes de descontar el spread)';
  m['exchange.actual_platform'] = 'Monedas de plataforma realmente acreditadas';
  m['exchange.spread_pct'] = 'Tasa de spread';
  m['exchange.quote_hint'] = 'La cotización es solo informativa; el servidor recalcula el precio al comprar/vender.';
  m['exchange.confirm_buy'] = 'Confirmar compra';
  m['exchange.confirm_sell'] = 'Confirmar venta';
  m['exchange.currency_line'] = '{name} ({symbol})';
  m['exchange.game_amount_label'] = 'Cantidad de monedas de juego ({symbol})';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = 'Introduce un apodo';
  m['profile.saved'] = 'Guardado';
  m['profile.save_failed'] = 'Error al guardar';
  m['profile.logout_confirm_title'] = 'Confirmar cierre de sesión';
  m['profile.logout_confirm_message'] = '¿Seguro que quieres cerrar sesión?';
  m['profile.logout_confirm_ok'] = 'Cerrar sesión';
  m['profile.logged_out'] = 'Sesión cerrada';
  m['profile.delete_failed'] = 'Error al eliminar la cuenta';
  m['profile.delete_unverified'] = 'No se pudo confirmar el resultado de la eliminación; vuelve a iniciar sesión y comprueba';
  m['profile.delete_pending'] = 'La solicitud de eliminación se envió, pero el perfil aún se puede leer; actualiza para confirmar';
  m['profile.deleted'] = 'Cuenta eliminada';
  m['profile.title'] = 'Perfil';
  m['profile.loading'] = 'Cargando perfil...';
  m['profile.field_username'] = 'Nombre de usuario';
  m['profile.field_email'] = 'Correo';
  m['profile.field_phone'] = 'Teléfono';
  m['profile.field_country'] = 'País';
  m['profile.field_language'] = 'Idioma';
  m['profile.ui_language'] = 'Idioma de la interfaz';
  m['profile.field_created_at'] = 'Registrado el';
  m['profile.field_last_login'] = 'Último inicio de sesión';
  m['profile.edit_nickname'] = 'Cambiar apodo';
  m['profile.saving'] = 'Guardando...';
  m['profile.logout'] = 'Cerrar sesión';
  m['profile.delete_account'] = 'Eliminar cuenta';
  m['profile.delete_warning'] = 'Una vez eliminada, esta cuenta no podrá iniciar sesión y el perfil se anonimizará. El saldo restante debe retirarse antes hasta dejarlo a cero; de lo contrario, el servidor rechazará la eliminación.';
  m['profile.delete_password_placeholder'] = 'Introduce tu contraseña actual';
  m['profile.delete_confirm_placeholder'] = 'Escribe yes para confirmar la eliminación';
  m['profile.deleting'] = 'Eliminando...';
  m['profile.delete_confirm_ok'] = 'Confirmar eliminación';

  return m;
}

export const ES: Record<string, string> = buildEs();
