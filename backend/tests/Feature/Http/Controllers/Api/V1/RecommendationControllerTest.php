<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\Listing;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class RecommendationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['major' => 'CS', 'study_year' => 2]);
        $this->course = Course::factory()->create(['name' => 'Database Systems']);
        $this->student->courses()->attach($this->course);
    }

    private function enableModel(): void
    {
        config(['services.recommender.enabled' => true, 'services.recommender.url' => 'http://ml.test', 'services.recommender.token' => null]);
    }

    /**
     * Fake the service so it ranks candidates in the given order (unknown ids ignored).
     *
     * @param  callable(list<int>): list<int>  $order
     */
    private function fakeRanking(callable $order): void
    {
        Http::fake([
            'ml.test/recommend/*' => fn (Request $request) => Http::response([
                'recommender_version' => 'rec-lr-1',
                'ranked' => array_map(fn (int $id): array => ['id' => $id, 'score' => 0.5], $order(array_column($request['candidates'], 'id'))),
            ]),
        ]);
    }

    private function feed(): TestResponse
    {
        return $this->withToken(JWTAuth::fromUser($this->student))->getJson(route('api.v1.recommendations'));
    }

    public function test_requires_a_token(): void
    {
        $this->getJson(route('api.v1.recommendations'))->assertUnauthorized();
    }

    public function test_without_the_model_it_uses_the_rules(): void
    {
        Http::fake();
        $mine = StudyGroup::factory()->create(['course_id' => $this->course->id]);
        StudyGroup::factory()->create();
        $other = Listing::factory()->create();
        $forMyCourse = Listing::factory()->create(['course_id' => $this->course->id, 'created_at' => now()->subWeek()]);
        Listing::factory()->create(['seller_id' => $this->student->id]);

        $this->feed()
            ->assertOk()
            ->assertJsonPath('data.source', 'rules')
            ->assertJsonCount(1, 'data.groups')
            ->assertJsonPath('data.groups.0.id', $mine->id)
            ->assertJsonCount(2, 'data.listings')
            ->assertJsonPath('data.listings.0.id', $forMyCourse->id)
            ->assertJsonPath('data.listings.1.id', $other->id);

        Http::assertNothingSent();
    }

    public function test_the_model_order_is_used_and_can_surface_a_group_outside_my_courses(): void
    {
        $this->enableModel();
        $this->fakeRanking(fn (array $ids): array => array_reverse($ids));
        $mine = StudyGroup::factory()->create(['course_id' => $this->course->id, 'starts_at' => now()->addDay()]);
        $friends = StudyGroup::factory()->create(['starts_at' => now()->addDays(2)]);

        $this->feed()
            ->assertOk()
            ->assertJsonPath('data.source', 'model')
            ->assertJsonPath('data.groups.0.id', $friends->id)
            ->assertJsonPath('data.groups.1.id', $mine->id);
    }

    public function test_the_service_receives_the_profile_and_candidates(): void
    {
        $this->enableModel();
        $this->fakeRanking(fn (array $ids): array => $ids);

        $partner = User::factory()->create(['major' => 'CS', 'study_year' => 2]);
        $partner->courses()->attach($this->course);
        $past = StudyGroup::factory()->past()->create(['course_id' => $this->course->id, 'creator_id' => $partner->id]);
        $past->members()->attach($this->student);

        $open = StudyGroup::factory()->create(['course_id' => $this->course->id]);
        $open->members()->attach($partner);
        Listing::factory()->soldTo($this->student)->create(['title' => 'SQL notes', 'category' => 'notes', 'price' => 8]);
        $listing = Listing::factory()->create(['course_id' => $this->course->id, 'seller_id' => $partner->id]);

        $this->feed()->assertOk();

        Http::assertSent(function (Request $request) use ($partner, $open): bool {
            if (! str_ends_with($request->url(), '/recommend/groups')) {
                return false;
            }
            $group = collect($request['candidates'])->firstWhere('id', $open->id);
            $partnerAsMember = collect($group['members'])->firstWhere('id', $partner->id);

            return $request['user']['course_ids'] === [$this->course->id]
                && $request['user']['major'] === 'CS'
                && (array) $request['user']['co_members'] === [$partner->id => 1]
                && count($request['user']['past_join_hours']) === 1
                && $partnerAsMember['course_ids'] === [$this->course->id];
        });

        Http::assertSent(function (Request $request) use ($partner, $listing): bool {
            return str_ends_with($request->url(), '/recommend/listings')
                && $request['user']['course_names'] === ['Database Systems']
                && $request['user']['purchases'] === [['title' => 'SQL notes', 'category' => 'notes', 'price' => 8.0]]
                && $request['user']['co_member_ids'] === [$partner->id]
                && collect($request['candidates'])->firstWhere('id', $listing->id)['seller_id'] === $partner->id;
        });
    }

    public function test_when_the_service_is_down_the_rules_decide_and_it_is_not_retried_for_a_minute(): void
    {
        $this->enableModel();
        Http::fake(['ml.test/*' => Http::response('boom', 500)]);
        $mine = StudyGroup::factory()->create(['course_id' => $this->course->id]);
        StudyGroup::factory()->create();

        $this->feed()->assertOk()->assertJsonCount(1, 'data.groups')->assertJsonPath('data.groups.0.id', $mine->id);
        $this->feed()->assertOk();

        Http::assertSentCount(1);
    }

    public function test_the_study_group_tab_uses_the_same_ranking(): void
    {
        $this->enableModel();
        $this->fakeRanking(fn (array $ids): array => array_reverse($ids));
        $first = StudyGroup::factory()->create(['course_id' => $this->course->id, 'starts_at' => now()->addDay()]);
        $second = StudyGroup::factory()->create(['course_id' => $this->course->id, 'starts_at' => now()->addDays(2)]);

        $this->withToken(JWTAuth::fromUser($this->student))
            ->getJson(route('api.v1.study-groups.recommended'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);
    }
}
