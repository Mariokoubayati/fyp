<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EnrollmentStoreController extends Controller
{
    /**
     * Enroll the signed-in student in a course (idempotent).
     */
    public function __invoke(Request $request, Course $course): Response
    {
        $request->user()->courses()->syncWithoutDetaching([$course->id]);

        return response()->noContent();
    }
}
