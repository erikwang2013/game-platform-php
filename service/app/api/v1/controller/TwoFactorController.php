<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\User;
use app\model\User2FA;
use hg\apidoc\annotation as Apidoc;
use support\Log;
use support\Redis;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("双因素认证")
 * @Apidoc\Group("auth")
 */
class TwoFactorController extends BaseController
{
    /**
     * @Apidoc\Title("2FA状态")
     * @Apidoc\Url("/api/user/2fa/status")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function status(Request $request): Response
    {
        $user2FA = User2FA::where('user_id', $request->userId)
            ->where('is_enabled', 1)
            ->first();

        return $this->success(['enabled' => $user2FA !== null]);
    }

    /**
     * @Apidoc\Title("设置2FA")
     * @Apidoc\Url("/api/user/2fa/setup")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     */
    public function setup(Request $request): Response
    {
        $userId = $request->userId;

        // Remove any previous non-enabled setup for this user
        User2FA::where('user_id', $userId)->where('is_enabled', 0)->delete();

        // Generate a random 32-byte secret (Base32 encoded for TOTP compatibility)
        $secret = $this->generateSecret();

        $user2FA = new User2FA();
        $user2FA->id         = $this->generateId();
        $user2FA->user_id    = $userId;
        $user2FA->secret     = $secret;
        $user2FA->is_enabled = 0;
        $user2FA->save();

        // Build otpauth:// URL for QR code generation
        $user    = User::find($userId);
        $issuer  = rawurlencode(getenv('APP_NAME', 'Game Platform'));
        $label   = rawurlencode($user ? ($user->email ?: $user->username) : 'user');
        $qrUrl   = "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";

        return $this->success([
            'secret'  => $secret,
            'qr_url'  => $qrUrl,
        ], '2FA setup initiated — verify with a TOTP code to enable');
    }

    /**
     * @Apidoc\Title("启用2FA")
     * @Apidoc\Url("/api/user/2fa/enable")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="code", type="string", require=true, desc="6位TOTP验证码")
     */
    public function enable(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId = $request->userId;
        $code   = $request->input('code');

        $user2FA = User2FA::where('user_id', $userId)
            ->where('is_enabled', 0)
            ->first();

        if (!$user2FA) {
            return $this->fail('No pending 2FA setup found. Call /setup first.', 404);
        }

        if (!$this->verifyTOTP($user2FA->secret, $code)) {
            return $this->fail('Invalid TOTP code', 422);
        }

        // Generate 8 backup codes (each 10 random alphanumeric characters)
        $backupCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $backupCodes[] = $this->generateBackupCode();
        }

        $user2FA->is_enabled   = 1;
        $user2FA->backup_codes = json_encode($backupCodes);
        $user2FA->enabled_at   = date('Y-m-d H:i:s');
        $user2FA->save();

