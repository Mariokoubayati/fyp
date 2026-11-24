<?php

namespace App\Models;

use App\Enums\CampusTag;
use App\Enums\ProjectMemberStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['course_id', 'title', 'description', 'skills_needed', 'tags', 'team_size', 'is_open'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * New projects are looking for members.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_open' => true,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Everyone linked to the project, whatever their status.
     *
     * @return BelongsToMany<User, $this>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['status', 'message'])->withTimestamps();
    }

    /**
     * The team, owner included.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->people()->wherePivot('status', ProjectMemberStatus::Accepted->value);
    }

    /**
     * Students waiting for the owner's answer.
     *
     * @return BelongsToMany<User, $this>
     */
    public function requests(): BelongsToMany
    {
        return $this->people()->wherePivot('status', ProjectMemberStatus::Pending->value);
    }

    /**
     * Add the team size so far and the given student's status.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeWithMembership(Builder $query, User $user): void
    {
        $query->withCount('members')
            ->addSelect(['my_status' => DB::table('project_user')
                ->select('status')
                ->whereColumn('project_user.project_id', 'projects.id')
                ->where('project_user.user_id', $user->id)
                ->limit(1),
            ]);
    }

    /**
     * Load the team size so far and the given student's status onto an already fetched project.
     */
    public function loadMembership(User $user): static
    {
        $this->loadCount('members');
        $this->setAttribute('my_status', $this->people()->whereKey($user->id)->first()?->pivot->status);

        return $this;
    }

    /**
     * Whether the team is complete. Needs the member count loaded.
     */
    public function isFull(): bool
    {
        return $this->members_count >= $this->team_size;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skills_needed' => 'array',
            'tags' => AsEnumCollection::of(CampusTag::class),
            'team_size' => 'integer',
            'is_open' => 'boolean',
        ];
    }
}
