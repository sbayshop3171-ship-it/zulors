<?php

namespace App\Http\Controllers\Admin\Ad;

use App\Models\Ad;
use App\Enums\Ad\AdApproval;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Actions\Ad\DeleteAdAction;
use App\Http\Controllers\Controller;

class AdController extends Controller
{
    private $filters = [];

    public function index(Request $request)
    {
        $approval = $request->string('approval')->value;
        $approval = in_array($approval, array_column(AdApproval::cases(), 'value')) ? $approval : 'all';
        $this->filters = [
            'search' => $request->string('search')->value,
            'approval' => $approval,
        ];

        $ads = Ad::excludeDraft()->when(($this->filters['approval'] != 'all'), function($query) {
            $query->where('approval', AdApproval::tryFrom($this->filters['approval']));
        })->when((! empty($this->filters['search'])), function ($query) {
            $query->where(function($query) {
                $query->where('title', 'like', "%{$this->filters['search']}%")
                    ->orWhere('content', 'like', "%{$this->filters['search']}%");
            });
        })->with(['user', 'media'])->latest('id')->paginate(10);

        return view('admin::ads.index.index', [
            'ads' => $ads,
            'filters' => $this->filters
        ]);
    }

    public function show(int $adId)
    {
        $adData = Ad::with(['user', 'media'])->findOrFail($adId);

        return view('admin::ads.show.index', [
            'adData' => $adData
        ]);
    }

    public function destroy(int $adId)
    {
        $adData = Ad::findOrFail($adId);

        (new DeleteAdAction($adData))->execute();

        return redirect()->route('admin.ads.index')->with('flashMessage', (new Flash(content: __('admin/flash.ad.delete_success')))->get());
    }

    public function approve(int $adId)
    {
        $adData = Ad::findOrFail($adId);

        $adData->update([
            'approval' => AdApproval::APPROVED
        ]);

        return back()->with('flashMessage', (new Flash(content: __('admin/flash.ad.approve_success')))->get());
    }

    public function reject(int $adId)
    {
        $adData = Ad::findOrFail($adId);

        $adData->update([
            'approval' => AdApproval::REJECTED
        ]);

        return back()->with('flashMessage', (new Flash(content: __('admin/flash.ad.reject_success')))->get());
    }
}
