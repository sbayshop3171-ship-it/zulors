<?php

namespace App\Http\Controllers\Api\Ad;

use App\Models\Ad;
use Illuminate\Http\Request;
use App\Actions\Ad\AdShowAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Ad\AdResource;
use App\Services\Ad\TargetedAdService;
use App\Traits\Http\Api\SupportsApiResponses;

class AdController extends Controller
{
    use SupportsApiResponses;

    public function getAd(Request $request, TargetedAdService $targetedAdService)
    {
        $adData = $targetedAdService->selectForRequest($request);

        // No published ads is a valid empty state for feeds.

        if(! $adData) {
            return $this->responseSuccess([
                'data' => null
            ]);
        }
        
        $targetedAdService->recordImpression($adData, $request);
        (new AdShowAction($adData))->execute();

        return $this->responseSuccess([
            'data' => AdResource::make($adData)
        ]);
    }

    public function click(int $adId, Request $request, TargetedAdService $targetedAdService)
    {
        $adData = Ad::published()->approved()->with(['media', 'sourcePost.media'])->findOrFail($adId);

        abort_unless($adData->isSourceAvailable(), 404);

        $targetedAdService->recordClick($adData, $request);

        return redirect()->away($adData->target_url);
    }
}
