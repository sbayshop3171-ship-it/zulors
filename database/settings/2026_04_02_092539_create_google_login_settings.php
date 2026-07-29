<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('google_login.enabled', false);
            $this->migrator->add('google_login.client_id', '');
            $this->migrator->add('google_login.client_secret', '');
        });
    }
};
