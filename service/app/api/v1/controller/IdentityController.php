<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\UserIdentity;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("身份认证")]
#[Apidoc\Group("user")]
class IdentityController extends BaseController
{
    #[Apidoc\Title("认证状态")]
    #[Apidoc\Url("/api/v1/user/identity/status")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
    public function status(Request $request): Response
    {
        $identity = UserIdentity::where('user_id', $request->userId)->first();

        if (!$identity) {
            return $this->success([
                'status' => 'not_submitted',
            ]);
        }

        return $this->success([
            'status'       => $identity->status,
            'real_name'    => $this->maskName($identity->real_name),
            'id_type'      => $identity->id_type,
            'review_note'  => $identity->review_note,
            'submitted_at' => $identity->created_at ? $identity->created_at->format('Y-m-d H:i:s') : null,
            'reviewed_at'  => $identity->reviewed_at ? $identity->reviewed_at->format('Y-m-d H:i:s') : null,
        ]);
    }

    #[Apidoc\Title("提交认证")]
    #[Apidoc\Url("/api/v1/user/identity/apply")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Auth(true)]
    #[Apidoc\Param(name: "real_name", type: "string", require: true, desc: "真实姓名")]
    #[Apidoc\Param(name: "id_type", type: "string", require: true, desc: "证件类型(id_card/passport/driver_license)")]
    #[Apidoc\Param(name: "id_number", type: "string", require: true, desc: "证件号码")]
    #[Apidoc\Param(name: "id_front_photo", type: "string", require: true, desc: "证件正面照")]
    #[Apidoc\Param(name: "selfie_photo", type: "string", require: true, desc: "自拍照")]
    public function apply(Request $request): Response
    {
        $validator = validator($request->all(), [
            'real_name'       => 'required|string|max:100',
            'id_type'         => 'required|string|in:id_card,passport,driver_license',
            // 下面四处的 max 与列一致：不一致会让超长值过校验、写库抛 1406 ⇒ 500（应 422）。
            // id_number 列 varchar(500) 存的却是密文 —— 实测（走模型 cast 真写库量的，非推算）
            // 密文长度 = 4*ceil((明文+17)/3) + 76：明文 301 ⇒ 500（列满）、302 ⇒ 1406 ⇒ 取 256（密文 440）。
            'id_number'       => 'required|string|max:256',
            'id_front_photo'  => 'required|string|max:255',
            'id_back_photo'   => 'nullable|string|max:255',
            'selfie_photo'    => 'required|string|max:255',
            // 列是 varchar(10)：原写 max:50 会让 10 字符以上的值过校验、写库抛 1406 ⇒ 500（应 422）
            'country'         => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // Check if already submitted and still pending or approved
        $existing = UserIdentity::where('user_id', $request->userId)->first();
        $action   = self::kycSubmitAction($existing?->status);

        if ($action === 'reject') {
            return $this->fail(trans('You already have a pending or approved KYC submission'), 422);
        }

        $now = date('Y-m-d H:i:s');

        if ($action === 'resubmit') {
            // Re-submission: update the existing record
            $existing->real_name       = $request->input('real_name');
            $existing->id_type         = $request->input('id_type');
            $existing->id_number       = $request->input('id_number');
            $existing->id_front_photo  = $request->input('id_front_photo');
            // 两个可选字段（id_back_photo / country）按**存在性**取值：请求不带该键 ⇒ 保留原值。
            // 两棵 web 树的提交体对空值不发这个键（`...(v ? {k: v} : {})`），原写法用 input() 的默认 ''
            // 会把上次提交的值悄悄抹掉。判据用 support\Request::has()（本应用自己在
            // service/support/Request.php 补的，框架没有它），口径同 UserController::updateProfile
            // 的「只送改动的字段」；**显式送空串 = 有意清空**（flutter 树正是这么发空值的）。
            if ($request->has('id_back_photo')) {
                $existing->id_back_photo = $request->input('id_back_photo', '');
            }
            $existing->selfie_photo    = $request->input('selfie_photo');
            // 同上：不带 country 键 ⇒ 保留原值
            if ($request->has('country')) {
                $existing->country = $request->input('country', '');
            }
            $existing->status          = 'pending';
            // reviewer_id 列是 NOT NULL DEFAULT 0（活库/测试库/install.sql 三处一致）⇒「清空审核人」
            // 只能写 0；写 null 会让整条 UPDATE 抛 1048、重交路径恒 500（该写法自 2026-05-22 起）。
            $existing->reviewer_id     = 0;
            $existing->review_note     = '';
            $existing->reviewed_at     = null;
            $existing->updated_at      = $now;
            $existing->save();
        } else {
            // New submission
            $identity = new UserIdentity();
            $identity->id              = $this->generateId();
            $identity->user_id         = $request->userId;
            $identity->real_name       = $request->input('real_name');
            $identity->id_type         = $request->input('id_type');
            $identity->id_number       = $request->input('id_number');
            $identity->id_front_photo  = $request->input('id_front_photo');
            $identity->id_back_photo   = $request->input('id_back_photo', '');
            $identity->selfie_photo    = $request->input('selfie_photo');
            $identity->country         = $request->input('country', '');
            $identity->status          = 'pending';
            $identity->created_at      = $now;
            $identity->updated_at      = $now;
            // 并发双提交：两次都读到「从未提交」⇒ 都走 create，后者撞 uk_user_id。
            // 对方刚插的必是 pending，与上面 reject 分支同义 —— 返回同一句话，而不是 500。
            // 连键名一起判：只认 uk_user_id 这一种重复键，主键 snowflake 撞号等其它唯一键冲突
            // 原样上抛成 500，不被伪装成「你已提交过 KYC」。
            try {
                $identity->save();
            } catch (\PDOException $e) {
                if (in_array($e->errorInfo[1] ?? null, [1062, 23000], true)
                    && str_contains($e->getMessage(), 'uk_user_id')) {
                    return $this->fail(trans('You already have a pending or approved KYC submission'), 422);
                }
                throw $e;
            }
        }

        return $this->success([], trans('KYC submitted successfully'));
    }

    /**
     * KYC 提交状态机判定（纯函数，输入为已提交记录的状态，null = 从未提交）。
     *
     * 原先内联在 apply() 中。返回的三种动作即三条分支：
     *  - reject   : 已有 pending/approved 记录，重复提交一律拒绝（422）
     *  - resubmit : 上次被驳回，复用原记录回写并重置为 pending（清空 reviewer_id/review_note/reviewed_at）
     *  - create   : 从未提交（含其它未知状态值）→ 新建记录
     *
     * @return string 'reject'|'resubmit'|'create'
     */
    private static function kycSubmitAction(?string $existingStatus): string
    {
        if ($existingStatus !== null && in_array($existingStatus, ['pending', 'approved'], true)) {
            return 'reject';
        }

        return $existingStatus === 'rejected' ? 'resubmit' : 'create';
    }

    /**
     * Mask a real name for privacy.
     * Examples:
     *   "Zhang San"   -> "Z*** S**"
     *   "张三"         -> "张*"
     *   "A"           -> "*"
     */
    private function maskName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        $name  = trim($name);
        $len   = mb_strlen($name);
        $parts = explode(' ', $name);

        if (count($parts) > 1) {
            // Multi-word name (e.g., "Zhang San")
            $masked = array_map(function (string $part): string {
                return $this->maskSinglePart($part);
            }, $parts);
            return implode(' ', $masked);
        }

        return $this->maskSinglePart($name);
    }

    /**
     * Mask a single part of a name: first character + asterisks.
     */
    private function maskSinglePart(string $part): string
    {
        $len = mb_strlen($part);

        if ($len <= 1) {
            return '*';
        }

        $first = mb_substr($part, 0, 1);
        return $first . str_repeat('*', $len - 1);
    }
}