        return $this->success([
            'backup_codes' => $backupCodes,
        ], '2FA enabled successfully');
    }

    /**
     * @Apidoc\Title("验证2FA")
     * @Apidoc\Url("/api/2fa/verify")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="pending_2fa_token", type="string", require=true, desc="登录返回的短期2FA票据")
     * @Apidoc\Param(name="code", type="string", require=true, desc="6位TOTP验证码")
     */
    public function verify(Request $request): Response
    {
        $validator = validator($request->all(), [
            'pending_2fa_token' => 'required|string',
            'code'              => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 用户身份取自登录时签发的短期票据（含已过密码校验标记），客户端无法自选用户
        try {
            $payload = jwt_wrapper()->verify($request->input('pending_2fa_token'));
        } catch (\Throwable) {
            return $this->fail('Invalid or expired verification session', 401);
        }

        $userId = (int) ($payload->sub ?? 0);
        $scope  = $payload->scope ?? '';
        if ($userId <= 0 || $scope !== 'pending_2fa') {
            return $this->fail('Invalid or expired verification session', 401);
        }

        $bound = $this->boundUserId($request);
        if ($bound > 0 && $bound !== $userId) {
            return $this->fail('user_id does not match current session', 403);
        }

        $code = $request->input('code');

        // 逐用户锁定：5 次失败锁定 15 分钟（Redis 故障时 fail-closed，拒绝校验）
        $lockCheck = $this->assert2faNotLocked($userId);
        if ($lockCheck !== null) {
            return $lockCheck;
        }

        $user2FA = User2FA::where('user_id', $userId)
            ->where('is_enabled', 1)
            ->first();

        if (!$user2FA) {
            return $this->fail('2FA is not enabled for this user', 404);
        }

        $user = User::find($userId);
        if (!$user || (int) $user->status !== 1) {
            return $this->fail('Account is disabled', 403);
        }

        // Check TOTP code
        if ($this->verifyTOTP($user2FA->secret, $code)) {
            $this->clear2faFailures($userId);
            return $this->issueLogin($user, $request);
        }

        // Check backup codes
        $backupCodes = json_decode($user2FA->backup_codes, true) ?: [];
        $matchIndex  = array_search($code, $backupCodes, true);

        if ($matchIndex !== false) {
            // Remove the used backup code
            unset($backupCodes[$matchIndex]);
            $user2FA->backup_codes = json_encode(array_values($backupCodes));
            $user2FA->save();
            $this->clear2faFailures($userId);

            return $this->issueLogin($user, $request);
        }

        $recorded = $this->record2faFailure($userId);
        if ($recorded !== null) {
            return $recorded;
        }
        return $this->fail('Invalid TOTP code', 422);
    }

    /**
     * @Apidoc\Title("禁用2FA")
     * @Apidoc\Url("/api/user/2fa/disable")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="password", type="string", require=true, desc="密码")
     * @Apidoc\Param(name="code", type="string", require=true, desc="6位TOTP验证码")
     */
    public function disable(Request $request): Response
    {
        $validator = validator($request->all(), [
            'password' => 'required|string',
            'code'     => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId   = $request->userId;
        $password = $request->input('password');
        $code     = $request->input('code');

        // Verify password
        $user = User::find($userId);
        if (!$user || !password_verify($password, $user->password)) {
            return $this->fail('Password is incorrect', 422);
        }

        // Verify TOTP
        $user2FA = User2FA::where('user_id', $userId)
            ->where('is_enabled', 1)
            ->first();

        if (!$user2FA) {
            return $this->fail('2FA is not enabled', 404);
        }

        if (!$this->verifyTOTP($user2FA->secret, $code)) {
            return $this->fail('Invalid TOTP code', 422);
        }

        $user2FA->delete();

        return $this->success([], '2FA disabled successfully');
    }

    // -----------------------------------------------------------------
    //  Internal helpers
    // -----------------------------------------------------------------

    /**
     * 2FA 校验通过后签发正式 token，响应结构与 AuthController::login 一致。
     */
    private function issueLogin(User $user, Request $request): Response
    {
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = $request->getRealIp() ?? '';
        $user->save();

        $accessToken  = jwt_wrapper()->create(['sub' => $user->id, 'username' => $user->username]);
        $refreshToken = jwt_wrapper()->create(['sub' => $user->id, 'token_type' => 'refresh']);

        return $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user'          => [
                'id'       => $this->encodeId($user->id),
                'username' => $user->username,
                'nickname' => $user->nickname,
                'avatar'   => $user->avatar,
            ],
        ], 'Login successful');
    }

    /**
     * Optional call-context bind: JWT sub if present, else 0 (public login-step verify).
     */
    private function boundUserId(Request $request): int
    {
        $authed = (int) ($request->userId ?? 0);
        if ($authed > 0) {
            return $authed;
        }
        $header = $request->header('Authorization', '');
        if (!is_string($header) || !preg_match('/^Bearer\s+(\S+)/', $header, $m)) {
            return 0;
        }
        try {
            $payload = jwt_wrapper()->verify($m[1]);
            return (int) ($payload->sub ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Generate a random Base32-encoded secret (32 raw bytes).
     */
    private function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret   = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Generate a random 10-character alphanumeric backup code.
     */
    private function generateBackupCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $code  = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    private function base32Decode(string $base32): string
    {
        $base32 = strtoupper(rtrim($base32, '='));
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $decoded = '';
        for ($i = 0, $len = strlen($base32); $i < $len; $i++) {
            $val = strpos($alphabet, $base32[$i]);
            if ($val === false) {
                return '';
            }
            $buffer = ($buffer << 5) | $val;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 0xFF);
            }
        }
        return $decoded;
    }

    /**
     * Verify a TOTP code against a secret.
     *
     * Uses 30-second time window with ±1 window drift tolerance.
     *
     * @param string $secret Base32-encoded secret
     * @param string $code   6-digit code to verify
     * @return bool
     */
    private function verifyTOTP(string $secret, string $code): bool
    {
        // RFC 4648 Base32 解码后才是真实 HMAC 密钥（Authenticator 存储的也是解码后的字节）
        $key = $this->base32Decode($secret);
        if ($key === '') {
            return false;
        }

        $timeSlice = (int)(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            $hash = hash_hmac('sha1', pack('J', $timeSlice + $i), $key, true);
            $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
            $binary = (ord($hash[$offset]) & 0x7F) << 24
                    | (ord($hash[$offset + 1]) & 0xFF) << 16
                    | (ord($hash[$offset + 2]) & 0xFF) << 8
                    | (ord($hash[$offset + 3]) & 0xFF);
            $otp = $binary % 1000000;
            if (str_pad((string)$otp, 6, '0', STR_PAD_LEFT) === $code) {
                return true;
            }
        }
        return false;
    }

    private const TOTP_MAX_FAILURES = 5;
    private const TOTP_LOCK_SECONDS = 900;

    private function totpFailKey(int $userId): string
    {
        return "2fa:fail:{$userId}";
    }

    private function totpLockKey(int $userId): string
    {
        return "2fa:lock:{$userId}";
    }

    private function assert2faNotLocked(int $userId): ?Response
    {
        try {
            if (Redis::exists($this->totpLockKey($userId))) {
                return $this->fail('Too many failed attempts. Try again later.', 429);
            }
        } catch (\Throwable $e) {
            Log::error('2FA lock check Redis failed (fail-closed): ' . $e->getMessage());
            return $this->fail('Verification temporarily unavailable', 503);
        }
        return null;
    }

    private function record2faFailure(int $userId): ?Response
    {
        try {
            $key = $this->totpFailKey($userId);
            $count = (int) Redis::incr($key);
            if ($count === 1) {
                Redis::expire($key, self::TOTP_LOCK_SECONDS);
            }
            if ($count >= self::TOTP_MAX_FAILURES) {
                Redis::setex($this->totpLockKey($userId), self::TOTP_LOCK_SECONDS, '1');
                Redis::del($key);
                return $this->fail('Too many failed attempts. Try again later.', 429);
            }
        } catch (\Throwable $e) {
            Log::error('2FA failure counter Redis failed (fail-closed): ' . $e->getMessage());
            return $this->fail('Verification temporarily unavailable', 503);
        }
        return null;
    }

    private function clear2faFailures(int $userId): void
    {
        try {
            Redis::del($this->totpFailKey($userId));
            Redis::del($this->totpLockKey($userId));
        } catch (\Throwable $e) {
            Log::error('2FA clear failures Redis failed: ' . $e->getMessage());
        }
    }
}
