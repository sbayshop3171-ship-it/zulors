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

namespace App\Constants;

abstract class Filesystem
{
    const EXTERNAL_DISK_NAME = 'external';

    const IMAGE_PLACEHOLDER_WIDTH = 128;

    const IMAGE_PLACEHOLDER_BLUR = 1;

    static function mediaNamespace(string $mediaType) {
        return config("filesystems.upload_namespaces.media") . "/{$mediaType}";
    }
}
