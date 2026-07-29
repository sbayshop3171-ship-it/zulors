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

namespace App\Http\Controllers\Api\User\Settings;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\User\PrivacyPermit;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;

class PrivacySettingsController extends Controller
{
    use SupportsApiResponses;
    
    private $me;

    public function __construct() {
        $this->me = me();
    }

    public function getPrivacySettings()
    {
        return $this->responseSuccess([
            'data' => [
                'mentions' => $this->me->permitSettings->mentions->value,
                'followers' => $this->me->permitSettings->followers->value,
                'direct_messages' => $this->me->permitSettings->direct_messages->value,
                'story_replies' => $this->me->permitSettings->story_replies->value,
                'group_invites' => $this->me->permitSettings->group_invites->value,
                'payment_transfers' => $this->me->permitSettings->payment_transfers->value,
            ]
        ]);
    }

    public function updatePrivacySettings(Request $request)
    {
        $request->validate([
            'mentions' => ['required', 'string', Rule::in(array_column(PrivacyPermit::cases(), 'value'))],
            'followers' => ['required', 'string', Rule::in(array_column(PrivacyPermit::followPermits(), 'value'))],
            'direct_messages' => ['required', 'string', Rule::in(array_column(PrivacyPermit::cases(), 'value'))],
            'story_replies' => ['required', 'string', Rule::in(array_column(PrivacyPermit::cases(), 'value'))],
            'group_invites' => ['required', 'string', Rule::in(array_column(PrivacyPermit::cases(), 'value'))],
            'payment_transfers' => ['required', 'string', Rule::in(array_column(PrivacyPermit::cases(), 'value'))],
        ]);

        $this->me->permitSettings()->update([
            'mentions' => $request->mentions,
            'followers' => $request->followers,
            'direct_messages' => $request->direct_messages,
            'story_replies' => $request->story_replies,
            'group_invites' => $request->group_invites,
            'payment_transfers' => $request->payment_transfers,
        ]);
        
        return $this->responseSuccess([
            'data' => null
        ]);
    }
}
