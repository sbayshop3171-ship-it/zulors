<?php

namespace App\Http\Controllers\Api\User\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserAdPreference;
use App\Services\Ad\UserAdInterestService;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;

class AdPersonalizationSettingsController extends Controller
{
    use SupportsApiResponses;

    public function show(Request $request, UserAdInterestService $interests)
    {
        $preference = $request->user()->adPreference;
        return $this->responseSuccess(['data' => [
            'personalized_ads' => (bool) $preference?->personalized_ads,
            'eligible' => $interests->age($request->user()) !== null && $interests->age($request->user()) >= 18,
            'consent_version' => config('ads.personalization.consent_version'),
        ]]);
    }

    public function update(Request $request, UserAdInterestService $interests)
    {
        $data = $request->validate(['personalized_ads' => ['required', 'boolean']]);
        $user = $request->user();
        $enabled = (bool) $data['personalized_ads']
            && $interests->age($user) !== null
            && $interests->age($user) >= 18;

        $preference = UserAdPreference::query()->firstOrNew(['user_id' => $user->id]);
        $preference->personalized_ads = $enabled;
        $preference->consented_at = $enabled ? now() : null;
        $preference->consent_version = $enabled ? config('ads.personalization.consent_version') : null;
        $preference->save();
        if(! $enabled) $interests->forget($user);

        return $this->responseSuccess(['data' => ['personalized_ads' => $enabled, 'eligible' => $interests->age($user) >= 18]]);
    }

    public function interests(Request $request, UserAdInterestService $interests)
    {
        $scores = $interests->scores($request->user());
        return $this->responseSuccess(['data' => collect($scores)->map(fn($score, $topic) => ['topic' => $topic, 'score' => round($score, 2)])->values()]);
    }

    public function resetInterests(Request $request, UserAdInterestService $interests)
    {
        $interests->forget($request->user());
        return $this->responseSuccess(['data' => ['reset' => true]]);
    }
}
