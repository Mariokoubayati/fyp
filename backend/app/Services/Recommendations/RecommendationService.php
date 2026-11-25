<?php

namespace App\Services\Recommendations;

use App\Enums\ListingStatus;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Project;
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

    /**
     * True once any ranking in this request fell back to the SQL ordering.
     */
    private bool $fellBackToRules = false;

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

        $this->fellBackToRules = $this->fellBackToRules || $ranked === null;

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
            ->with(['seller.badges', 'course'])
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

        $this->fellBackToRules = $this->fellBackToRules || $ranked === null;

        // Fallback: listings for my courses first, then the newest.
        $ordered = $ranked === null
            ? $candidates->sortBy(fn (Listing $listing): int => in_array($listing->course_id, $courseIds, true) ? 0 : 1)->values()
            : $this->inOrder($candidates, $ranked);

        return $ordered->take($limit)->values();
    }

    /**
     * Whether the last ranking came from the model (for the API response).
     */
    /**
     * Upcoming events for the student, ranked by the recommender when it is enabled.
     * Fallback: events matching the student's interests first, then events for their courses, then the soonest.
     *
     * @return Collection<int, Event>
     */
    public function events(User $user, int $limit = 20): Collection
    {
        $courseIds = $user->courses()->pluck('courses.id')->map(fn ($id): int => (int) $id)->all();
        $interests = $user->interests ?? [];

        $candidates = Event::query()
            ->with([
                'course',
                'creator',
                'attendees' => fn ($attendees) => $attendees->select('users.id', 'users.major'),
            ])
            ->withAttendance($user)
            ->upcoming()
            ->where('creator_id', '!=', $user->id)
            ->whereDoesntHave('attendees', fn ($attendees) => $attendees->whereKey($user->id))
            ->whereRaw('(capacity is null or (select count(*) from event_user where event_id = events.id) < capacity)')
            ->orderBy('starts_at')
            ->limit(self::MAX_CANDIDATES)
            ->get();

        $past = Event::query()
            ->whereHas('attendees', fn ($attendees) => $attendees->whereKey($user->id))
            ->where('creator_id', '!=', $user->id)
            ->get(['tags', 'starts_at']);

        $ranked = $this->client->rank(
            'events',
            [
                'id' => $user->id,
                'major' => $user->major,
                'interests' => $interests,
                'course_ids' => $courseIds,
                'co_members' => (object) $this->campusPeers($user),
                'past_event_tags' => $past->flatMap(fn (Event $event) => $event->tags->map->value)->values()->all(),
                'past_event_count' => $past->count(),
                'past_event_hours' => $past->map(fn (Event $event): int => (int) $event->starts_at->format('G'))->values()->all(),
            ],
            $candidates->map(fn (Event $event): array => [
                'id' => $event->id,
                'tags' => $event->tags->map->value->values()->all(),
                'course_id' => $event->course_id,
                'starts_at' => $event->starts_at->getTimestamp(),
                'capacity' => $event->capacity,
                'attendees' => $event->attendees->map(fn (User $attendee): array => [
                    'id' => $attendee->id,
                    'major' => $attendee->major,
                ])->values()->all(),
            ])->values()->all(),
            now()->getTimestamp(),
        );

        $this->fellBackToRules = $this->fellBackToRules || $ranked === null;

        $ordered = $ranked === null
            ? $candidates->sortBy([
                fn (Event $a, Event $b): int => $this->tagOverlap($b->tags->map->value->all(), $interests) <=> $this->tagOverlap($a->tags->map->value->all(), $interests),
                fn (Event $a, Event $b): int => (int) in_array($b->course_id, $courseIds, true) <=> (int) in_array($a->course_id, $courseIds, true),
                fn (Event $a, Event $b): int => $a->starts_at <=> $b->starts_at,
            ])->values()
            : $this->inOrder($candidates, $ranked);

        return $ordered->take($limit)->each(fn (Event $event) => $event->unsetRelation('attendees'))->values();
    }

    /**
     * Open projects for the student, ranked by the recommender when it is enabled.
     * Fallback: projects that need the student's skills first, then matching interests, then their courses, then the newest.
     *
     * @return Collection<int, Project>
     */
    public function projects(User $user, int $limit = 20): Collection
    {
        $courseIds = $user->courses()->pluck('courses.id')->map(fn ($id): int => (int) $id)->all();
        $interests = $user->interests ?? [];
        $skills = $user->skills ?? [];

        $candidates = Project::query()
            ->with(['course', 'owner', 'members' => fn ($members) => $members->select('users.id')])
            ->withMembership($user)
            ->where('is_open', true)
            ->where('owner_id', '!=', $user->id)
            ->whereDoesntHave('people', fn ($people) => $people->whereKey($user->id))
            ->whereRaw("(select count(*) from project_user where project_id = projects.id and status = 'accepted') < team_size")
            ->latest()
            ->limit(self::MAX_CANDIDATES)
            ->get();

        $ranked = $this->client->rank(
            'projects',
            [
                'id' => $user->id,
                'major' => $user->major,
                'interests' => $interests,
                'skills' => $skills,
                'course_ids' => $courseIds,
                'co_members' => (object) $this->campusPeers($user),
            ],
            $candidates->map(fn (Project $project): array => [
                'id' => $project->id,
                'owner_id' => $project->owner_id,
                'owner_major' => $project->owner->major,
                'tags' => $project->tags->map->value->values()->all(),
                'skills_needed' => $project->skills_needed,
                'course_id' => $project->course_id,
                'team_size' => $project->team_size,
                'member_ids' => $project->members->modelKeys(),
                'created_at' => $project->created_at->getTimestamp(),
            ])->values()->all(),
            now()->getTimestamp(),
        );

        $this->fellBackToRules = $this->fellBackToRules || $ranked === null;

        $ordered = $ranked === null
            ? $candidates->sortBy([
                fn (Project $a, Project $b): int => $this->skillOverlap($b->skills_needed, $skills) <=> $this->skillOverlap($a->skills_needed, $skills),
                fn (Project $a, Project $b): int => $this->tagOverlap($b->tags->map->value->all(), $interests) <=> $this->tagOverlap($a->tags->map->value->all(), $interests),
                fn (Project $a, Project $b): int => (int) in_array($b->course_id, $courseIds, true) <=> (int) in_array($a->course_id, $courseIds, true),
                fn (Project $a, Project $b): int => $b->created_at <=> $a->created_at,
            ])->values()
            : $this->inOrder($candidates, $ranked);

        return $ordered->take($limit)->each(fn (Project $project) => $project->unsetRelation('members'))->values();
    }

    /**
     * Whether the model actually ranked everything returned so far, not just whether it is switched on.
     */
    public function usesModel(): bool
    {
        return $this->client->enabled() && ! $this->fellBackToRules;
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
     * Students the given student has met on UniCircle: shared study groups, events and project teams,
     * with how many times. A trace of real friendships the recommender can use.
     *
     * @return array<int, int>
     */
    private function campusPeers(User $user): array
    {
        $shared = [];

        foreach ([['study_group_user', 'study_group_id'], ['event_user', 'event_id'], ['project_user', 'project_id']] as [$table, $key]) {
            DB::table("{$table} as mine")
                ->join("{$table} as theirs", "theirs.{$key}", '=', "mine.{$key}")
                ->where('mine.user_id', $user->id)
                ->where('theirs.user_id', '!=', $user->id)
                ->when($table === 'project_user', fn ($query) => $query->where('mine.status', 'accepted')->where('theirs.status', 'accepted'))
                ->groupBy('theirs.user_id')
                ->selectRaw('theirs.user_id as user_id, count(*) as shared')
                ->pluck('shared', 'user_id')
                ->each(function ($count, $userId) use (&$shared): void {
                    $shared[(int) $userId] = ($shared[(int) $userId] ?? 0) + (int) $count;
                });
        }

        return $shared;
    }

    /**
     * How many of the needed skills the student has (compared without case).
     *
     * @param  list<string>  $needed
     * @param  list<string>  $skills
     */
    private function skillOverlap(array $needed, array $skills): int
    {
        return count(array_intersect(array_map('mb_strtolower', $needed), array_map('mb_strtolower', $skills)));
    }

    /**
     * How many of the item's tags the student is interested in.
     *
     * @param  list<string>  $tags
     * @param  list<string>  $interests
     */
    private function tagOverlap(array $tags, array $interests): int
    {
        return count(array_intersect($tags, $interests));
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
