/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * 身份安全域：KYC 实名认证、登录/注册、两步验证、点击验证码弹框。
 *
 * **B2 起这一域是整页文案**（不再只是异步回调那几条）：`kyc.ts` / `login.ts` /
 * `security.ts` / `core/captcha.ts` 四处的模板文字与 TS 里的静态标签都在这里。
 * 抽取前它们是中文字面量，没有「切语言」这回事。
 *
 * ⚠ 最初那四条（`kyc.upload_failed` / `kyc.submitted` / `login.session_expired` /
 * `security.2fa_off_done`）是**异步回调里落进 state 的文案**（`.then/.catch` 里 `set(…)`），
 * 渲染时机在切语言之后 —— 详见 `i18n.ts` 的 `Msg` 注释；换成渲染期求值后才要求有键可查。
 *
 * ⚠ `login.session_expired` 与 `me.del_unknown` **带占位符**：它们包着**服务端原文**
 * （`e.message`）。这两条的形状是「半句译文 + 服务端的一半」—— 服务端那一半
 * 是后端 `trans()` 出的（`X-Language` 已经跟着界面语言走），本地不翻。
 *
 * ⚠ `security.codes_hint` 与 `kyc.hint` **原文里各有一处 `<b>` 加粗**，B2 抽取时把加粗
 * 去掉了（整句收进一条键）。理由：加粗落在句子中间，拆成「前段/加粗段/后段」三条键时，
 * 某些语言的语序会让其中一段为空串，而「无空值」是 `i18n.spec.ts` 钉着的不变量。
 * 代价只是少了那两处视觉强调，文字一字未改。
 */
