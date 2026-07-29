<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\User\Onboarding;

use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;

class OnboardingController extends Controller
{
    private Collection $stepMap;
    private int $totalSteps;

    public function __construct()
    {
        $this->stepMap = collect(config('user.onboarding_steps'));
        $this->totalSteps = $this->stepMap->count();
    }

    public function index(string $step)
    {
        if(! $this->validateStep($step)) {
            abort(404);
        }

        $stepComponentData = $this->stepMap->get($step);

        return view('onboarding::index', [
            'stepComponentData' => $stepComponentData,
            'stepIndex' => $this->stepMap->keys()->search($step),
            'totalSteps' => $this->totalSteps
        ]);
    }

    private function validateStep(string $step)
    {
        return $this->stepMap->has($step);
    }
}
