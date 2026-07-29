<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('code.header_code', '');
            $this->migrator->add('code.footer_code', '');
            $this->migrator->add('code.header_code_enabled', false);
            $this->migrator->add('code.footer_code_enabled', false);
        });
    }
};
