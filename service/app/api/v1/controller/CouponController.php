<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Coupon;
use common\model\DepositOrder;
use common\model\GamePlayLog;
use common\model\UserCoupon;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Db;
use support\Request;
use support\Response;

#[Apidoc\Title("优惠券")]
#[Apidoc\Group("coupon")]
class CouponController extends BaseController
{
    #[Apidoc\Title("可领优惠券")]
    #[Apidoc\Url("/api/v1/coupon/available")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
    public function available(Request $request): Response
    {
        $now    = date('Y-m-d H:i:s');
        $userId = $request->userId;

        $candidates = Coupon::where('status', 1)
            ->where(function ($query) use ($now) {
                $query->whereNull('start_at')
                    ->orWhere('start_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', $now);
            })
            ->where(function ($query) {
                $query->where('total_qty', 0)
                    ->orWhereRaw('used_qty < total_qty');
            })
            ->orderBy('id', 'desc')
            ->get();

        // conditions 的用户维度聚合按整批预取一次，避免逐张券各查一次（见 buildConditionContext）
        $ctx = $this->buildConditionContext($userId, $candidates);

        $coupons = $candidates
            ->filter(function ($coupon) use ($userId, $ctx) {
                // Check user_limit: user hasn't already claimed max
                $userLimit = (int) $coupon->user_limit;
                if ($userLimit > 0) {
                    $claimed = UserCoupon::where('user_id', $userId)
                        ->where('coupon_id', $coupon->id)
                        ->count();
                    if ($claimed >= $userLimit) {
                        return false;
                    }
                }

                // Check conditions: 与 claim() 同一判据，列表里的券必然领得走
                return $this->conditionsBlockReason($coupon, $userId, $ctx) === null;
            })
            ->values()
            ->map(function ($coupon) {
                $data = $coupon->toArray();
                $data['id'] = $this->encodeId($coupon->id);
                if (!empty($coupon->game_id) && $coupon->game_id > 0) {
                    $data['game_id'] = $this->encodeId((int) $coupon->game_id);
                }
                return $data;
            });

        return $this->success(['list' => $coupons]);
    }

