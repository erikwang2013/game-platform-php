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
 * @Apidoc\Title("实名认证管理")
 * @Apidoc\Group("identity")
 */
class IdentityController extends BaseController
{
    /**
     * 实名认证列表
     * @Apidoc\Title("实名认证列表")
     * @Apidoc\Url("/admin/identity/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", required=false, desc="页码")
     * @Apidoc\Param(name="limit", type="int", required=false, desc="每页数量")
     * @Apidoc\Param(name="status", type="string", required=false, desc="认证状态")
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
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
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

    /**
     * 审核实名认证
     * @Apidoc\Title("审核实名认证")
     * @Apidoc\Url("/admin/identity/review")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="identity_id", type="string", required=true, desc="认证记录ID")
     * @Apidoc\Param(name="action", type="string", required=true, desc="操作:approve|reject")
     * @Apidoc\Param(name="note", type="string", required=false, desc="审核备注")
     */
    public function review(Request $request): Response
    {
        $validator = validator($request->all(), [
            'identity_id' => 'required|string',
            'action'      => 'required|string|in:approve,reject',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $identityId = $this->decodeId($request->input('identity_id'));
        $identity   = UserIdentity::find($identityId);
        if (!$identity) {
            return $this->fail('认证记录不存在', 404);
        }

        if ($identity->status !== 'pending') {
            return $this->fail('该认证已审核', 422);
        }

        $action  = $request->input('action');
        $note    = $request->input('note', '');
        $adminId = $request->adminId;

        $identity->status      = $action === 'approve' ? 'approved' : 'rejected';
        $identity->reviewer_id = $adminId;
        $identity->review_note = $note;
        $identity->reviewed_at = date('Y-m-d H:i:s');
        $identity->save();

        $label = $action === 'approve' ? '审核通过' : '已驳回';
        return $this->success([], $label);
    }
}
