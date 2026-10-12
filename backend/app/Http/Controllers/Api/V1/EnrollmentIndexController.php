<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CourseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EnrollmentIndexController extends Controller
{
    /**
     * List the courses the signed-in student is enrolled in.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return CourseResource::collection($request->user()->courses()->orderBy('code')->get());
    }
}
