<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('brand.dark_theme_enabled', true);
        $this->migrator->add('brand.default_theme', 'light');
        $this->migrator->add('brand.images_watermark_enabled', true);
        $this->migrator->add('brand.videos_watermark_enabled', true);
    }
};
