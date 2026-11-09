<?php

namespace App\Services\Recommendations;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Picks study groups and listings for one student: the ML ranking when the service is
 * available, otherwise the SQL ordering (which is also the baseline in the evaluation).
 */
class RecommendationService
{
    private const MAX_CANDIDATES = 200;

    public function __construct(private readonly RecommenderClient $client) {}

    /**
     * Open groups the student could join, best first.
     *
     * @return Collection<int, StudyGroup>
     */
    public function groups(User $user, int $limit = 20): Collection
    {
        $courseIds = $user->courses()->pluck('courses.id')->map(fn ($id): int => (int) $id)->all();

        $candidates = StudyGroup::query()
            ->with([
                'course',
                'creator',
                'members' => fn ($members) => $members->select('users.id', 'users.major', 'users.study_year'),
                'members.courses' => fn ($courses) => $courses->select('courses.id'),
            ])
            ->withMembership($user)
            ->upcoming()
            ->whereDoesntHave('members', fn ($members) => $members->whereKey($user->id))
            ->whereRaw('(select count(*) from study_group_user where study_group_id = study_groups.id) < capacity')
            ->orderBy('starts_at')
            ->limit(self::MAX_CANDIDATES)
            ->get();

        $ranked = $this->client->rank(
            'groups',
            $this->groupProfile($user, $courseIds),
            $candidates->map(fn (StudyGroup $group): array => [
                'id' => $group->id,
                'course_id' => $group->course_id,
                'starts_at' => $group->starts_at->getTimestamp(),
                'capacity' => $group->capacity,
                'members' => $group->members->map(fn (User $member): array => [
                    'id' => $member->id,
                    'major' => $member->major,
                    'study_year' => $member->study_year,
                    'course_ids' => $member->courses->modelKeys(),
                ])->values()->all(),
            ])->values()->all(),
            now()->getTimestamp(),
        );

        $ordered = $ranked === null
            ? $this->sqlGroupOrder($candidates, $courseIds, $user)
            : $this->inOrder($candidates, $ranked);

        return $ordered->take($limit)->each(fn (StudyGroup $group) => $group->unsetRelation('members'))->values();
    }

    /**
     * Available listings from other students, best first.
     *
     * @return Collection<int, Listing>
     */
    public function listings(User $user, int $limit = 10): Collection
    {
        $courses = $user->courses()->get(['courses.id', 'courses.name']);
        $courseIds = $courses->modelKeys();

        $candidates = Listing::query()
            ->with(['seller', 'course'])
            ->where('status', ListingStatus::Available)
            ->where('seller_id', '!=', $user->id)
            ->latest()
            ->limit(self::MAX_CANDIDATES)
            ->get();

        $purchases = Listing::query()
            ->where('buyer_id', $user->id)
            ->where('status', ListingStatus::Sold)
            ->get(['title', 'category', 'price'])
            ->map(fn (Listing $listing): array => [
                'title' => $listing->title,
                'category' => $listing->category->value,
                'price' => (float) $listing->price,
            ])
            ->values()
            ->all();

        $ranked = $this->client->rank(
            'listings',
            [
                'id' => $user->id,
                'course_ids' => $courseIds,
                'course_names' => $courses->pluck('name')->all(),
                'purchases' => $purchases,
                'co_member_ids' => array_keys($this->coMembers($user)),
            ],
            $candidates->map(fn (Listing $listing): array => [
                'id' => $listing->id,
                'title' => $listing->title,
                'description' => $listing->description,
                'category' => $listing->category->value,
                'course_id' => $listing->course_id,
                'price' => (float) $listing->price,
                'seller_id' => $listing->seller_id,
                'created_at' => $listing->created_at->getTimestamp(),
            ])->values()->all(),
            now()->getTimestamp(),
        );

        // Fallback: listings for my courses first, then the newest.
        $ordered = $ranked === null
            ? $candidates->sortBy(fn (Listing $listing): int => in_array($listing->course_id, $courseIds, true) ? 0 : 1)->values()
            : $this->inOrder($candidates, $ranked);

        return $ordered->take($limit)->values();
    }

    /**
     * Whether the last ranking came from the model (for the API response).
     */
    public function usesModel(): bool
    {
        return $this->client->enabled();
    }

    /**
     * @param  list<int>  $courseIds
     * @return array{id: int, major: ?string, study_year: ?int, course_ids: list<int>, co_members: array<int, int>, past_join_hours: list<int>}
     */
    private function groupProfile(User $user, array $courseIds): array
    {
        $hours = StudyGroup::query()
            ->whereHas('members', fn ($members) => $members->whereKey($user->id))
            ->pluck('starts_at')
            ->map(fn ($startsAt): int => (int) $startsAt->format('G'))
            ->values()
            ->all();

        return [
            'id' => $user->id,
            'major' => $user->major,
            'study_year' => $user->study_year,
            'course_ids' => $courseIds,
            'co_members' => (object) $this->coMembers($user),
            'past_join_hours' => $hours,
        ];
    }

    /**
     * Students this student has shared study groups with, and how many.
     *
     * @return array<int, int>
     */
    private function coMembers(User $user): array
    {
        return DB::table('study_group_user as mine')
            ->join('study_group_user as theirs', 'theirs.study_group_id', '=', 'mine.study_group_id')
            ->where('mine.user_id', $user->id)
            ->where('theirs.user_id', '!=', $user->id)
            ->groupBy('theirs.user_id')
            ->selectRaw('theirs.user_id as user_id, count(*) as shared')
            ->pluck('shared', 'user_id')
            ->mapWithKeys(fn ($shared, $userId): array => [(int) $userId => (int) $shared])
            ->all();
    }

    /**
     * Today's rule: groups in my courses, most classmates first, then the soonest.
     *
     * @param  Collection<int, StudyGroup>  $candidates
     * @param  list<int>  $courseIds
     * @return Collection<int, StudyGroup>
     */
    private function sqlGroupOrder(Collection $candidates, array $courseIds, User $user): Collection
    {
        return $candidates
            ->filter(fn (StudyGroup $group): bool => in_array($group->course_id, $courseIds, true))
            ->sortBy([
                fn (StudyGroup $a, StudyGroup $b): int => $this->classmates($b, $courseIds, $user) <=> $this->classmates($a, $courseIds, $user),
                fn (StudyGroup $a, StudyGroup $b): int => $a->starts_at <=> $b->starts_at,
            ])
            ->values();
    }

    /**
     * @param  list<int>  $courseIds
     */
    private function classmates(StudyGroup $group, array $courseIds, User $user): int
    {
        return $group->members
            ->filter(fn (User $member): bool => $member->id !== $user->id && array_intersect($member->courses->modelKeys(), $courseIds) !== [])
            ->count();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $items
     * @param  list<int>  $ids
     * @return Collection<int, TModel>
     */
    private function inOrder(Collection $items, array $ids): Collection
    {
        $byId = $items->keyBy('id');

        return new Collection(array_values(array_filter(array_map(fn (int $id) => $byId->get($id), $ids))));
    }
}
