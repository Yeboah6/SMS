<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearchService $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json([
            'groups' => $search->search($user, $validated['q']),
        ]);
    }
}
