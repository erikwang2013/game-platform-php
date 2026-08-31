<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\UserIdentity;
use common\service\NotificationService;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("KYC审核")
 * @Apidoc\Group("identity")
 */
class IdentityController extends BaseController
{
    /**
     * @Apidoc\Title("KYC列表")
     * @Apidoc\Desc("分页获取KYC身份认证记录列表")
     * @Apidoc\Url("/admin/identity/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("page", type="int", require=false, desc="页码")
     * @Apidoc\Param("per_page", type="int", require=false, desc="每页数量")
     * @Apidoc\Param("status", type="string", require=false, desc="审核状态(pending,approved,rejected)")
     * @Apidoc\Returned("id", type="string", desc="记录ID(hashid编码)")
     */
    public function list(Request $request): Response
    {
        $page   = (int) $request->input('page', 1);
        $limit  = (int) $request->input('limit', 15);
        $status = $request->input('status');

        $query = UserIdentity::with('user');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $total = $query->count();
        $list  = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->orderBy('created_at', 'desc')
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

    /**
     * @Apidoc\Title("审核KYC")
     * @Apidoc\Desc("审批或拒绝KYC身份认证申请")
     * @Apidoc\Url("/admin/identity/review")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("id", type="string", require=true, desc="认证记录ID(hashid编码)")
     * @Apidoc\Param("action", type="string", require=true, desc="操作(approve通过,reject拒绝)")
     * @Apidoc\Param("note", type="string", require=false, desc="审核备注")
     */
    public function review(Request $request): Response
    {
        $validator = validator($request->all(), [
            'id'     => 'required|string',
            'action' => 'required|string|in:approve,reject',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $identityId = $this->decodeId($request->input('id'));
        $identity   = UserIdentity::find($identityId);

        if (!$identity) {
            return $this->fail('Identity record not found', 404);
        }

        if ($identity->status !== 'pending') {
            return $this->fail('This identity record has already been reviewed', 422);
        }

        $action = $request->input('action');
        $note   = $request->input('note', '');

        $identity->status      = ($action === 'approve') ? 'approved' : 'rejected';
        $identity->reviewer_id = $request->adminId;
        $identity->review_note = $note;
        $identity->reviewed_at = date('Y-m-d H:i:s');
        $identity->save();

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
