<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseIndexController extends Controller
{
    /**
     * List the course catalogue, optionally filtered by code or name.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();

        $courses = Course::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
            }))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return CourseResource::collection($courses);
    }
}
