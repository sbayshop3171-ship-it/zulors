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

namespace App\Enums\Post;

enum PostType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case POLL = 'poll';
    case GIF = 'gif';

    public function isTextified():bool
    {
        return $this == self::TEXT;
    }

    public function isImage():bool
    {
        return $this == self::IMAGE;
    }
        
    public function isGif():bool
    {
        return $this == self::GIF;
    }

    public function isVideo():bool
    {
        return $this == self::VIDEO;
    }

    public function isPoll():bool
    {
        return $this == self::POLL;
    }

    public function isAudio():bool
    {
        return $this == self::AUDIO;
    }

    public function isDocument():bool
    {
        return $this == self::DOCUMENT;
    }

    public function isMedia():bool
    {
        return $this == self::DOCUMENT || $this == self::VIDEO || $this == self::IMAGE || $this == self::AUDIO || $this == self::GIF;
    }

    public function label():string
    {
        return match($this) {
            self::TEXT => __('labels.media_type_labels.text'),
            self::IMAGE => __('labels.media_type_labels.image'),
            self::VIDEO => __('labels.media_type_labels.video'),
            self::AUDIO => __('labels.media_type_labels.audio'),
            self::DOCUMENT => __('labels.media_type_labels.document'),
            self::POLL => __('labels.media_type_labels.poll'),
            self::GIF => __('labels.media_type_labels.gif'),
        };
    }
}
