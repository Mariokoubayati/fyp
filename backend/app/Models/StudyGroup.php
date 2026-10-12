<?php

namespace App\Models;

use Database\Factories\StudyGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['course_id', 'title', 'description', 'location', 'starts_at', 'capacity'])]
class StudyGroup extends Model
{
    /** @use HasFactory<StudyGroupFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Groups that have not started yet.
     *
     * @param  Builder<StudyGroup>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>', now());
    }

    /**
     * Add the member count and whether the given student has joined.
     *
     * @param  Builder<StudyGroup>  $query
     */
    public function scopeWithMembership(Builder $query, User $user): void
    {
        $query->withCount('members')
            ->withExists(['members as is_member' => fn (Builder $members) => $members->whereKey($user->getKey())]);
    }

    /**
     * Load the member count and membership flag onto an already fetched group.
     */
    public function loadMembership(User $user): static
    {
        return $this->loadCount('members')
            ->loadExists(['members as is_member' => fn (Builder $members) => $members->whereKey($user->getKey())]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'capacity' => 'integer',
        ];
    }
}
