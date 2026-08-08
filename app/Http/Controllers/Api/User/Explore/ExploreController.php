<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Api\User\Explore;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Database\Configs\Table;
use App\Http\Controllers\Controller;
use App\Services\Timeline\FeedService;
use Illuminate\Support\Facades\DB;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\People\PeopleCollection;
use App\Http\Resources\User\Timeline\TimelineCollection;
use App\Traits\Http\Controllers\Api\User\Explore\ValidatesPeopleFilters;

class ExploreController extends Controller
{
    use SupportsApiResponses,
        ValidatesPeopleFilters;

    private $filter = [];
    private $me = null;

    public function __construct()
    {
        $this->me = me();
    }

    public function getPeople(Request $request)
    {
        $filterOptions = $this->getValidatedFilters($request);
        $page = (! empty($filterOptions['page']) ? (int) $filterOptions['page'] : 1);
        $searchQuery = trim((string) data_get($filterOptions, 'query', ''));

        $peopleQuery = User::active()->onboarded()->excludeSelf()->whereNotIn('id', function($query) {
            $query->select('blocked_id')->from(Table::BLOCKS)->where('blocker_id', me()->id);
        })->whereNotIn('id', function($query) {
            $query->select('blocker_id')->from(Table::BLOCKS)->where('blocked_id', me()->id);
        });

        if($searchQuery !== '') {
            $this->applyPeopleSearchFilters($peopleQuery, $searchQuery);
        } else {
            $peopleQuery->whereNotIn('id', function ($query) {
                $query->select('following_id')->from(Table::FOLLOWS)->where('follower_id', me()->id);
            })->author()
                ->orderByDesc('followers_count')
                ->orderByDesc('publications_count');
        }

        $people = $peopleQuery->simplePaginateManual(30, $page);

        return $this->responseSuccess([
            'data' => PeopleCollection::make($people->items())
        ]);
    }

