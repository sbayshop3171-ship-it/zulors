<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Ultimate Zulors Web Application.
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

use App\Info\Zulors;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Timeline\UserInterestService;

/*
|--------------------------------------------------------------------------
| Story Clear Command
|--------------------------------------------------------------------------
| This command clears expired stories from the database every day at 00:00.
|--------------------------------------------------------------------------
*/

Schedule::command('story:clear')->dailyAt('00:00');

Schedule::command('chat:invite-clear')->weekly();

Schedule::command('interests:decay')->dailyAt('02:30');

Schedule::command('media:cleanup-temp --hours=24')->dailyAt('03:00');

Artisan::command('app:version', function () {
    $this->info(Zulors::VERSION);
});

Artisan::command('db:test', function () {
    try {
        DB::connection()->getPdo();

        $this->info('OK. Your app is connected to database: ' . DB::connection()->getDatabaseName());
    } catch (Exception $e) {
        $this->error('Could not connect to the database: ' . $e->getMessage());
    }
});

Artisan::command('interests:decay', function () {
    $updatedCount = app(UserInterestService::class)->decayAll();

    $this->info("Decayed {$updatedCount} user interest scores.");
});
