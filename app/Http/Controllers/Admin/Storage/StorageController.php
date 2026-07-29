<?php

namespace App\Http\Controllers\Admin\Storage;

use App\Models\DataStat;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Filesystem\RoundRobin\RoundRobinService;

class StorageController extends Controller
{
    private $roundRobinService;

    public function __construct(RoundRobinService $roundRobinService)
    {
        $this->roundRobinService = $roundRobinService;
    }

    public function index()
    {
        $roundRobinDisks = $this->roundRobinService->getRoundRobinDisks();

        return view('admin::config.storage.index.index', [
            'roundRobinDisks' => collect($roundRobinDisks)->map(function ($diskData, $key) {
                $diskData['id'] = $key;

                return $diskData;
            })->toArray(),
        ]);
    }

    public function show(string $diskId)
    {
        $dataStats = DataStat::where('disk', $diskId)->get();

        $diskStats = [
            'image' => [
                'content_size' => 0,
                'content_items' => 0,
            ],
            'video' => [
                'content_size' => 0,
                'content_items' => 0,
            ],
            'audio' => [
                'content_size' => 0,
                'content_items' => 0,
            ],
            'document' => [
                'content_size' => 0,
                'content_items' => 0,
            ],
        ];

        foreach($dataStats as $dataStat) {
            $diskStats[$dataStat->media_type->value]['content_size'] += $dataStat->content_size;
            $diskStats[$dataStat->media_type->value]['content_items'] += $dataStat->content_items;
        }

        return view('admin::config.storage.show.index', [
            'diskStats' => $diskStats,
            'diskId' => $diskId,
        ]);
    }
}
