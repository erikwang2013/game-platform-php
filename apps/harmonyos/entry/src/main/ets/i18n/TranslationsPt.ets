/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Português 表（语言短码 `pt`）—— 由 `TranslationsEn.ets` 的 196 键同构生成。
 *
 * **措辞无母语者复核**：只做了机械校验（键集/占位符/结构/编译），译文质量未复核。未覆盖的键回落 `TranslationsEn.ets`（本树查表顺序：当前语言 → EN）。
 *
 * 键与分节布局**不在本文件里手写**：生成时从 `TranslationsZh.ets` 读取，故键集/顺序
 * 与真值表恒等（占位符 `{name}` 逐键保留）。与 `TranslationsEn.ets` 同款：
 * `Record` 空字面量 + 逐键赋值（ArkTS 禁非空对象字面量）。
 */
export function buildPt(): Record<string, string> {
  const m: Record<string, string> = {};

  // ── 通用 ──────────────────────────────────────────────────
  m['common.loading'] = 'Carregando...';
  m['common.no_data'] = 'Sem dados';
  m['common.load_failed'] = 'Falha ao carregar';
  m['common.retry'] = 'Tentar novamente';
  m['common.back'] = '← Voltar';
  m['common.confirm'] = 'Confirmar';
  m['common.cancel'] = 'Cancelar';
  m['common.save'] = 'Salvar';
  m['common.submitting'] = 'Enviando...';
  m['common.unnamed'] = 'Sem nome';
  m['common.no_description'] = 'Sem descrição';
  m['common.unlimited'] = 'Ilimitado';
  m['common.currency'] = 'Moeda';
  m['common.order_no'] = 'Número do pedido';
  m['common.balance_after'] = 'Saldo';
  m['common.deposit'] = 'Depósito';
  m['common.withdraw'] = 'Saque';
  m['common.exchange'] = 'Troca';
  m['common.rate_line'] = '1 moeda da plataforma = {rate} {symbol}';
  m['common.platform_coin_amount'] = '{amount} moedas da plataforma';

  // ── 底部导航 / 应用名 ──────────────────────────────────
  m['nav.hall'] = 'Salão';
  m['nav.wallet'] = 'Carteira';
  m['nav.profile'] = 'Perfil';
  m['app.name'] = 'Plataforma de jogos';

  // ── 登录 / 注册 ────────────────────────────────────────
  m['login.username_placeholder'] = 'Digite o nome de usuário';
  m['login.password_placeholder'] = 'Digite a senha';
  m['login.password_format_register'] = 'A senha deve ter 8 a 32 caracteres e conter letras maiúsculas, minúsculas e números';
  m['login.password_min_login'] = 'A senha deve ter pelo menos 6 caracteres';
  m['login.network_error'] = 'Erro de rede, verifique sua conexão';
  m['login.subtitle_register'] = 'Crie uma conta e comece a jogar';
  m['login.subtitle_login'] = 'Bem-vindo de volta';
  m['login.hide_password'] = 'Ocultar';
  m['login.show_password'] = 'Mostrar';
  m['login.register_button'] = 'Cadastrar';
  m['login.login_button'] = 'Entrar';
  m['login.have_account'] = 'Já tem uma conta?';
  m['login.no_account'] = 'Não tem uma conta?';
  m['login.go_login'] = 'Entrar';
  m['login.go_register'] = 'Cadastre-se agora';
  m['login.require_2fa'] = 'Esta conta tem a autenticação de dois fatores (2FA) ativada. Informe o código de 6 dígitos do seu app autenticador ou um código de backup de 10 caracteres.';
  m['login.tfa_code_placeholder'] = 'Código de 6 dígitos / código de backup de 10 caracteres';
  m['login.tfa_back'] = 'Voltar';

  // ── 点击验证码弹框（common/CaptchaDialog.ets） ──
  m['captcha.title'] = 'Verificação de segurança';
  m['captcha.load_failed'] = 'Falha ao carregar o captcha';
  m['captcha.undo'] = 'Desfazer';
  m['captcha.refresh'] = 'Outra imagem';
  m['captcha.submitting'] = 'Enviando…';
  m['captcha.hint'] = 'Clique nos caracteres da imagem em ordem: {list}';
  m['captcha.clicked'] = 'Clicados {done}/{total}';

  // ── 游戏大厅 ──────────────────────────────────────────────
  m['hall.title'] = 'Salão de jogos';
  m['hall.search_placeholder'] = 'Pesquisar jogos...';
  m['hall.loading'] = 'Carregando jogos...';
  m['hall.no_match'] = 'Nenhum jogo correspondente';
  m['hall.empty'] = 'Ainda não há jogos';
  m['hall.enter'] = 'Entrar';

  // ── 游戏详情 ──────────────────────────────────────────────
  m['game.missing_id'] = 'ID do jogo ausente';
  m['game.launch_failed'] = 'Falha ao iniciar';
  m['game.title'] = 'Detalhes do jogo';
  m['game.loading'] = 'Carregando detalhes...';
  m['game.supported_currencies'] = 'Moedas suportadas';
  m['game.launching'] = 'Iniciando...';
  m['game.launch'] = 'Começar a jogar';
  m['game.not_found'] = 'Jogo não encontrado';
  m['game.launched'] = '{name} iniciado';

  // ── 钱包 ──────────────────────────────────────────────────
  m['wallet.title'] = 'Minha carteira';
  m['wallet.loading'] = 'Carregando carteira...';
  m['wallet.available_balance'] = 'Saldo disponível';
  m['wallet.total_earned'] = 'Total ganho';
  m['wallet.total_spent'] = 'Total gasto';
  m['wallet.transactions'] = 'Transações';
  m['wallet.no_transactions'] = 'Ainda não há transações';
  m['wallet.tx_exchange_in'] = 'Recebido na troca';
  m['wallet.tx_exchange_out'] = 'Pago na troca';
  m['wallet.tx_game_earn'] = 'Ganho em jogo';
  m['wallet.tx_game_spend'] = 'Gasto em jogo';
  m['wallet.frozen'] = 'Congelado {amount}';
  m['wallet.balance_after'] = 'Saldo {amount}';

  // ── 充值 ──────────────────────────────────────────────────
  m['deposit.load_methods_failed'] = 'Falha ao carregar os métodos de pagamento';
  m['deposit.amount_required'] = 'Digite o valor do depósito';
  m['deposit.amount_decimals2'] = 'Máximo de 2 casas decimais';
  m['deposit.method_required'] = 'Selecione um método de pagamento';
  m['deposit.create_failed'] = 'Falha ao criar o pedido de depósito';
  m['deposit.loading_methods'] = 'Carregando métodos de pagamento...';
  m['deposit.order_created'] = 'Pedido criado';
  m['deposit.status_pending'] = 'Aguardando pagamento';
  m['deposit.amount_label'] = 'Valor do depósito';
  m['deposit.platform_amount'] = 'Moedas da plataforma creditadas';
  m['deposit.expires_at'] = 'Pagar antes de';
  m['deposit.checkout_url'] = 'Link de pagamento';
  m['deposit.no_checkout_url'] = '(o gateway não retornou nenhum link de pagamento)';
  m['deposit.checkout_hint'] = 'Este aplicativo não pode abrir um navegador diretamente. Pressione e segure o link acima para copiá-lo e conclua o pagamento no navegador.';
  m['deposit.again'] = 'Depositar novamente';
  m['deposit.no_methods'] = 'Nenhum método de pagamento disponível';
  m['deposit.method_label'] = 'Método de pagamento';
  m['deposit.currency_hint'] = 'A moeda deve corresponder ao método de pagamento escolhido, caso contrário o servidor recusará o pedido';
  m['deposit.integer_amount'] = 'Valor inteiro';
  m['deposit.submit'] = 'Criar pedido de depósito';
  m['deposit.amount_label_currency'] = 'Valor ({currency})';
  m['deposit.integer_only'] = 'Os valores em {currency} devem ser números inteiros';

  // ── 提现 ──────────────────────────────────────────────────
  m['withdraw.method_bank'] = 'Cartão bancário';
  m['withdraw.method_crypto'] = 'Criptomoeda';
  m['withdraw.status_pending'] = 'Aguardando análise';
  m['withdraw.status_reviewing'] = 'Em análise';
  m['withdraw.status_manual_review'] = 'Análise manual';
  m['withdraw.status_approved'] = 'Aprovado';
  m['withdraw.status_processing'] = 'Processando';
  m['withdraw.status_completed'] = 'Concluído';
  m['withdraw.status_paid'] = 'Pago';
  m['withdraw.status_rejected'] = 'Rejeitado';
  m['withdraw.status_cancelled'] = 'Cancelado';
  m['withdraw.status_failed'] = 'Falhou';
  m['withdraw.amount_required'] = 'Digite o valor do saque';
  m['withdraw.amount_invalid'] = 'Formato de valor inválido, máximo de 4 casas decimais';
  m['withdraw.account_required'] = 'Preencha os dados do beneficiário';
  m['withdraw.failed'] = 'Falha ao enviar a solicitação de saque';
  m['withdraw.submitted'] = 'Solicitação de saque enviada';
  m['withdraw.amount_label'] = 'Valor do saque';
  m['withdraw.fee'] = 'Taxa';
  m['withdraw.actual_amount'] = 'Valor líquido';
  m['withdraw.created_at'] = 'Enviado em';
  m['withdraw.again'] = 'Sacar novamente';
  m['withdraw.method_label'] = 'Método de saque';
  m['withdraw.amount_platform_label'] = 'Valor do saque (moedas da plataforma)';
  m['withdraw.min_amount_hint'] = 'Mínimo 0.0001';
  m['withdraw.account_info'] = 'Dados do beneficiário';
  m['withdraw.account_placeholder'] = 'E-mail do PayPal / número do cartão e banco / endereço da carteira';
  m['withdraw.submit'] = 'Enviar solicitação de saque';
  m['withdraw.review_hint'] = 'Os saques exigem análise manual/de risco; os limites e as taxas são os retornados pelo servidor.';
  m['withdraw.available'] = 'Disponível {amount}';

  // ── 兑换 ──────────────────────────────────────────────────
  m['exchange.load_games_failed'] = 'Falha ao carregar os jogos';
  m['exchange.amount_required'] = 'Digite a quantidade da troca';
  m['exchange.amount_invalid'] = 'Formato de quantidade inválido, digite um número';
  m['exchange.select_first'] = 'Selecione primeiro um jogo e uma moeda';
  m['exchange.quote_failed'] = 'Falha ao obter a cotação';
  m['exchange.quote_first'] = 'Solicite primeiro uma cotação';
  m['exchange.failed'] = 'Falha na troca';
  m['exchange.success'] = 'Troca concluída';
  m['exchange.buy'] = 'Comprar';
  m['exchange.sell'] = 'Vender';
  m['exchange.id_label'] = 'Número de referência';
  m['exchange.pay_platform'] = 'Moedas da plataforma pagas';
  m['exchange.sell_game'] = 'Moedas de jogo vendidas';
  m['exchange.receive_game_after_spread'] = 'Moedas de jogo recebidas (spread descontado)';
  m['exchange.receive_platform_after_spread'] = 'Moedas da plataforma recebidas (spread descontado)';
  m['exchange.spread'] = 'Spread';
  m['exchange.rate'] = 'Taxa';
  m['exchange.again'] = 'Trocar novamente';
  m['exchange.no_games'] = 'Nenhum jogo disponível para troca';
  m['exchange.direction'] = 'Direção';
  m['exchange.buy_full'] = 'Comprar (moedas da plataforma → moedas de jogo)';
  m['exchange.sell_full'] = 'Vender (moedas de jogo → moedas da plataforma)';
  m['exchange.select_game'] = 'Selecionar um jogo';
  m['exchange.select_currency'] = 'Selecionar uma moeda';
  m['exchange.no_currencies'] = 'Este jogo não tem moedas disponíveis para troca';
  m['exchange.platform_amount_label'] = 'Quantidade de moedas da plataforma';
  m['exchange.sell_amount_hint'] = 'Ao vender, digite aqui a quantidade de moedas de jogo; o servidor a recebe com o mesmo nome de campo';
  m['exchange.quoting'] = 'Obtendo cotação...';
  m['exchange.quote'] = 'Obter cotação';
  m['exchange.game_before_spread'] = 'Moedas de jogo trocadas (antes do desconto do spread)';
  m['exchange.actual_game'] = 'Moedas de jogo efetivamente creditadas';
  m['exchange.platform_before_spread'] = 'Equivalente em moedas da plataforma (antes do desconto do spread)';
  m['exchange.actual_platform'] = 'Moedas da plataforma efetivamente creditadas';
  m['exchange.spread_pct'] = 'Taxa de spread';
  m['exchange.quote_hint'] = 'A cotação é apenas informativa; o servidor recalcula o preço na compra/venda.';
  m['exchange.confirm_buy'] = 'Confirmar compra';
  m['exchange.confirm_sell'] = 'Confirmar venda';
  m['exchange.currency_line'] = '{name} ({symbol})';
  m['exchange.game_amount_label'] = 'Quantidade de moedas de jogo ({symbol})';

  // ── 个人中心 ──────────────────────────────────────────────
  m['profile.nickname_placeholder'] = 'Digite um apelido';
  m['profile.saved'] = 'Salvo';
  m['profile.save_failed'] = 'Falha ao salvar';
  m['profile.logout_confirm_title'] = 'Confirmar saída';
  m['profile.logout_confirm_message'] = 'Tem certeza de que deseja sair?';
  m['profile.logout_confirm_ok'] = 'Sair';
  m['profile.logged_out'] = 'Você saiu da conta';
  m['profile.delete_failed'] = 'Falha ao excluir a conta';
  m['profile.delete_unverified'] = 'Não foi possível confirmar o resultado da exclusão; entre novamente e verifique';
  m['profile.delete_pending'] = 'A solicitação de exclusão foi enviada, mas o perfil ainda pode ser lido; atualize para confirmar';
  m['profile.deleted'] = 'Conta excluída';
  m['profile.title'] = 'Perfil';
  m['profile.loading'] = 'Carregando perfil...';
  m['profile.field_username'] = 'Nome de usuário';
  m['profile.field_email'] = 'E-mail';
  m['profile.field_phone'] = 'Telefone';
  m['profile.field_country'] = 'País';
  m['profile.field_language'] = 'Idioma';
  m['profile.ui_language'] = 'Idioma da interface';
  m['profile.field_created_at'] = 'Registrado em';
  m['profile.field_last_login'] = 'Último acesso';
  m['profile.edit_nickname'] = 'Alterar apelido';
  m['profile.saving'] = 'Salvando...';
  m['profile.logout'] = 'Sair';
  m['profile.delete_account'] = 'Excluir conta';
  m['profile.delete_warning'] = 'Após a exclusão, esta conta não poderá mais entrar e o perfil será anonimizado. O saldo restante deve ser sacado antes até zerar, caso contrário o servidor recusará a exclusão.';
  m['profile.delete_password_placeholder'] = 'Digite sua senha atual';
  m['profile.delete_confirm_placeholder'] = 'Digite yes para confirmar a exclusão';
  m['profile.deleting'] = 'Excluindo...';
  m['profile.delete_confirm_ok'] = 'Confirmar exclusão';

  return m;
}

export const PT: Record<string, string> = buildPt();
