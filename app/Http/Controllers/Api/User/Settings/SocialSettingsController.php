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
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Traits\Http\Api\SupportsApiResponses;

class SocialSettingsController extends Controller
{
    use SupportsApiResponses;
    
    private $me;

    public function __construct() {
        $this->me = me();
    }
    
    public function getSocialSettings(Request $request)
    {
        $socialLinks = $this->me->socialLinks;

        return $this->responseSuccess([
            'data' => [
                'links' => collect(social_links())->map(function($item) use ($socialLinks) {
                    $savedLink = $socialLinks->where('platform', $item['platform'])->first();
    
                    return [
                        'url' => $savedLink ? $savedLink->url : '',
                        'platform' => $item['platform'],
                        'name' => $item['name']
                    ];
                })->toArray()
            ]
        ]);
    }

    public function updateSocialSettings(Request $request)
    {
        $links = $request->get('links');

        $validator = Validator::make([
            'links' => $links,
        ], [
            'links' => ['required', 'array'],
            'links.*.url' => ['nullable', 'string', 'url', 'max:120'],
            'links.*.platform' => ['required', 'string', Rule::in(array_column(social_links(), 'platform'))],
        ], attributes: [
            'links.*.url' => 'URL',
            'links.*.platform' => 'Platform',
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }
        
        $validated = $validator->validated();
        $validatedLinks = collect($validated['links'])->filter(function($link) {
            return $link['url'] !== null;
        })->toArray();

        // Delete all existing social links.
        $this->me->socialLinks()->delete();

        // Create new social links.
        foreach ($validatedLinks as $link) {
            $this->me->socialLinks()->create([
                'url' => $link['url'],
                'platform' => $link['platform']
            ]);
        }
        
        return $this->responseSuccess([
            'data' => null
        ]);
    }
}
