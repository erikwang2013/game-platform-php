<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\User;
use common\model\User2FA;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Two Factor")
 * @Apidoc\Group("auth")
 */
class TwoFactorController extends BaseController
{
    /**
     * @Apidoc\Title("2FA Status")
     * @Apidoc\Url("/api/user/2fa/status")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function status(Request $request): Response
    {
        $userId = $request->userId;

        $twofa = User2FA::where('user_id', $userId)->first();

        return $this->success([
            'enabled' => $twofa && (int) $twofa->is_enabled === 1,
        ]);
    }

    /**
     * @Apidoc\Title("2FA Setup")
     * @Apidoc\Url("/api/user/2fa/setup")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function setup(Request $request): Response
    {
        $userId = $request->userId;

        // Check if already enabled
        $existing = User2FA::where('user_id', $userId)->first();
        if ($existing && (int) $existing->is_enabled === 1) {
            return $this->fail('2FA is already enabled. Disable it first to re-setup.', 422);
        }

        // Generate base32 secret
        $secret = $this->generateSecret();

        $user = User::find($userId);

        if ($existing) {
            $existing->secret = $secret;
            $existing->is_enabled = 0;
            $existing->save();
        } else {
            $twofa = new User2FA();
            $twofa->id      = $this->generateId();
            $twofa->user_id = $userId;
            $twofa->secret  = $secret;
            $twofa->is_enabled = 0;
            $twofa->save();
        }

        $qrUrl = $this->getQrCodeUrl($user->username, $secret);

        return $this->success([
            'secret'  => $secret,
            'qr_url'  => $qrUrl,
        ], '2FA secret generated. Scan QR code with your authenticator app.');
    }

    /**
     * @Apidoc\Title("2FA Enable")
     * @Apidoc\Url("/api/user/2fa/enable")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Param(name:"code",type:"string",require:true,desc:"TOTP code from authenticator app")
     */
    public function enable(Request $request): Response
    {
        $userId = $request->userId;

        $validator = validator($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $code = $request->input('code');

        $twofa = User2FA::where('user_id', $userId)->first();
        if (!$twofa) {
            return $this->fail('2FA not set up. Call /user/2fa/setup first.', 400);
        }

        // Verify TOTP code
        if (!$this->verifyTotp($twofa->secret, $code)) {
            return $this->fail('Invalid verification code', 422);
        }

        // Generate 8 backup codes
        $backupCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $backupCodes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }

        $twofa->is_enabled   = 1;
        $twofa->backup_codes = $backupCodes;
        $twofa->enabled_at   = date('Y-m-d H:i:s');
        $twofa->save();

        return $this->success([
            'backup_codes' => $backupCodes,
        ], '2FA enabled successfully. Save your backup codes in a safe place.');
    }

    /**
     * @Apidoc\Title("2FA Verify (Public)")
     * @Apidoc\Url("/api/2fa/verify")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name:"user_id",type:"string",require:true,desc:"User hashid")
     * @Apidoc\Param(name:"code",type:"string",require:true,desc:"TOTP code")
     */
    public function verify(Request $request): Response
    {
        $validator = validator($request->all(), [
            'user_id' => 'required|string',
            'code'    => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId = $this->decodeId($request->input('user_id'));
        $code   = $request->input('code');

        $twofa = User2FA::where('user_id', $userId)->first();
        if (!$twofa || (int) $twofa->is_enabled !== 1) {
            return $this->fail('2FA is not enabled for this user', 400);
        }

        // Try TOTP first
        if ($this->verifyTotp($twofa->secret, $code)) {
            return $this->success(['verified' => true], 'TOTP code verified');
        }

        // Try backup codes
        $backupCodes = $twofa->backup_codes;
        if (is_array($backupCodes) && in_array(strtoupper($code), $backupCodes)) {
            // Remove used backup code
            $backupCodes = array_values(array_diff($backupCodes, [strtoupper($code)]));
            $twofa->backup_codes = $backupCodes;
            $twofa->save();

            return $this->success(['verified' => true, 'backup_used' => true], 'Backup code verified');
        }

        return $this->fail('Invalid verification code', 422);
    }

    /**
     * @Apidoc\Title("2FA Disable")
     * @Apidoc\Url("/api/user/2fa/disable")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Param(name:"password",type:"string",require:true,desc:"Account password")
     * @Apidoc\Param(name:"code",type:"string",require:true,desc:"TOTP code")
     */
    public function disable(Request $request): Response
    {
        $userId = $request->userId;

        $validator = validator($request->all(), [
            'password' => 'required|string',
            'code'     => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $password = $request->input('password');
        $code     = $request->input('code');

        // Verify password
        $user = User::find($userId);
        if (!$user || !password_verify($password, $user->password)) {
            return $this->fail('Invalid password', 401);
        }

        $twofa = User2FA::where('user_id', $userId)->first();
        if (!$twofa || (int) $twofa->is_enabled !== 1) {
            return $this->fail('2FA is not enabled', 400);
        }

        // Verify TOTP or backup code
        $codeValid = $this->verifyTotp($twofa->secret, $code);
        if (!$codeValid) {
            $backupCodes = $twofa->backup_codes;
            if (is_array($backupCodes) && in_array(strtoupper($code), $backupCodes)) {
                $codeValid = true;
            }
        }

        if (!$codeValid) {
            return $this->fail('Invalid verification code', 422);
        }

        // Delete 2FA record
        $twofa->delete();

        return $this->success([], '2FA has been disabled');
    }

    /**
     * Generate a base32-encoded random secret.
     */
    private function generateSecret(int $length = 16): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Generate a QR code URL for the authenticator app.
     */
    private function getQrCodeUrl(string $label, string $secret): string
    {
        $issuer = getenv('APP_NAME') ?: 'GamePlatform';
        $encodedIssuer = urlencode($issuer);
        $encodedLabel  = urlencode($issuer . ':' . $label);

        return "otpauth://totp/{$encodedLabel}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Verify a TOTP code against a secret.
     *
     * Implements RFC 6238 TOTP with SHA1, 6 digits, 30-second window.
     * Allows 1 step of time drift (±30 seconds).
     */
    private function verifyTotp(string $secret, string $code): bool
    {
        $timeSlice = (int) floor(time() / 30);

        // Check current and adjacent time slices (±1 window for clock drift)
        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($code, $this->generateTotp($secret, $timeSlice + $offset))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a TOTP code for a given time slice.
     */
    private function generateTotp(string $secret, int $timeSlice): string
    {
        $secret = $this->base32Decode($secret);
        $timeSlice = pack('J', $timeSlice);

        $hash = hash_hmac('sha1', $timeSlice, $secret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $binary = (
            ((ord($hash[$offset + 0]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 decode a string to binary.
     */
    private function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(rtrim($input, '='));

        $binary = '';
        $buffer = 0;
        $bitsRemaining = 0;

        for ($i = 0; $i < strlen($input); $i++) {
            $value = strpos($alphabet, $input[$i]);
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bitsRemaining += 5;
            if ($bitsRemaining >= 8) {
                $bitsRemaining -= 8;
                $binary .= chr(($buffer >> $bitsRemaining) & 0xFF);
            }
        }

        return $binary;
    }
}
