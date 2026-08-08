<?php

namespace Tests\Feature;

use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExplorePeopleSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_search_matches_full_name_partial_terms_username_and_numeric_id(): void
    {
        $viewer = $this->createUser('people-viewer', UserType::READER, [
            'first_name' => 'Viewer',
            'last_name' => 'User',
        ]);

        $target = $this->createUser('shishirchowdhury808', UserType::AUTHOR, [
            'first_name' => 'Md Shishir C.',
            'last_name' => 'Chowdhury',
            'caption' => 'Creator from Chittagong',
            'bio' => 'Writes about lifestyle and travel.',
        ]);

        $this->assertSearchContains($viewer, 'Md Shishir C. Chowdhury', $target->id);
        $this->assertSearchContains($viewer, 'Shishir Chow', $target->id);
        $this->assertSearchContains($viewer, 'shishirchow', $target->id);
        $this->assertSearchContains($viewer, (string) $target->id, $target->id);
    }

    public function test_people_search_includes_active_reader_accounts_when_query_matches(): void
    {
        $viewer = $this->createUser('reader-search-viewer', UserType::READER, [
            'first_name' => 'Reader',
            'last_name' => 'Viewer',
        ]);

        $readerTarget = $this->createUser('shishirchowdhury808', UserType::READER, [
            'first_name' => 'Md Shishir C.',
            'last_name' => 'Chowdhury',
            'caption' => 'Reader profile',
        ]);

        $this->assertSearchContains($viewer, 'shishirchowdhury808', $readerTarget->id);
        $this->assertSearchContains($viewer, 'Md Shishir C. Chowdhury', $readerTarget->id);
        $this->assertSearchContains($viewer, 'Shishir Chow', $readerTarget->id);
    }

    public function test_people_search_still_returns_matching_followed_accounts(): void
    {
        $viewer = $this->createUser('followed-search-viewer', UserType::READER, [
            'first_name' => 'Followed',
            'last_name' => 'Viewer',
        ]);

        $followedTarget = $this->createUser('shishirfollowed808', UserType::READER, [
            'first_name' => 'Shishir',
            'last_name' => 'Chowdhury',
        ]);

        Follow::query()->create([
            'follower_id' => $viewer->id,
            'following_id' => $followedTarget->id,
            'status' => true,
        ]);

        $this->assertSearchContains($viewer, 'shishirfollowed808', $followedTarget->id);
        $this->assertSearchContains($viewer, 'Shishir Chowdhury', $followedTarget->id);
    }

    public function test_people_search_prioritizes_exact_full_name_match_over_loose_related_matches(): void
    {
        $viewer = $this->createUser('priority-viewer', UserType::READER, [
            'first_name' => 'Priority',
            'last_name' => 'Viewer',
        ]);

        $target = $this->createUser('shishir-target', UserType::AUTHOR, [
            'first_name' => 'Md Shishir C.',
            'last_name' => 'Chowdhury',
            'followers_count' => 2,
            'publications_count' => 1,
        ]);

        $highFollowerLooseMatch = $this->createUser('shishir-fan-club', UserType::AUTHOR, [
            'first_name' => 'Md Shishir C Rahman',
            'last_name' => 'Chowdhury',
            'followers_count' => 500,
            'publications_count' => 300,
        ]);

        $response = $this->searchPeople($viewer, 'Md Shishir C. Chowdhury');
        $peopleIds = array_column($response->json('data'), 'id');

        $this->assertNotFalse(array_search($target->id, $peopleIds, true));
        $this->assertNotFalse(array_search($highFollowerLooseMatch->id, $peopleIds, true));
        $this->assertSame($target->id, $peopleIds[0]);
    }

    private function assertSearchContains(User $viewer, string $query, int $expectedUserId): void
    {
        $response = $this->searchPeople($viewer, $query);
        $peopleIds = array_column($response->json('data'), 'id');

        $this->assertContains($expectedUserId, $peopleIds, "Failed asserting that [{$query}] returns user [{$expectedUserId}].");
    }

    private function searchPeople(User $viewer, string $query)
    {
        return $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/explore/people', [
                'filter' => [
                    'page' => 1,
                    'query' => $query,
                ],
            ])
            ->assertOk();
    }

    private function createUser(string $username, UserType $type = UserType::AUTHOR, array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => $username,
            'caption' => '@' . $username,
            'email' => "{$username}@example.test",
            'phone' => '',
            'website' => '',
            'bio' => '',
            'country' => null,
            'city' => null,
            'birth_day' => null,
            'birth_month' => null,
            'birth_year' => null,
            'age' => null,
            'gender' => 'male',
            'last_active' => now()->timestamp,
            'language' => 'en',
            'avatar' => null,
            'cover' => null,
            'verified' => false,
            'tips' => [],
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::USER,
            'theme' => 'light',
            'publications_count' => 0,
            'followers_count' => 0,
            'following_count' => 0,
            'status' => UserStatus::ACTIVE,
            'type' => $type,
        ], $overrides));
    }
}
