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

namespace App\Events\Media;

use Illuminate\Foundation\Events\Dispatchable;

class MediaCreatedEvent
{
    use Dispatchable;

    public $mediaItem;

    /**
     * Create a new event instance.
     */
    public function __construct($mediaItem)
    {
        $this->mediaItem = $mediaItem;
    }
}
