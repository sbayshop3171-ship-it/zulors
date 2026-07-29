<?php

namespace App\Http\Controllers\Admin\Dash;

use App\Models\Ad;
use App\Models\Post;
use App\Models\User;
use App\Support\Num;
use App\Models\Product;
use App\Models\JobListing;
use App\Models\StoryFrame;
use App\Http\Controllers\Controller;

class DashController extends Controller
{
    public function dashboard()
    {
        return view('admin::dash.index', [
            'metrics' => $this->getMetrics()
        ]);
    }

    private function getMetrics()
    {
        $totalUsers = cache()->remember('admin:dash:total_users', 60, function () {
            return User::onboarded()->count();
        });

        $totalPublications = cache()->remember('admin:dash:total_publications', 60, function () {
            return Post::active()->count();
        });

        $totalProducts = cache()->remember('admin:dash:total_products', 60, function () {
            return Product::approved()->count();
        });

        $totalJobs = cache()->remember('admin:dash:total_jobs', 60, function () {
            return JobListing::approved()->count();
        });

        $totalAdvertising = cache()->remember('admin:dash:total_advertising', 60, function () {
            return Ad::approved()->count();
        });

        $totalStories = cache()->remember('admin:dash:total_stories', 60, function () {
            return StoryFrame::active()->count();
        });

        return [
            'users' => Num::abbreviate($totalUsers),
            'publications' => Num::abbreviate($totalPublications),
            'products' => Num::abbreviate($totalProducts),
            'jobs' => Num::abbreviate($totalJobs),
            'advertising' => Num::abbreviate($totalAdvertising),
            'stories' => Num::abbreviate($totalStories),
        ];
    }
}