    #[Apidoc\Title("领取优惠券")]
    #[Apidoc\Url("/api/v1/coupon/claim")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Auth(true)]
    #[Apidoc\Param(name: "coupon_id", type: "string", require: true, desc: "优惠券ID")]
    #[Apidoc\Param(name: "captcha_key", type: "string", require: true, desc: "点击验证码 key")]
    #[Apidoc\Param(name: "clicks", type: "array", require: true, desc: "点击坐标集合，元素含 x/y")]
    public function claim(Request $request): Response
    {
        $validator = validator($request->all(), [
            'coupon_id'   => 'required|string',
            'captcha_key' => 'required|string',
            'clicks'      => 'required|array|min:2',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 领券是发放权益的入口，刷券与脚本领取都从这进
        if (!$this->captchaOk($request)) {
            return $this->fail('验证码错误，请重试', 422);
        }

        $couponId = $this->decodeId($request->input('coupon_id'));
        $userId   = $request->userId;
        $now      = date('Y-m-d H:i:s');

        $coupon = Coupon::find($couponId);
        if (!$coupon) {
            return $this->fail('优惠券不存在', 404);
        }

        // Check status
        if ((int) $coupon->status !== 1) {
            return $this->fail('优惠券已禁用', 400);
        }

        // Check time range
        if ($coupon->start_at && $coupon->start_at > $now) {
            return $this->fail('优惠券尚未开始', 400);
        }
        if ($coupon->end_at && $coupon->end_at < $now) {
            return $this->fail('优惠券已过期', 400);
        }

        // Check conditions（与 available() 同一判据，$ctx 传 null = 单券现查）
        $blocked = $this->conditionsBlockReason($coupon, $userId);
        if ($blocked !== null) {
            return $this->fail($blocked, 400);
        }

        // 扣库存与落券行必须同事务：原先两步之间失败会把库存白扣（实测 insert 抛错时 used_qty 已 +1
        // 而 user_coupon 一行没落）。校验的早退都在上面，不进事务 —— 事务里只有写。
        //
        // 限领校验原先在事务外（check-then-act）：同一用户并发两次领取都读到 count=0 ⇒ 都放行，
        // 而券表没有 uk(user_id, coupon_id) 兜底（DDL 在 install/install.sql，本批只报不改）⇒ 超领。
        // 改法＝把计数挪进事务、先锁券行：同一张券的所有领取在此串行，
        // 「数我已领 → 落我的券行」中间不再有窗口，且不需要新唯一键。
        $userLimit = (int) $coupon->user_limit;
        $failReason = '优惠券已被领完';

        $userCoupon = Db::transaction(function () use ($couponId, $userId, $userLimit, &$failReason) {
            // 锁券行（increment 的行锁要到下面才拿，撑不住上面那次计数判读）
            Coupon::where('id', $couponId)->lockForUpdate()->first();

            if ($userLimit > 0) {
                $claimed = UserCoupon::where('user_id', $userId)
                    ->where('coupon_id', $couponId)
                    ->count();
                if ($claimed >= $userLimit) {
                    $failReason = '您已达到该优惠券的领取上限';
                    return null;
                }
            }

            // Atomic increment used_qty
            $affected = Coupon::where('id', $couponId)
                ->where(function ($query) {
                    $query->where('total_qty', 0)
                        ->orWhereRaw('used_qty < total_qty');
                })
                ->increment('used_qty');

            if ($affected === 0) {
                return null; // 已被领完：上面没改到任何行，提交亦无副作用
            }

            // Create UserCoupon
            $userCoupon = new UserCoupon();
            $userCoupon->id        = $this->generateId();
            $userCoupon->user_id   = $userId;
            $userCoupon->coupon_id = $couponId;
            $userCoupon->status    = 'unused';
            $userCoupon->save();

            return $userCoupon;
        });

        if ($userCoupon === null) {
            return $this->fail($failReason, 400);
        }

        // Refresh coupon data
        $coupon->refresh();

        $data = $coupon->toArray();
        $data['id'] = $this->encodeId($coupon->id);
        if (!empty($coupon->game_id) && $coupon->game_id > 0) {
            $data['game_id'] = $this->encodeId((int) $coupon->game_id);
        }
        $data['user_coupon_id'] = $this->encodeId($userCoupon->id);

        return $this->success(['coupon' => $data], '领取成功');
    }

    #[Apidoc\Title("我的优惠券")]
    #[Apidoc\Url("/api/v1/coupon/my")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
    public function my(Request $request): Response
    {
        $userId = $request->userId;
        $status = $request->input('status');

        $query = UserCoupon::where('user_id', $userId)
            ->with('coupon');

        if ($status && in_array($status, ['unused', 'used', 'expired'])) {
            $query->where('status', $status);
        }

        $userCoupons = $query->orderBy('id', 'desc')->get();

        $list = $userCoupons->map(function ($uc) {
            $data = $uc->toArray();
            $data['id'] = $this->encodeId($uc->id);
            if ($uc->coupon) {
                $couponData = $uc->coupon->toArray();
                $couponData['id'] = $this->encodeId($uc->coupon->id);
                if (!empty($uc->coupon->game_id) && $uc->coupon->game_id > 0) {
                    $couponData['game_id'] = $this->encodeId((int) $uc->coupon->game_id);
                }
                $data['coupon'] = $couponData;
            }
            return $data;
        });

        return $this->success(['list' => $list]);
    }

    /**
     * 条件校验：返回拦截原因（null = 通过）。
     *
     * available() 与 claim() 共用同一判据，杜绝「列表里看得到、点领取才被拒」
     * （此前 available() 完全不校验 conditions，唯一的 conditions 校验长在 claim() 里）。
     * $ctx 为 available() 的批量预取上下文（见 buildConditionContext）；claim() 传 null 时
     * 按单券现查，与抽取前的内联实现逐字一致。
     */
    private function conditionsBlockReason(Coupon $coupon, int $userId, ?array $ctx = null): ?string
    {
        $conditions = json_decode($coupon->conditions ?? '{}', true) ?: [];

        if (!empty($conditions['min_deposit'])) {
            // 判不了就当「本人不满足」：坏券只让它自己看不见，不让 bccomp 把整条 available() 打成 500
            if (!self::isAmountLiteral($conditions['min_deposit'])) {
                return 'Invalid coupon condition';
            }

            // sum() 在零行时返回 int 0（不是 null），`?? '0'` 兜不住 ⇒ 必须 (string) 转型，
            // 否则 bccomp 收 int 抛 TypeError：零充值用户领取带 min_deposit 的券会 500 而非 400。
            $totalDeposit = (string) ($ctx !== null
                ? $ctx['deposit_total']
                : (DepositOrder::where('user_id', $userId)->where('status', 'confirmed')->sum('platform_amount') ?? '0'));
            if (bccomp($totalDeposit, $conditions['min_deposit'], 4) < 0) {
                return 'Minimum deposit of ' . $conditions['min_deposit'] . ' not met';
            }
        }
        if (!empty($conditions['first_user_only']) && $conditions['first_user_only']) {
            $hasDeposit = $ctx !== null
                ? $ctx['has_deposit']
                : DepositOrder::where('user_id', $userId)->where('status', 'confirmed')->exists();
            if ($hasDeposit) {
                return 'This coupon is for new users only';
            }
        }
        if (!empty($conditions['game_id'])) {
            // 判不了就当「本人不满足」（与 min_deposit 同向、fail-closed）：(int) 对非字面量是
            // **静默退化**而不是报错 —— [123]→1、true→1、'123abc'→123 全都无警告；退化的 id 一旦
            // 恰好是用户玩过的游戏，这张条件读不出来的券就被**放行**（实测红态：列表里出现，
            // claim 的授权也过去、直接走到写 user_coupon）。
            if (!self::isPositiveIntId($conditions['game_id'])) {
                return 'Invalid coupon condition';
            }

            $gameId     = (int) $conditions['game_id'];
            $gamePlayed = $ctx !== null
                ? in_array($gameId, $ctx['played_game_ids'], true)
                : GamePlayLog::where('user_id', $userId)->where('game_id', $gameId)->exists();
            if (!$gamePlayed) {
                return 'Must play the required game first';
            }
        }

        return null;
    }

    /**
     * conditions 里金额字面量的语法闸（只认十进制字符串）。
     *
     * conditions 是直写库的 JSON —— admin 的 store/update 都不收这个字段（只能手工造），值可能是
     * JSON 数字、数组，或 'abc'/'1e5' 这类 bcmath 读不了的字符串。直接喂 bccomp 会抛
     * TypeError / ValueError，而 available() 是按**整批券**过滤的 ⇒ 一张坏券会把整条列表对所有
     * 用户打成 500（claim() 侧只坏它自己）。这里判不了就由调用方按「本人不满足」处理。
     *
     * 判据与 SelfProvider::isAmountSyntaxValid() 同一套（bcmath 自证 + 拒非字符串），
     * 只是不跨层 import provider 类；两处若要合一，应上移到 common\BcMath。
     */
    private static function isAmountLiteral(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        try {
            bcadd($value, '0', 8);
        } catch (\ValueError $e) {
            return false;
        }

        return true;
    }

    /**
     * conditions 里「正整数 id」字面量的语法闸（只认 int 与纯数字字符串）。
     *
     * 与 isAmountLiteral **同源**：conditions 都是直写库的 JSON（admin 的 store/update 都不收这个
     * 字段，只能手工造），值都可能不是字面量；两侧的收场也一样 —— 判不了就由调用方按「本人不满足」
     * 处理。但**判据不同源**，所以不复用那个函数：金额要的是 bcmath 能解析的十进制字面量，
     * id 要的是整数语法（docs/API.md §7.12 的契约是 int），两处能合的只有 fail-closed 的方向。
     *
     * 特意不用 is_numeric：它认 '1e5'（会变成 id 100000）与 123.9（有损截断为 123），
     * 都不该被当成 game_id。
     */
    private static function isPositiveIntId(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        return is_string($value) && ctype_digit($value) && (int) $value > 0;
    }

    /**
     * available() 的批量预取：把 conditions 的用户维度聚合压成每请求 ≤3 次查询。
     *
     * 三条条件里 min_deposit / first_user_only 查的是同一用户的充值聚合、与券无关，
     * game_id 虽随券变化但可按券列表去重后一次 whereIn 取回 —— 逐张券各查一次会成 N+1。
     * 只对列表里真实出现的条件取数：没有任何券带 conditions 时零查询。
     *
     * @param  iterable<Coupon>  $coupons
     * @return array{deposit_total: string, has_deposit: bool, played_game_ids: int[]}
     */
    private function buildConditionContext(int $userId, iterable $coupons): array
    {
        $needsDeposit = false;
        $gameIds      = [];

        foreach ($coupons as $coupon) {
            $conditions = json_decode($coupon->conditions ?? '{}', true) ?: [];
            if (!empty($conditions['min_deposit']) || !empty($conditions['first_user_only'])) {
                $needsDeposit = true;
            }
            if (!empty($conditions['game_id']) && self::isPositiveIntId($conditions['game_id'])) {
                $gameIds[(int) $conditions['game_id']] = true;
            }
        }

        $ctx = ['deposit_total' => '0', 'has_deposit' => false, 'played_game_ids' => []];

        if ($needsDeposit) {
            $ctx['deposit_total'] = DepositOrder::where('user_id', $userId)
                ->where('status', 'confirmed')
                ->sum('platform_amount') ?? '0';
            $ctx['has_deposit'] = DepositOrder::where('user_id', $userId)
                ->where('status', 'confirmed')
                ->exists();
        }

        if ($gameIds) {
            $ctx['played_game_ids'] = GamePlayLog::where('user_id', $userId)
                ->whereIn('game_id', array_keys($gameIds))
                ->distinct()
                ->pluck('game_id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        }

        return $ctx;
    }
}
