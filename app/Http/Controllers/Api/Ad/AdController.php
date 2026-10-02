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

    public function engagements(int $adId, Request $request)
    {
        $ad = Ad::published()->approved()->findOrFail($adId);
        $userId = $request->user()->id;

        $comments = $ad->engagements()->where('type', 'comment')->whereNull('parent_id')
            ->with(['user', 'replies.user'])->latest()->limit(50)->get();

        return $this->responseSuccess(['data' => [
            'comments' => $comments,
            'counts' => [
                'likes' => $ad->engagements()->where('type', 'like')->count(),
                'comments' => $ad->engagements()->where('type', 'comment')->count(),
                'shares' => $ad->events()->where('event_type', 'share')->count(),
                'saves' => $ad->engagements()->where('type', 'save')->count(),
            ],
            'viewer' => [
                'liked' => $ad->engagements()->where(['user_id' => $userId, 'type' => 'like'])->exists(),
                'saved' => $ad->engagements()->where(['user_id' => $userId, 'type' => 'save'])->exists(),
            ],
        ]]);
    }

    public function engage(int $adId, Request $request, TargetedAdService $targetedAdService)
    {
        $ad = Ad::published()->approved()->findOrFail($adId);
        $data = $request->validate([
            'type' => ['required', 'in:like,save,comment,share,report'],
            'content' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $userId = $request->user()->id;
        $type = $data['type'];
        $engagement = null;

        if(in_array($type, ['like', 'save'], true)) {
            $engagement = $ad->engagements()->where(['user_id' => $userId, 'type' => $type])->first();
            if($engagement) {
                $engagement->delete();
                $active = false;
            } else {
                $engagement = $ad->engagements()->create(['user_id' => $userId, 'type' => $type]);
                $active = true;
            }
        } elseif($type === 'comment') {
            abort_if(blank(trim((string) ($data['content'] ?? ''))), 422, 'Comment content is required.');
            $parentId = $data['parent_id'] ?? null;
            if($parentId) {
                abort_unless($ad->engagements()->where(['id' => $parentId, 'type' => 'comment'])->exists(), 422, 'Invalid comment parent.');
            }
            $engagement = $ad->engagements()->create([
                'user_id' => $userId,
                'parent_id' => $parentId,
                'type' => 'comment',
                'content' => trim($data['content']),
            ]);
            $active = true;
        } else {
            $targetedAdService->recordEvent($ad, $request->merge(['event_type' => $type]));
            $active = true;
        }

        return $this->responseSuccess(['data' => [
            'active' => $active,
            'engagement' => $engagement,
            'counts' => [
                'likes' => $ad->engagements()->where('type', 'like')->count(),
                'comments' => $ad->engagements()->where('type', 'comment')->count(),
                'saves' => $ad->engagements()->where('type', 'save')->count(),
            ],
        ]]);
    }
}
