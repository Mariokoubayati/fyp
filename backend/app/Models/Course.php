<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /**
     * The students enrolled in the course.
     *
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
