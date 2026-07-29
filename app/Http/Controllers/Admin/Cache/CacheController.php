<?php

namespace App\Http\Controllers\Admin\Cache;

use App\Support\Views\Flash;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class CacheController extends Controller
{
    public function reset()
    {
        defer(function () {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('optimize:clear');

            if (app()->isProduction()) {
                Artisan::call('optimize');
            }
        });

        return back()->with('flashMessage', (new Flash(content: __('admin/flash.cache.reset')))->get());
    }
}
