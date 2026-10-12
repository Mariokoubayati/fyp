<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

#[Fillable(['name', 'email', 'password', 'major', 'study_year', 'bio'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Default attribute values; mirrors the database default for new accounts.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'trust_score' => 500,
    ];

    /**
     * The courses the student is enrolled in this semester.
     *
     * @return BelongsToMany<Course, $this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class)->withTimestamps();
    }

    /**
     * @return HasMany<TrustEvent, $this>
     */
    public function trustEvents(): HasMany
    {
        return $this->hasMany(TrustEvent::class);
    }

    /**
     * @return HasMany<TrustScoreLog, $this>
     */
    public function trustScoreLogs(): HasMany
    {
        return $this->hasMany(TrustScoreLog::class);
    }

    /**
     * Get the identifier stored in the JWT subject claim.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Get custom claims added to the JWT.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'study_year' => 'integer',
            'trust_score' => 'integer',
        ];
    }
}
