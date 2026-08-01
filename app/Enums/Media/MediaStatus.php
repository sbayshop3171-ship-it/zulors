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

namespace App\Enums\Media;

enum MediaStatus:string
{
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case UNPROCESSED = 'unprocessed';
    case FAILED = 'failed';

    public function isProcessed():bool
    {
        return $this == self::PROCESSED;
    }

    public function isProcessing():bool
    {
        return $this == self::PROCESSING;
    }

    public function isFailed():bool
    {
        return $this == self::FAILED;
    }
    
    
}
