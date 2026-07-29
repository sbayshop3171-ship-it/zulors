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

namespace App\Enums\Story;

enum StoryType: string
{
	case VIDEO = 'video';
	case IMAGE = 'image';
	case TEXT = 'text';

	public function isImage():bool
    {
        return $this == self::IMAGE;
    }

	public function isVideo():bool
    {
        return $this == self::VIDEO;
    }

	public function isTextified():bool
    {
        return $this == self::TEXT;
    }

	public function label():string
    {
        return match($this) {
            self::VIDEO => __('labels.media_type_labels.video'),
            self::IMAGE => __('labels.media_type_labels.image'),
            self::TEXT => __('labels.media_type_labels.text'),
        };
    }
}