    public function getPosts(Request $request)
    {
        $filter = $request->array('filter');

        $this->filter['page'] = data_get_integer($filter, 'page', 1);
        $this->filter['onset'] = data_get_integer($filter, 'onset', 0);
        $this->filter['query'] = trim(strval(data_get($filter, 'query', '')));
        $this->filter['session_id'] = substr(trim(strval(data_get($filter, 'session_id', ''))), 0, 80);
        $this->filter['refresh_reason'] = substr(trim(strval(data_get($filter, 'refresh_reason', ''))), 0, 32);

        if($this->filter['query'] === '') {
            $feedResult = app(FeedService::class)->getFeed($this->me, [
                'page' => $this->filter['page'],
                'onset' => $this->filter['onset'],
                'type' => FeedService::TYPE_FOR_YOU,
                'session_id' => $this->filter['session_id'],
                'refresh_reason' => $this->filter['refresh_reason'],
            ]);

            return $this->responseSuccess([
                'data' => TimelineCollection::make($feedResult->posts),
                'meta' => $feedResult->meta,
            ]);
        }

        $feedORMQuery = Post::timelineFormatPosts()
            ->when(! empty($this->filter['onset']), function($query) {
                $query->where('id', '>', $this->filter['onset']);
            })->when((! $this->me->isRoot()), function($query) {
                $query->where(function($query) {
                    $query->where('user_id', $this->me->id)->orWhereHas('user', function($u) {
                        $u->author()->active();
                    });
                });
            })->unless(empty($this->filter['query']), function($query) {
                $query->where(function($query) {
                    $query->whereLike('content', "%{$this->filter['query']}%")
                        ->orWhereHas('user', function($userQuery) {
                            $userQuery->whereLike('username', "%{$this->filter['query']}%")
                                ->orWhereLike('first_name', "%{$this->filter['query']}%")
                                ->orWhereLike('last_name', "%{$this->filter['query']}%")
                                ->orWhereLike('caption', "%{$this->filter['query']}%");
                        });
                });
            })
            ->whereNotIn('user_id', function($query) {
                $query->select('blocked_id')->from(Table::BLOCKS)->where('blocker_id', me()->id);
            })->whereNotIn('user_id', function($query) {
                $query->select('blocker_id')->from(Table::BLOCKS)->where('blocked_id', me()->id);
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('comments_count', 'desc')
            ->orderBy('bookmarks_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->orderBy('quotes_count', 'desc');

        $timelinePosts = $feedORMQuery->simplePaginateManual(config('post.paginate_per'), $this->filter['page']);

        return $this->responseSuccess([
            'data' => TimelineCollection::make($timelinePosts)
        ]);
    }

    private function applyPeopleSearchFilters(Builder $query, string $searchQuery): void
    {
        $normalizedQuery = $this->normalizePeopleSearchQuery($searchQuery);
        $searchTerms = $this->extractPeopleSearchTerms($normalizedQuery);
        $rawLike = $this->makeLikePattern($searchQuery);
        $normalizedLike = $this->makeLikePattern($normalizedQuery);
        $documentExpression = $this->peopleSearchDocumentExpression();
        $displayNameExpression = $this->peopleDisplayNameExpression();

        $query->where(function(Builder $searchBuilder) use ($searchQuery, $rawLike, $normalizedLike, $normalizedQuery, $searchTerms, $documentExpression, $displayNameExpression) {
            $searchBuilder->whereLike('username', $rawLike)
                ->orWhereLike('first_name', $rawLike)
                ->orWhereLike('last_name', $rawLike)
                ->orWhereLike('city', $rawLike)
                ->orWhereLike('caption', $rawLike)
                ->orWhereLike('bio', $rawLike)
                ->orWhereRaw("{$displayNameExpression} LIKE ?", [$normalizedLike])
                ->orWhereRaw("{$documentExpression} LIKE ?", [$normalizedLike]);

            if(is_numeric($searchQuery)) {
                $searchBuilder->orWhereRaw($this->peopleIdentifierExpression() . ' LIKE ?', [$this->makeLikePattern($searchQuery)]);
            }

            if(count($searchTerms) > 1) {
                $searchBuilder->orWhere(function(Builder $tokenBuilder) use ($searchTerms, $documentExpression) {
                    foreach($searchTerms as $term) {
                        $tokenBuilder->whereRaw("{$documentExpression} LIKE ?", [$this->makeLikePattern($term)]);
                    }
                });
            }
        });

        $rankingBindings = [
            mb_strtolower($searchQuery),
            $normalizedQuery,
            $this->makePrefixPattern(mb_strtolower($searchQuery)),
            $this->makePrefixPattern($normalizedQuery),
            $this->makePrefixPattern($normalizedQuery),
        ];

        $query->orderByRaw(
            "CASE
                WHEN LOWER(username) = ? THEN 0
                WHEN {$displayNameExpression} = ? THEN 1
                WHEN LOWER(username) LIKE ? THEN 2
                WHEN {$displayNameExpression} LIKE ? THEN 3
                WHEN {$documentExpression} LIKE ? THEN 4
                ELSE 5
            END",
            $rankingBindings
        );

        if(count($searchTerms) > 1) {
            $termScoreSql = implode(' + ', array_fill(0, count($searchTerms), "CASE WHEN {$documentExpression} LIKE ? THEN 1 ELSE 0 END"));

            $query->orderByRaw(
                "({$termScoreSql}) DESC",
                array_map(fn(string $term) => $this->makeLikePattern($term), $searchTerms)
            );
        }

        $query->orderByDesc('followers_count')
            ->orderByDesc('publications_count');
    }

    private function normalizePeopleSearchQuery(string $query): string
    {
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim($query)));
        $normalized = preg_replace('/\s+/u', ' ', (string) $normalized);

        return trim((string) $normalized);
    }

    private function extractPeopleSearchTerms(string $normalizedQuery): array
    {
        if($normalizedQuery === '') {
            return [];
        }

        return array_values(array_filter(array_unique(explode(' ', $normalizedQuery))));
    }

    private function peopleSearchDocumentExpression(): string
    {
        return $this->normalizedSqlTextExpression([
            'first_name',
            'last_name',
            'username',
            'caption',
            'city',
            'bio',
        ]);
    }

    private function peopleDisplayNameExpression(): string
    {
        return $this->normalizedSqlTextExpression([
            'first_name',
            'last_name',
        ]);
    }

    private function peopleIdentifierExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => 'CAST(id AS TEXT)',
            'pgsql' => 'CAST(id AS TEXT)',
            default => 'CAST(id AS CHAR)',
        };
    }

    private function normalizedSqlTextExpression(array $columns): string
    {
        $expression = match (DB::connection()->getDriverName()) {
            'sqlite' => implode(" || ' ' || ", array_map(fn(string $column) => "COALESCE({$column}, '')", $columns)),
            'pgsql' => implode(" || ' ' || ", array_map(fn(string $column) => "COALESCE({$column}, '')", $columns)),
            default => "CONCAT_WS(' ', " . implode(', ', array_map(fn(string $column) => "COALESCE({$column}, '')", $columns)) . ')',
        };

        $expression = "LOWER({$expression})";

        foreach(['.', '-', '_', '@'] as $character) {
            $expression = "REPLACE({$expression}, '{$character}', ' ')";
        }

        for($iteration = 0; $iteration < 3; $iteration++) {
            $expression = "REPLACE({$expression}, '  ', ' ')";
        }

        return "TRIM({$expression})";
    }

    private function makeLikePattern(string $value): string
    {
        return '%' . addcslashes($value, '\\%_') . '%';
    }

    private function makePrefixPattern(string $value): string
    {
        return addcslashes($value, '\\%_') . '%';
    }
}
