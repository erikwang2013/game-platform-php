<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\api\v1\controller;
use common\model\Tournament;
use common\model\TournamentEntry;
use common\service\FeatureFlag;
use support\Request;
use support\Response;
use hg\apidoc\annotation as Apidoc;

/**
 * @Apidoc\Title("赛事管理")
 * @Apidoc\Group("tournament")
 */
class TournamentController extends BaseController
{
    /**
     * @Apidoc\Title("赛事列表")
     * @Apidoc\Url("/api/tournament/list")
     * @Apidoc\Method("GET")
     */
    public function list(Request $request): Response
    {
        if (!FeatureFlag::isEnabled('tournament')) return $this->fail('Tournaments not available', 503);

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $status = $request->input('status', 'active');

        $query = Tournament::with('game')->withCount('entries')->orderBy('id', 'desc');
        if ($status === 'active') {
            $now = date('Y-m-d H:i:s');
            $query->where('start_at', '<=', $now)->where('end_at', '>=', $now)->where('status', 1);
        } elseif ($status === 'upcoming') {
            $query->where('start_at', '>', date('Y-m-d H:i:s'))->where('status', 1);
        } elseif ($status === 'ended') {
            $query->where('end_at', '<', date('Y-m-d H:i:s'));
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items = [];
        foreach ($paginator->items() as $t) {
            $items[] = [
                'id' => $this->encodeId($t->id), 'name' => $t->name, 'slug' => $t->slug,
                'type' => $t->type, 'description' => $t->description,
                'game' => $t->game ? ['id' => $this->encodeId($t->game->id), 'name' => $t->game->name] : null,
                'prize_pool' => $t->prize_pool, 'entry_fee' => $t->entry_fee,
                'player_count' => $t->entries_count, 'max_players' => $t->max_players,
                'start_at' => $t->start_at, 'end_at' => $t->end_at,
            ];
        }
        return $this->success(['items' => $items, 'total' => $paginator->total(), 'page' => $page, 'last_page' => $paginator->lastPage()]);
    }

    /**
     * @Apidoc\Title("赛事详情")
     * @Apidoc\Url("/api/tournament/{hashid}")
     * @Apidoc\Method("GET")
     */
    public function detail(Request $request, string $hashid): Response
    {
        $t = Tournament::with(['game', 'entries' => function($q) { $q->orderBy('score', 'desc')->limit(100); }])
            ->withCount('entries')
            ->find($this->decodeId($hashid));
        if (!$t) return $this->fail('Tournament not found', 404);

        $myEntry = TournamentEntry::where('tournament_id', $t->id)->where('user_id', $request->userId)->first();

        $leaderboard = [];
        foreach ($t->entries as $e) {
            $leaderboard[] = ['rank' => $e->rank, 'user' => $e->user ? $e->user->nickname : 'N/A', 'score' => $e->score];
        }

        return $this->success([
            'id' => $this->encodeId($t->id), 'name' => $t->name, 'slug' => $t->slug,
            'type' => $t->type, 'description' => $t->description,
            'game' => $t->game ? ['id' => $this->encodeId($t->game->id), 'name' => $t->game->name] : null,
            'prize_pool' => $t->prize_pool, 'entry_fee' => $t->entry_fee,
            'player_count' => $t->entries_count, 'max_players' => $t->max_players,
            'start_at' => $t->start_at, 'end_at' => $t->end_at,
            'my_entry' => $myEntry ? ['id' => $this->encodeId($myEntry->id), 'score' => $myEntry->score, 'rank' => $myEntry->rank] : null,
            'leaderboard' => $leaderboard,
        ]);
    }

    /**
     * @Apidoc\Title("报名参赛")
     * @Apidoc\Url("/api/tournament/{hashid}/join")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     */
    public function join(Request $request, string $hashid): Response
    {
        $t = Tournament::find($this->decodeId($hashid));
        if (!$t || (int) $t->status !== 1) return $this->fail('Tournament not available', 404);
        if ($t->start_at <= date('Y-m-d H:i:s')) return $this->fail('Tournament has already started', 400);

        $existing = TournamentEntry::where('tournament_id', $t->id)->where('user_id', $request->userId)->first();
        if ($existing) return $this->fail('Already entered', 422);

        if ($t->max_players > 0 && $t->entries()->count() >= $t->max_players) {
            return $this->fail('Tournament is full', 400);
        }

        $entry = new TournamentEntry();
        $entry->id = $this->generateId();
        $entry->tournament_id = $t->id;
        $entry->user_id = $request->userId;
        $entry->score = '0';
        $entry->rank = 0;
        $entry->created_at = date('Y-m-d H:i:s');
        $entry->save();

        return $this->success(['id' => $this->encodeId($entry->id)], 'Entry confirmed');
    }
}
