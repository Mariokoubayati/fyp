<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserSearchController extends Controller
{
    /**
     * Find other students by name (at least 2 characters), for picking a buyer or borrower.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();

        $students = mb_strlen($search) < 2
            ? collect()
            : User::query()
                ->whereKeyNot($request->user()->id)
                ->whereNotNull('email_verified_at')
                ->where('name', 'like', "%{$search}%")
                ->orderBy('name')
                ->limit(10)
                ->get();

        return StudentResource::collection($students);
    }
}
