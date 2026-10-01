<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\admin\v1\controller;
use common\model\VipLevel;
use common\model\UserVip;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("VIP等级管理")]
#[Apidoc\Group("vip")]
class VipLevelController extends BaseController
{
    #[Apidoc\Title("VIP等级列表")]
    #[Apidoc\Url("/admin/v1/vip/level/list")]
    #[Apidoc\Method("GET")]
    public function list(Request $request): Response
    {
        $list = VipLevel::orderBy('level')->get()->map(function ($item) {
            $data = $item->toArray();
            return $this->encodeIds($data);
        });
        return $this->success(['list' => $list]);
    }

    #[Apidoc\Title("新增VIP等级")]
    #[Apidoc\Url("/admin/v1/vip/level/create")]
    #[Apidoc\Method("POST")]
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'level' => 'required|integer|min:0',
            'name' => 'required|string|max:50',
            'required_exp' => 'required|integer|min:0',
            'benefits' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $this->validateBenefits((string) $request->input('benefits'));
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $exists = VipLevel::where('level', $request->input('level'))->first();
        if ($exists) {
            return $this->fail(trans('VIP level already exists'), 422);
        }

        $vl = new VipLevel();
        $vl->id = $this->generateId();
        $vl->level = (int) $request->input('level');
        $vl->name = $request->input('name');
        $vl->required_exp = (int) $request->input('required_exp');
        $vl->benefits = $request->input('benefits');
        $vl->save();

        return $this->success($this->encodeIds($vl->toArray()), trans('Created successfully'));
    }

    #[Apidoc\Title("更新VIP等级")]
    #[Apidoc\Url("/admin/v1/vip/level/{hashid}")]
    #[Apidoc\Method("PUT")]
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $vl = VipLevel::find($id);
        if (!$vl) {
            return $this->fail(trans('VIP level not found'), 404);
        }

        $validator = validator($request->all(), [
            'name' => 'nullable|string|max:50',
            'required_exp' => 'nullable|integer|min:0',
            'benefits' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if ($request->has('benefits')) {
            try {
                $this->validateBenefits((string) $request->input('benefits'));
            } catch (\InvalidArgumentException $e) {
                return $this->fail($e->getMessage(), 422);
            }
        }

        $vl->fill($request->only(['name', 'required_exp', 'benefits']));
        $vl->save();

        return $this->success($this->encodeIds($vl->toArray()), trans('Updated successfully'));
    }

    /**
     * benefits 校验：JSON 对象 + 键白名单 + 值域上下界。
     *
     * 旧实现只判 `is_array(json_decode(...))`，即"是合法 JSON"，于是键名写错与值域越界都能落库。
     * 三个键都是**折扣率/加成率**，读取点是 VipService（packages/platform-common/src/service/VipService.php:67/79/91），
     * 消费点如下 —— 值域不是我拍的，是照这几处的算术反推的：
     *  - exchange_discount：ExchangeController.php:67-68 `spreadPct -= spreadPct * d`（下方夹到 ≥0）。
     *    d>1 只是被夹成 0（等于手续费全免），d<0 反而**加收**用户手续费。【0, 1】
     *  - withdraw_fee_discount：WithdrawController.php:342-343 `feePct * (1 - d)`（夹到 ≥0）。
     *    d>1 ⇒ 手续费恒为 0（提现免费，直接漏收入），d≤0 则 :342 的守卫整段跳过、等同没配。【0, 1】
     *  - rate_bonus：ExchangeController.php:297-298 `rate * (1 + b)`。b 越大，同样平台币换到的游戏币越多
     *    （等于发钱）；b ≤ -1 会被 rateError()（:306）挡成 422，但 (-1, 0) 这段是静默压低汇率。【0, 1】
     * 键名白名单有 DDL 背书：install/install.sql:1556 的列注释就是这三个键。
     *
     * 键名白名单是必需的：VipService 一律 `$benefits['key'] ?? '0'`，键名打错**不报错、按 0 处理**，
     * 运营以为配了 5% 折扣其实一分没折。
     *
     * @throws \InvalidArgumentException 校验失败，消息可直接回给调用方
     */
    private function validateBenefits(string $raw): void
    {
        $benefits = json_decode($raw, true);
        if (!is_array($benefits) || ($benefits !== [] && array_is_list($benefits))) {
            throw new \InvalidArgumentException('benefits must be a JSON object (key => value)');
        }

        $allowed = ['exchange_discount', 'withdraw_fee_discount', 'rate_bonus'];
        foreach ($benefits as $key => $value) {
            if (!in_array((string) $key, $allowed, true)) {
                throw new \InvalidArgumentException(
                    "benefits.{$key} 不是可识别的权益键（可用: " . implode('/', $allowed) . '）'
                );
            }

            // 十进制字面量：is_numeric 认可 "1e3" / "0x10"，而 bccomp 遇到会抛 ValueError。
            // JSON 数字只做序列化取最短往返表示、不参与算术，属允许的 JSON 边界转型。
            $str = match (true) {
                is_int($value), is_float($value) => (string) json_encode($value),
                is_string($value)                => $value,
                default                          => '',
            };
            if ($str === '' || !preg_match('/^-?\d+(\.\d+)?$/', $str)) {
                throw new \InvalidArgumentException("benefits.{$key} 必须是十进制数字");
            }
            // bccomp 的 scale 是**截断**不是四舍五入：固定按 8 位比的话 1.0000000001 会被截成
            // 1.00000000，与上界相等 ⇒ 越界值静默通过。按字面量自身的小数位数取下界，比较才精确。
            $scale = 8;
            if (($dot = strpos($str, '.')) !== false) {
                $scale = max($scale, strlen($str) - $dot - 1);
            }
            if (bccomp($str, '0', $scale) < 0 || bccomp($str, '1', $scale) > 0) {
                throw new \InvalidArgumentException("benefits.{$key} 必须落在 [0, 1] 区间（当前 {$str}）");
            }
        }
    }

    #[Apidoc\Title("删除VIP等级")]
    #[Apidoc\Url("/admin/v1/vip/level/{hashid}")]
    #[Apidoc\Method("DELETE")]
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $vl = VipLevel::find($id);
        if (!$vl) {
            return $this->fail(trans('VIP level not found'), 404);
        }

        // 列名是 `level` 不是 `vip_level`（game_user_vip DDL: install.sql:1736）—— 写错列名时
        // MySQL 抛 SQLSTATE 42S22 ⇒ 整个删除端点**永远 500**，下面那条守卫成了死代码。
        $userCount = UserVip::where('level', $vl->level)->count();
        if ($userCount > 0) {
            return $this->fail(trans('%count% users are on this VIP level; it cannot be deleted', ['%count%' => (string) $userCount]), 422);
        }

        $vl->delete();
        return $this->success([], trans('Deleted successfully'));
    }
}
