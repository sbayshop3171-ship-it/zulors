<?php

namespace App\Models\Traits\Text;

trait InteractsWithText
{
    public function getMentions()
    {
        $mentions = [];

        if($this->content) {
            preg_match_all('/@([a-zA-Z0-9_.]+)/', $this->content, $matches);

            $mentions = $matches[1];
        }

        return $mentions;
    }

    public function getContentLanguage()
    {
        return detect_text_language($this->content);
    }

    public function isContentTranslatable()
    {
        if($this->text_language) {
            return $this->text_language !== app()->getLocale();
        }

        return false;
    }
}
