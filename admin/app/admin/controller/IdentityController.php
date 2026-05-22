<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\UserIdentity;
use support\Request;
use support\Response;

class IdentityController extends BaseController
{
    /**
     * GET /admin/identity/list
     *
     * Paginated list of KYC identity verification records.
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
     * PUT /admin/identity/review
     *
     * Approve or reject a KYC identity verification submission.
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
