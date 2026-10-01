<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use common\model\UserIdentity;
use common\service\NotificationService;
use support\Request;
use support\Response;

#[Apidoc\Title("KYC审核")]
#[Apidoc\Group("identity")]
class IdentityController extends BaseController
{
    #[Apidoc\Title("KYC列表")]
    #[Apidoc\Desc("分页获取KYC身份认证记录列表")]
    #[Apidoc\Url("/admin/v1/identity/list")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "page", type: "int", require: false, desc: "页码")]
    #[Apidoc\Param(name: "limit", type: "int", require: false, desc: "每页数量")]
    #[Apidoc\Param(name: "status", type: "string", require: false, desc: "审核状态(pending,approved,rejected)")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "记录ID(hashid编码)")]
    public function list(Request $request): Response
    {
        $page   = (int) $request->input('page', 1);
        // clamp [1,200]：200 是本仓客户端的最大合法取数（游戏/角色下拉一次拉全）；无上界时 ?limit=10000000 直接拉全表
        $limit  = min(200, max(1, (int) $request->input('limit', 15)));
        $status = $request->input('status');

        $query = UserIdentity::with('user');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        // 加 id 次序：game_user_identity.created_at 是 DATETIME（秒精度，install.sql:505），
        // 同秒提交的多笔是常态；只按 created_at 排的话翻页会重复/漏行 —— 而 total/last_page
        // 仍然正确，账面自洽、行对不上（KYC 审核队列漏看一条就是漏审一个人）。
        // 照 PlatformUserController::transactions 的写法。
        $total = $query->count();
        $list  = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->orderBy('created_at', 'desc')
                       ->orderBy('id', 'desc')
                       ->get()
                       ->map(function ($identity) {
                           $data = $identity->toArray();
                           $data = $this->encodeIds($data);
                           if ($identity->user) {
                               $data['user'] = $this->encodeIds([
                                   'id'       => $identity->user->id,
                                   'username' => $identity->user->username,
                               ]);
                           }
                           return $data;
                       });

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    #[Apidoc\Title("审核KYC")]
    #[Apidoc\Desc("审批或拒绝KYC身份认证申请")]
    #[Apidoc\Url("/admin/v1/identity/review")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "id", type: "string", require: true, desc: "认证记录ID(hashid编码)")]
    #[Apidoc\Param(name: "action", type: "string", require: true, desc: "操作(approve通过,reject拒绝)")]
    #[Apidoc\Param(name: "note", type: "string", require: false, desc: "审核备注")]
    public function review(Request $request): Response
    {
        $validator = validator($request->all(), [
            'id'     => 'required|string',
            'action' => 'required|string|in:approve,reject',
            'note'   => 'sometimes|nullable|string|max:500',   // game_user_identity.review_note VARCHAR(500)
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $identityId = $this->decodeId($request->input('id'));
        $identity   = UserIdentity::find($identityId);

        if (!$identity) {
            return $this->fail(trans('Identity record not found'), 404);
        }

        if ($identity->status !== 'pending') {
            return $this->fail(trans('This identity record has already been reviewed'), 422);
        }

        $action = $request->input('action');
        $note   = $request->input('note', '');

        // CAS：状态翻转与「还是 pending」是同一个原子条件。上面那次读只用来给出友好错误，
        // 真正的判据是这一行的 affected rows —— 两个管理员并发审同一单时，只有一次能拿到 1 行，
        // 另一次拿到 0 行 ⇒ 不覆盖先手结论、也不重复发通知（通知在 CAS 成功之后）。
        $affected = UserIdentity::where('id', $identityId)
            ->where('status', 'pending')
            ->update([
                'status'      => ($action === 'approve') ? 'approved' : 'rejected',
                'reviewer_id' => $request->adminId,
                'review_note' => $note,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ]);

        if ($affected === 0) {
            return $this->fail(trans('This identity record has already been reviewed'), 422);
        }

        if ($action === 'approve') {
            NotificationService::send(
                $identity->user_id,
                'kyc',
                'KYC Approved',
                'Your identity verification has been approved.',
                'identity',
                $identity->id
            );
        } else {
            NotificationService::send(
                $identity->user_id,
                'kyc',
                'KYC Rejected',
                "Your identity verification has been rejected. {$note}",
                'identity',
                $identity->id
            );
        }

        $message = ($action === 'approve') ? 'KYC approved' : 'KYC rejected';

        return $this->success([], $message);
    }
}
