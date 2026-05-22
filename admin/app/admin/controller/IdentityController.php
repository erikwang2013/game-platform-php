<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\UserIdentity;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("身份认证审核")
 * @Apidoc\Group("identity")
 */
class IdentityController extends BaseController
{
    /**
     * 身份认证列表
     * @Apidoc\Title("身份认证列表")
     * @Apidoc\Url("/admin/identity/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", require=false, desc="页码")
     * @Apidoc\Param(name="per_page", type="int", require=false, desc="每页数量")
     * @Apidoc\Param(name="status", type="string", require=false, desc="认证状态")
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
     * 审核身份认证
     * @Apidoc\Title("审核身份认证")
     * @Apidoc\Url("/admin/identity/review")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="id", type="string", require=true, desc="认证记录哈希ID")
     * @Apidoc\Param(name="action", type="string", require=true, desc="审核动作: approve|reject")
     * @Apidoc\Param(name="note", type="string", require=false, desc="审核备注")
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

        $message = ($action === 'approve') ? 'KYC approved' : 'KYC rejected';

        return $this->success([], $message);
    }
}
