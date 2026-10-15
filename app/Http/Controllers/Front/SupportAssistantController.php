<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstimateSupportUrgencyRequest;
use App\Services\SupportUrgencyEstimator;
use Illuminate\Http\JsonResponse;

class SupportAssistantController extends Controller
{
    public function estimate(
        EstimateSupportUrgencyRequest $request,
        SupportUrgencyEstimator $estimator,
    ): JsonResponse {
        return response()->json($estimator->evaluer($request->validated('message')));
    }
}
