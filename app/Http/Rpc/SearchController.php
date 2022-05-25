<?php declare(strict_types=1);
namespace App\Http\Rpc;
use App\Rpc\RpcError;
use App\Support\RpcConstants;
use App\Support\Presenters\SearchResultPresenter;

class SearchController
{
    public function performSearch(array $params): array
    {
        $query = $params['search_query'] ??
            throw new RpcError(RpcConstants::ERROR_INVALID_PARAMS,
                "No search_query provided.");

        // TODO[pn]: This is a hack to make multiple words work but as any hack
        // it works only barely. Notably, anything "quoted" will break this
        // horribly.
        $query = implode(' & ', explode(' ', $query));

        // TODO[pn]: This uses the English normalization and so is kinda stupid.
        // TODO?[pn]: Postgres doesn't pay attention to partial matches...
        $searchResults = app('db')->select(<<<SQL
            select
                id,
                ts_headline(content, to_tsquery(:query),
                    'StartSel=<<<, StopSel=>>>') as headline
            from files_fulltext
            where to_tsvector('english', content) @@ to_tsquery(:query)
            order by ts_rank(to_tsvector('english', content), to_tsquery(:query)) desc
        SQL, [':query' => $query]);

        $presenter = new SearchResultPresenter();
        return array_map(fn($x) => $presenter->present($x), $searchResults);
    }
}
