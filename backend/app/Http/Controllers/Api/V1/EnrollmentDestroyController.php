<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EnrollmentDestroyController extends Controller
{
    /**
     * Remove the signed-in student from a course (idempotent).
     */
    public function __invoke(Request $request, Course $course): Response
    {
        $request->user()->courses()->detach($course->id);

        return response()->noContent();
    }
}
