/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Français 表（语言短码 `fr`）—— 由 `TranslationsEn.ets` 的 196 键同构生成。
 *
 * **措辞无母语者复核**：只做了机械校验（键集/占位符/结构/编译），译文质量未复核。未覆盖的键回落 `TranslationsEn.ets`（本树查表顺序：当前语言 → EN）。
 *
 * 键与分节布局**不在本文件里手写**：生成时从 `TranslationsZh.ets` 读取，故键集/顺序
 * 与真值表恒等（占位符 `{name}` 逐键保留）。与 `TranslationsEn.ets` 同款：
 * `Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildFr(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = 'Chargement...';
  m['common.no_data'] = 'Aucune donnée';
  m['common.load_failed'] = 'Échec du chargement';
  m['common.retry'] = 'Réessayer';
  m['common.back'] = '← Retour';
  m['common.confirm'] = 'Confirmer';
  m['common.cancel'] = 'Annuler';
  m['common.save'] = 'Enregistrer';
  m['common.submitting'] = 'Envoi en cours...';
  m['common.unnamed'] = 'Sans nom';
  m['common.no_description'] = 'Aucune description';
  m['common.unlimited'] = 'Illimité';
  m['common.currency'] = 'Devise';
  m['common.order_no'] = 'Numéro de commande';
  m['common.balance_after'] = 'Solde';
  m['common.deposit'] = 'Dépôt';
  m['common.withdraw'] = 'Retrait';
  m['common.exchange'] = 'Échange';
  m['common.rate_line'] = '1 pièce de plateforme = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} pièces de plateforme';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = 'Salle';
  m['nav.wallet'] = 'Portefeuille';
  m['nav.profile'] = 'Profil';
  m['app.name'] = 'Plateforme de jeux';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = 'Saisissez un nom d\'utilisateur';
  m['login.password_placeholder'] = 'Saisissez le mot de passe';
  m['login.password_format_register'] = 'Le mot de passe doit contenir 8 à 32 caractères, avec majuscules, minuscules et chiffres';
  m['login.password_min_login'] = 'Le mot de passe doit contenir au moins 6 caractères';
  m['login.network_error'] = 'Erreur réseau, vérifiez votre connexion';
  m['login.subtitle_register'] = 'Créez un compte et commencez à jouer';
  m['login.subtitle_login'] = 'Bon retour';
  m['login.hide_password'] = 'Masquer';
  m['login.show_password'] = 'Afficher';
  m['login.register_button'] = 'S\'inscrire';
  m['login.login_button'] = 'Se connecter';
  m['login.have_account'] = 'Vous avez déjà un compte ?';
  m['login.no_account'] = 'Vous n\'avez pas de compte ?';
  m['login.go_login'] = 'Se connecter';
  m['login.go_register'] = 'S\'inscrire maintenant';
  m['login.require_2fa'] = 'L\'authentification à deux facteurs (2FA) est activée sur ce compte. Saisissez le code à 6 chiffres de votre application d\'authentification ou un code de secours à 10 caractères.';
  m['login.tfa_code_placeholder'] = 'Code à 6 chiffres / code de secours à 10 caractères';
  m['login.tfa_back'] = 'Retour';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = 'Vérification de sécurité';
  m['captcha.load_failed'] = 'Échec du chargement du captcha';
  m['captcha.undo'] = 'Annuler';
  m['captcha.refresh'] = 'Nouvelle image';
  m['captcha.submitting'] = 'Envoi…';
  m['captcha.hint'] = 'Cliquez sur les caractères de l\'image dans l\'ordre : {list}';
  m['captcha.clicked'] = 'Cliqué {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = 'Salle de jeux';
  m['hall.search_placeholder'] = 'Rechercher des jeux...';
  m['hall.loading'] = 'Chargement des jeux...';
  m['hall.no_match'] = 'Aucun jeu correspondant';
  m['hall.empty'] = 'Aucun jeu pour le moment';
  m['hall.enter'] = 'Entrer';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = 'ID de jeu manquant';
  m['game.launch_failed'] = 'Échec du lancement';
  m['game.title'] = 'Détails du jeu';
  m['game.loading'] = 'Chargement des détails...';
  m['game.supported_currencies'] = 'Devises prises en charge';
  m['game.launching'] = 'Lancement...';
  m['game.launch'] = 'Commencer à jouer';
  m['game.not_found'] = 'Jeu introuvable';
  m['game.launched'] = '{name} lancé';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = 'Mon portefeuille';
  m['wallet.loading'] = 'Chargement du portefeuille...';
  m['wallet.available_balance'] = 'Solde disponible';
  m['wallet.total_earned'] = 'Total gagné';
  m['wallet.total_spent'] = 'Total dépensé';
  m['wallet.transactions'] = 'Transactions';
  m['wallet.no_transactions'] = 'Aucune transaction';
  m['wallet.tx_exchange_in'] = 'Reçu par échange';
  m['wallet.tx_exchange_out'] = 'Payé par échange';
  m['wallet.tx_game_earn'] = 'Gain de jeu';
  m['wallet.tx_game_spend'] = 'Dépense de jeu';
  m['wallet.frozen'] = 'Gelé {amount}';
  m['wallet.balance_after'] = 'Solde {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = 'Échec du chargement des moyens de paiement';
  m['deposit.amount_required'] = 'Saisissez le montant du dépôt';
  m['deposit.amount_decimals2'] = 'Maximum 2 décimales';
  m['deposit.method_required'] = 'Sélectionnez un moyen de paiement';
  m['deposit.create_failed'] = 'Échec de la création de la commande de dépôt';
  m['deposit.loading_methods'] = 'Chargement des moyens de paiement...';
  m['deposit.order_created'] = 'Commande créée';
  m['deposit.status_pending'] = 'En attente de paiement';
  m['deposit.amount_label'] = 'Montant du dépôt';
  m['deposit.platform_amount'] = 'Pièces de plateforme créditées';
  m['deposit.expires_at'] = 'Payer avant';
  m['deposit.checkout_url'] = 'Lien de paiement';
  m['deposit.no_checkout_url'] = '(la passerelle n\'a pas renvoyé de lien de paiement)';
  m['deposit.checkout_hint'] = 'Cette application ne peut pas ouvrir de navigateur directement. Appuyez longuement sur le lien ci-dessus pour le copier, puis effectuez le paiement dans votre navigateur.';
  m['deposit.again'] = 'Déposer à nouveau';
  m['deposit.no_methods'] = 'Aucun moyen de paiement disponible';
  m['deposit.method_label'] = 'Moyen de paiement';
  m['deposit.currency_hint'] = 'La devise doit correspondre au moyen de paiement choisi, sinon le serveur refusera la commande';
  m['deposit.integer_amount'] = 'Montant entier';
  m['deposit.submit'] = 'Créer la commande de dépôt';
  m['deposit.amount_label_currency'] = 'Montant ({currency})';
  m['deposit.integer_only'] = 'Les montants en {currency} doivent être des nombres entiers';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = 'Carte bancaire';
  m['withdraw.method_crypto'] = 'Cryptomonnaie';
  m['withdraw.status_pending'] = 'En attente de vérification';
  m['withdraw.status_reviewing'] = 'En cours de vérification';
  m['withdraw.status_manual_review'] = 'Vérification manuelle';
  m['withdraw.status_approved'] = 'Approuvé';
  m['withdraw.status_processing'] = 'En cours de traitement';
  m['withdraw.status_completed'] = 'Terminé';
  m['withdraw.status_paid'] = 'Payé';
  m['withdraw.status_rejected'] = 'Refusé';
  m['withdraw.status_cancelled'] = 'Annulé';
  m['withdraw.status_failed'] = 'Échec';
  m['withdraw.amount_required'] = 'Saisissez le montant du retrait';
  m['withdraw.amount_invalid'] = 'Format de montant invalide, maximum 4 décimales';
  m['withdraw.account_required'] = 'Saisissez les informations du bénéficiaire';
  m['withdraw.failed'] = 'Échec de l\'envoi de la demande de retrait';
  m['withdraw.submitted'] = 'Demande de retrait envoyée';
  m['withdraw.amount_label'] = 'Montant du retrait';
  m['withdraw.fee'] = 'Frais';
  m['withdraw.actual_amount'] = 'Montant net';
  m['withdraw.created_at'] = 'Envoyé le';
  m['withdraw.again'] = 'Retirer à nouveau';
  m['withdraw.method_label'] = 'Méthode de retrait';
  m['withdraw.amount_platform_label'] = 'Montant du retrait (pièces de plateforme)';
  m['withdraw.min_amount_hint'] = 'Minimum 0.0001';
  m['withdraw.account_info'] = 'Informations du bénéficiaire';
  m['withdraw.account_placeholder'] = 'E-mail PayPal / numéro de carte bancaire et banque / adresse du portefeuille';
  m['withdraw.submit'] = 'Envoyer la demande de retrait';
  m['withdraw.review_hint'] = 'Les retraits nécessitent une vérification manuelle/des risques ; les limites et les frais sont ceux renvoyés par le serveur.';
  m['withdraw.available'] = 'Disponible {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = 'Échec du chargement des jeux';
  m['exchange.amount_required'] = 'Saisissez la quantité à échanger';
  m['exchange.amount_invalid'] = 'Format de quantité invalide, saisissez un nombre';
  m['exchange.select_first'] = 'Sélectionnez d\'abord un jeu et une devise';
  m['exchange.quote_failed'] = 'Échec de l\'obtention du cours';
  m['exchange.quote_first'] = 'Demandez d\'abord un cours';
  m['exchange.failed'] = 'Échec de l\'échange';
  m['exchange.success'] = 'Échange effectué';
  m['exchange.buy'] = 'Acheter';
  m['exchange.sell'] = 'Vendre';
  m['exchange.id_label'] = 'Numéro de référence';
  m['exchange.pay_platform'] = 'Pièces de plateforme payées';
  m['exchange.sell_game'] = 'Pièces de jeu vendues';
  m['exchange.receive_game_after_spread'] = 'Pièces de jeu reçues (spread déduit)';
  m['exchange.receive_platform_after_spread'] = 'Pièces de plateforme reçues (spread déduit)';
  m['exchange.spread'] = 'Spread';
  m['exchange.rate'] = 'Cours';
  m['exchange.again'] = 'Échanger à nouveau';
  m['exchange.no_games'] = 'Aucun jeu disponible pour l\'échange';
  m['exchange.direction'] = 'Sens';
  m['exchange.buy_full'] = 'Acheter (pièces de plateforme → pièces de jeu)';
  m['exchange.sell_full'] = 'Vendre (pièces de jeu → pièces de plateforme)';
  m['exchange.select_game'] = 'Sélectionner un jeu';
  m['exchange.select_currency'] = 'Sélectionner une devise';
  m['exchange.no_currencies'] = 'Ce jeu n\'a aucune devise échangeable';
  m['exchange.platform_amount_label'] = 'Quantité de pièces de plateforme';
  m['exchange.sell_amount_hint'] = 'Lors d\'une vente, saisissez ici la quantité de pièces de jeu ; le serveur l\'accepte sous le même nom de champ';
  m['exchange.quoting'] = 'Obtention du cours...';
  m['exchange.quote'] = 'Obtenir un cours';
  m['exchange.game_before_spread'] = 'Pièces de jeu échangées (avant déduction du spread)';
  m['exchange.actual_game'] = 'Pièces de jeu réellement créditées';
  m['exchange.platform_before_spread'] = 'Équivalent en pièces de plateforme (avant déduction du spread)';
  m['exchange.actual_platform'] = 'Pièces de plateforme réellement créditées';
  m['exchange.spread_pct'] = 'Taux de spread';
  m['exchange.quote_hint'] = 'Le cours est fourni à titre indicatif ; le serveur recalcule le prix lors de l\'achat/vente.';
  m['exchange.confirm_buy'] = 'Confirmer l\'achat';
  m['exchange.confirm_sell'] = 'Confirmer la vente';
  m['exchange.currency_line'] = '{name} ({symbol})';
  m['exchange.game_amount_label'] = 'Quantité de pièces de jeu ({symbol})';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = 'Saisissez un pseudonyme';
  m['profile.saved'] = 'Enregistré';
  m['profile.save_failed'] = 'Échec de l\'enregistrement';
  m['profile.logout_confirm_title'] = 'Confirmation de déconnexion';
  m['profile.logout_confirm_message'] = 'Voulez-vous vraiment vous déconnecter ?';
  m['profile.logout_confirm_ok'] = 'Se déconnecter';
  m['profile.logged_out'] = 'Déconnecté';
  m['profile.delete_failed'] = 'Échec de la suppression du compte';
  m['profile.delete_unverified'] = 'Le résultat de la suppression n\'a pas pu être confirmé ; reconnectez-vous et vérifiez';
  m['profile.delete_pending'] = 'La demande de suppression a été envoyée, mais le profil est toujours lisible ; actualisez pour vérifier';
  m['profile.deleted'] = 'Compte supprimé';
  m['profile.title'] = 'Profil';
  m['profile.loading'] = 'Chargement du profil...';
  m['profile.field_username'] = 'Nom d\'utilisateur';
  m['profile.field_email'] = 'E-mail';
  m['profile.field_phone'] = 'Téléphone';
  m['profile.field_country'] = 'Pays';
  m['profile.field_language'] = 'Langue';
  m['profile.ui_language'] = 'Langue d\'affichage';
  m['profile.field_created_at'] = 'Inscrit le';
  m['profile.field_last_login'] = 'Dernière connexion';
  m['profile.edit_nickname'] = 'Modifier le pseudonyme';
  m['profile.saving'] = 'Enregistrement...';
  m['profile.logout'] = 'Se déconnecter';
  m['profile.delete_account'] = 'Supprimer le compte';
  m['profile.delete_warning'] = 'Après suppression, ce compte ne pourra plus se connecter et le profil sera anonymisé. Le solde restant doit d\'abord être retiré jusqu\'à zéro, sinon le serveur refusera la suppression.';
  m['profile.delete_password_placeholder'] = 'Saisissez votre mot de passe actuel';
  m['profile.delete_confirm_placeholder'] = 'Saisissez yes pour confirmer la suppression';
  m['profile.deleting'] = 'Suppression...';
  m['profile.delete_confirm_ok'] = 'Confirmer la suppression';

  return m;
}

export const FR: Record<string, string> = buildFr();
