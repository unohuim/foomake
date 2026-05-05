<?php

namespace App\Http\Controllers;

use App\Navigation\NavigationEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Return the current tenant navigation eligibility state.
 */
class NavigationStateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, NavigationEligibility $navigationEligibility): JsonResponse
    {
        return response()->json(
            $navigationEligibility->forUser($request->user())
        );
    }
}
