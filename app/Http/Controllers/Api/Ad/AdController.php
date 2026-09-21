<?php

namespace App\Http\Controllers\Api\Ad;

use App\Models\Ad;
use Illuminate\Http\Request;
use App\Actions\Ad\AdShowAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Ad\AdResource;
use App\Services\Ad\TargetedAdService;
use App\Services\Ad\AdDestinationResolver;
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

    public function click(int $adId, Request $request, TargetedAdService $targetedAdService, AdDestinationResolver $destinationResolver)
    {
        $adData = Ad::published()->approved()->with(['media', 'sourcePost.media', 'user'])->findOrFail($adId);

        abort_unless($adData->isSourceAvailable(), 404);
        abort_if(blank($destination = $destinationResolver->resolve($adData)), 404);

        $targetedAdService->recordClick($adData, $request);

        return redirect()->away($destination);
    }

    public function event(int $adId, Request $request, TargetedAdService $targetedAdService)
    {
        $adData = Ad::published()->approved()->findOrFail($adId);
        $event = $targetedAdService->recordEvent($adData, $request);

        return $this->responseSuccess(['data' => ['id' => $event->id]]);
    }
}
