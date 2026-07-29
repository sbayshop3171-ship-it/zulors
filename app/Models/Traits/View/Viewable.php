<?php

namespace App\Models\Traits\View;

use App\Data\DataCapsule;
use Illuminate\Support\Facades\Cache;

trait Viewable
{
	protected static function boot()
    {
        parent::boot();

        static::retrieved(function($model) {
            $request = request();
			$deviceId = $request->cookie('device_id');
			
			$dataCapsule = app()->make(DataCapsule::class);
			$modelName = get_class($model);
			$cacheKey = "$deviceId:resourceViews";
			$capsuleKey = "resourceViews";

			$capsuledViews = $dataCapsule->get($capsuleKey, []);
			$cachedViews = Cache::get($cacheKey, []);

			if(empty($cachedViews[$modelName])) {
				$cachedViews[$modelName] = [];
			}
			
			if(empty($capsuledViews[$modelName])) {
				$capsuledViews[$modelName] = [];
			}

			if(in_array($model->id, $cachedViews[$modelName]) != true) {
				array_push($cachedViews[$modelName], $model->id);
				array_push($capsuledViews[$modelName], $model->id);
				
				Cache::put($cacheKey, $cachedViews, now()->addHours(24));
				$dataCapsule->set($capsuleKey, $capsuledViews);

				$model->views_count = $model->views_count + 1;
			}
        });
    }
}
