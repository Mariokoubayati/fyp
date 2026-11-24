<?php

namespace App\Models;

use App\Enums\CampusTag;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['course_id', 'title', 'description', 'location', 'starts_at', 'ends_at', 'capacity', 'tags'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Students who said they are going.
     *
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Events that have not started yet.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>', now());
    }

    /**
     * Add the attendee count and whether the given student is going.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeWithAttendance(Builder $query, User $user): void
    {
        $query->withCount('attendees')
            ->withExists(['attendees as is_going' => fn (Builder $attendees) => $attendees->whereKey($user->getKey())]);
    }

    /**
     * Load the attendee count and the going flag onto an already fetched event.
     */
    public function loadAttendance(User $user): static
    {
        return $this->loadCount('attendees')
            ->loadExists(['attendees as is_going' => fn (Builder $attendees) => $attendees->whereKey($user->getKey())]);
    }

    /**
     * Whether a capacity is set and reached. Needs the attendee count loaded.
     */
    public function isFull(): bool
    {
        return $this->capacity !== null && $this->attendees_count >= $this->capacity;
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
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'tags' => AsEnumCollection::of(CampusTag::class),
        ];
    }
}