export const IDENTITY: Record<string, [string, string]> = {
  // —— KYC ——
  'kyc.upload_failed': ['Upload failed, please retry', '上传失败，请重试'],
  'kyc.submitted': ['Submitted, awaiting review', '已提交，等待审核'],
  // —— 登录 ——
  'login.session_expired': ['{msg} (please sign in again)', '{msg}（请重新登录）'],
  // 下面三条是 `login.error` 的另外三个写入点（原为裸中文字面量 ⇒ 同一个信号两副面孔）。
  'login.code2fa_err': ['Enter the 6-digit code, or a 10-character backup code.', '请输入 6 位动态码，或 10 位备份码。'],
  'login.tfa_no_ticket': ['The server asked for two-factor verification but did not issue a ticket. Please try again later.', '服务端要求二次验证但未下发票据，请稍后重试。'],
  'login.token_missing': ['The sign-in response is missing a token. Please try again later.', '登录响应缺少令牌，请稍后重试。'],
  // —— 两步验证 ——
  'security.2fa_off_done': ['Two-factor authentication is off.', '两步验证已关闭。'],

  // —— B2 身份安全域 ——
  'captcha.title': ['Security check', '安全验证'],
  'captcha.hint_ordered': ['Tap the characters in order:', '按顺序点击图中文字：'],
  'captcha.hint_plain': ['Tap the image in the order shown', '请按图片提示依次点击'],
  'captcha.image_alt': ['CAPTCHA image', '点击验证码'],
  'captcha.progress': ['Tapped {done} of {need}', '已点击 {done} 点（需 {need} 点）'],
  'captcha.reload': ['New image', '换一张'],
  'captcha.undo': ['Undo', '撤销'],
  'captcha.loading': ['Loading CAPTCHA…', '验证码加载中…'],
  'kyc.title': ['Identity verification', '实名认证'],
  'kyc.status.not_submitted': ['Not submitted', '未提交'],
  'kyc.status.pending': ['Under review', '审核中'],
  'kyc.status.approved': ['Verified', '已认证'],
  'kyc.status.rejected': ['Rejected', '已驳回'],
  'kyc.type.id_card': ['ID card', '身份证'],
  'kyc.type.passport': ['Passport', '护照'],
  'kyc.type.driver_license': ["Driver's license", '驾照'],
  'kyc.hint': ['Once verified, withdrawals use the verified tier (higher per-transaction/day/month limits and a lower fee rate); unverified or under-review accounts use the default tier. Your ID details are used only for compliance review and are never shown publicly.', '认证通过后提现额度按 已认证档 计（更高单笔/日/月额度、更低费率）；未认证或审核中按默认档计。证件信息仅用于合规审核，不会对外展示。'],
  'kyc.real_name': ['Name', '姓名'],
  'kyc.id_type': ['ID type', '证件类型'],
  'kyc.submitted_at': ['Submitted at', '提交时间'],
  'kyc.reviewed_at': ['Reviewed at', '审核时间'],
  'kyc.pending_hint': ['Usually reviewed within 1-2 business days; once approved your withdrawal limits are upgraded automatically — no further action needed.', '通常 1-2 个工作日内出结果，通过后提现额度自动升级，无需再操作。'],
  'kyc.approved_title': ['Verification complete', '已完成认证'],
  'kyc.approved_hint': ['Your withdrawals use the verified tier.', '你的提现按已认证档计。'],
  'kyc.go_withdraw': ['Withdraw', '去提现'],
  'kyc.rejected_hint': ['Your last submission was rejected. Edit the fields below and resubmit — this overwrites the previous record.', '上次提交被驳回，可按下面的驳回原因修改后重新提交（会覆盖原记录）。'],
  'kyc.form_title': ['Submit verification documents', '提交认证资料'],
  'kyc.real_name_ph': ['Must match your ID', '与证件一致'],
  'kyc.id_number': ['ID number', '证件号码'],
  'kyc.id_number_ph': ['The number on your ID', '证件上的号码'],
  'kyc.country': ['Country/region (optional)', '国家/地区（可选）'],
  'kyc.country_failed': ['Failed to load the country list', '国家列表加载失败'],
  'kyc.country_none': ['Not selected', '未选择'],
  'kyc.photo_front': ['ID front photo', '证件正面照'],
  'kyc.photo_back': ['ID back photo (optional)', '证件背面照（可选）'],
  'kyc.photo_selfie': ['Selfie holding your ID', '手持自拍照'],
  'kyc.uploading': ['Uploading…', '上传中…'],
  'kyc.uploaded': ['Uploaded', '已上传'],
  'kyc.upload_hint': ['Supports jpg / png / gif / webp, up to 5MB each.', '支持 jpg / png / gif / webp，单张不超过 5MB。'],
  'kyc.submit': ['Submit verification', '提交认证'],
  'kyc.err_too_big': ['Image is over 5MB — please compress it and try again', '图片超过 5MB，请压缩后再试'],
  'kyc.err_name_required': ['Please enter your real name', '请填写真实姓名'],
  'kyc.err_number_required': ['Please enter your ID number', '请填写证件号码'],
  'kyc.err_uploading': ['Photos are still uploading, please wait', '照片仍在上传中，请稍候'],
  'kyc.err_front_required': ['Please upload the front of your ID', '请上传证件正面照'],
  'kyc.err_selfie_required': ['Please upload a selfie holding your ID', '请上传手持自拍照'],
  'login.tagline': ['Sign in to play, and to view your wallet and messages', '登录后即可开局、查看钱包与消息'],
  'login.tab_register': ['Register', '注册'],
  'login.code2fa_label': ['Code / backup code', '动态码 / 备份码'],
  'login.code2fa_ph': ['6-digit code or 10-character backup code', '6 位动态码或 10 位备份码'],
  'login.verifying': ['Verifying…', '验证中…'],
  'login.verify_submit': ['Verify and sign in', '验证并登录'],
  'login.back_to_login': ['Back to sign in', '返回重新登录'],
  'login.username': ['Username', '用户名'],
  'login.username_ph': ['Enter your username', '请输入用户名'],
  'login.username_err': ['Username must be at least 3 characters', '用户名至少 3 个字符'],
  'login.password': ['Password', '密码'],
  'login.password_ph': ['Enter your password', '请输入密码'],
  'login.password_err': ['Password must be at least 6 characters', '密码至少 6 个字符'],
  'login.busy': ['Signing in…', '登录中…'],
  'login.reg_username_ph': ['3-20 letters or digits', '3-20 位字母或数字'],
  'login.email': ['Email', '邮箱'],
  'login.email_err': ['Enter a valid email address', '请输入有效邮箱'],
  'login.reg_password_ph': ['8-32 characters, with upper and lower case letters and digits', '8-32 位，含大小写字母和数字'],
  'login.reg_password_err': ['Password must be 8-32 characters and include upper and lower case letters and digits', '密码 8-32 个字符，需含大小写字母和数字'],
  'login.nickname': ['Nickname (optional)', '昵称（可选）'],
  'login.nickname_ph': ['Display name', '展示用昵称'],
  'login.invite': ['Invite code (optional)', '邀请码（可选）'],
  'login.invite_ph': ['8-character code shared by a friend', '朋友分享的 8 位码'],
  'login.invite_err': ['Invite code is at most 12 characters', '邀请码最多 12 个字符'],
  'login.registering': ['Creating account…', '注册中…'],
  'login.register_submit': ['Create account', '创建账号'],
  'login.captcha_login': ['Confirm sign-in', '确认登录'],
  'login.captcha_register': ['Confirm registration', '确认注册'],
  'security.title': ['Account security', '账号安全'],
  'security.codes_title': ['Save these 8 backup codes now', '请立即保存这 8 个备份码'],
  'security.codes_hint': ['Use them to sign in if you lose your authenticator (step 2 of the sign-in page accepts a 10-character backup code). Once you close this page you will never see them again — the server does not show them a second time, it only invalidates them. Each code works once.', '验证器丢失时用它们登录（登录页第二步可填 10 位备份码）。此页关掉就再也看不到 —— 服务端不回显，只核销。每个码只能用一次。'],
  'security.copy_all': ['Copy all', '复制全部'],
  'security.copy_secret': ['Copy key', '复制密钥'],
  'security.codes_close': ['I have saved them — close', '我已抄好，关闭'],
  'security.badge_on': ['Enabled', '已开启'],
  'security.badge_off': ['Not enabled', '未开启'],
  'security.on_title': ['Two-factor authentication', '两步验证'],
  'security.on_hint': ['When signing in you must enter the 6-digit code from your authenticator app in addition to your password. Turning it off requires your password and the current code.', '登录时除密码外还要输入验证器 App 中的 6 位动态码。关闭需要密码 + 当前动态码。'],
  'security.password': ['Login password', '登录密码'],
  'security.password_ph': ['Enter your current password', '请输入当前密码'],
  'security.code': ['Authenticator code', '动态验证码'],
  'security.code_ph': ['6-digit code', '6 位动态码'],
  'security.off_busy': ['Working…', '处理中…'],
  'security.disable': ['Turn off two-factor', '关闭两步验证'],
  'security.step1': ['Step 1: add the key to your authenticator app', '第一步：把密钥加进验证器 App'],
  'security.step1_hint': ['Google Authenticator / Authy / 1Password and others all support "enter key manually". There is no QR image here, so the key is shown in groups of 4 — just type it in (case-insensitive).', 'Google Authenticator / Authy / 1Password 等都可「手动输入密钥」。没有二维码图，密钥按 4 位一组显示，抄进去即可（大小写不敏感）。'],
  'security.secret': ['Key', '密钥'],
  'security.otpauth': ['You can also paste this into the app (otpauth link)', '也可以直接粘贴给 App（otpauth 链接）'],
  'security.step2': ['Step 2: enter the 6-digit code shown in the app', '第二步：输入 App 显示的 6 位动态码'],
  'security.verifying': ['Verifying…', '校验中…'],
  'security.enable': ['Turn on two-factor', '启用两步验证'],
  'security.off_hint': ['Once enabled, signing in requires your password plus the 6-digit code from your authenticator app — so even a leaked password is not enough. Enabling gives you 8 backup codes one time only; keep them safe, they are the only way to sign in if you lose the authenticator.', '开启后，登录需要「密码 + 验证器 App 的 6 位动态码」。即使密码泄漏，别人也进不来。开启时会一次性给你 8 个备份码，请务必存好 —— 那是验证器丢失后唯一的登录方式。'],
  'security.generating': ['Generating key…', '正在生成密钥…'],
  'login.tfa_hint': ['This account has two-factor authentication enabled: enter the 6-digit code from your authenticator app; if you lost the device, use a 10-character backup code instead.', '该账号已开启两步验证：请输入验证器 App 中的 6 位动态码；设备丢失时可改用 10 位备份码。'],
};
