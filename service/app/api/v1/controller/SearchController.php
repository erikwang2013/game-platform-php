<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Game;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Search")
 * @Apidoc\Group("search")
 */
class SearchController extends BaseController
{
    /**
     * @Apidoc\Title("Search")
     * @Apidoc\Url("/api/search")
     * @Apidoc\Method("GET")
     * @Apidoc\Query(name:"q",type:"string",require:true,desc:"Search keyword")
     * @Apidoc\Query(name:"type",type:"string",require:false,desc:"Search type (game)")
     * @Apidoc\Query(name:"page",type:"integer",require:false,desc:"Page number")
     * @Apidoc\Query(name:"per_page",type:"integer",require:false,desc:"Items per page")
     */
    public function search(Request $request): Response
    {
        $keyword = trim($request->input('q', ''));
        $type    = $request->input('type', 'game');
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        if (empty($keyword)) {
            return $this->fail('Search keyword is required', 422);
        }

        if (mb_strlen($keyword) < 2) {
            return $this->fail('Search keyword must be at least 2 characters', 422);
        }

        if ($type !== 'game') {
            return $this->fail('Unsupported search type. Supported: game', 422);
        }

        // Try Elasticsearch via Laravel Scout
        try {
            $paginator = Game::search($keyword)
                ->query(function ($query) {
                    $query->where('status', 1);
                })
                ->paginate($perPage, 'page', $page);

            $items = [];
            foreach ($paginator->items() as $game) {
                $items[] = $this->formatGameItem($game);
            }

            return $this->success([
                'items'     => $items,
                'total'     => $paginator->total(),
                'page'      => $paginator->currentPage(),
                'per_page'  => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'engine'    => 'elasticsearch',
            ]);
        } catch (\Throwable $e) {
            // Fallback to MySQL LIKE query
            $query = Game::where('status', 1)
                ->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', '%' . $keyword . '%')
                      ->orWhere('description', 'like', '%' . $keyword . '%')
                      ->orWhere('type', 'like', '%' . $keyword . '%');
                })
                ->orderBy('sort', 'asc')
                ->orderBy('id', 'desc');

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            $items = [];
            foreach ($paginator->items() as $game) {
                $items[] = $this->formatGameItem($game);
            }

            return $this->success([
                'items'     => $items,
                'total'     => $paginator->total(),
                'page'      => $paginator->currentPage(),
                'per_page'  => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'engine'    => 'mysql',
            ]);
        }
    }

    /**
     * Format a game record for the search response.
     */
    private function formatGameItem(Game $game): array
    {
        return [
            'id'          => $this->encodeId($game->id),
            'name'        => $game->name,
            'slug'        => $game->slug,
            'type'        => $game->type,
            'description' => $game->description,
            'cover_image' => $game->cover_image,
        ];
    }
}
