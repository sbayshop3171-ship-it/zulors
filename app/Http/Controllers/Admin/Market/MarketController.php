<?php

namespace App\Http\Controllers\Admin\Market;

use App\Models\Product;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Enums\Product\ProductApproval;
use App\Actions\Product\DeleteProductAction;
use App\Notifications\User\Market\ProductApprovedNotification;
use App\Notifications\User\Market\ProductRejectedNotification;

class MarketController extends Controller
{
    private $filters = [];

    public function index(Request $request)
    {
        $approval = $request->string('approval')->value;
        $approval = in_array($approval, array_column(ProductApproval::cases(), 'value')) ? $approval : 'all';

        $this->filters = [
            'search' => $request->string('search')->value,
            'approval' => $approval,
        ];

        $products = Product::active()->when(($this->filters['approval'] != 'all'), function($query) {
            $query->where('approval', ProductApproval::tryFrom($this->filters['approval']));
        })->when((! empty($this->filters['search'])), function ($query) {
            $query->where(function($query) {
                $query->where('title', 'like', "%{$this->filters['search']}%")
                    ->orWhere('description', 'like', "%{$this->filters['search']}%");
            });
        })->with(['user', 'category'])->latest('id')->paginate(10);

        return view('admin::market.index.index', [
            'products' => $products,
            'filters' => $this->filters
        ]);
    }

    public function show(int $productId)
    {
        $productData = Product::active()->with(['user', 'category'])->findOrFail($productId);

        return view('admin::market.show.index', [
            'productData' => $productData
        ]);
    }

    public function destroy(int $productId)
    {
        $productData = Product::active()->findOrFail($productId);

        (new DeleteProductAction($productData))->execute();

        return redirect()->route('admin.market.index')
            ->with('flashMessage', (new Flash(content: __('admin/flash.product.delete_success')))->get());
    }

    public function approve(int $productId)
    {
        $productData = Product::active()->findOrFail($productId);
        $shouldNotify = ! $productData->approval->isApproved();

        $productData->update(['approval' => ProductApproval::APPROVED]);

        if($shouldNotify) {
            $productData->user->notify(new ProductApprovedNotification($productData));
        }

        return back()->with('flashMessage', (new Flash(content: __('admin/flash.product.approve_success')))->get());
    }

    public function reject(int $productId)
    {
        $productData = Product::active()->findOrFail($productId);
        $shouldNotify = ! $productData->approval->isRejected();

        $productData->update(['approval' => ProductApproval::REJECTED]);

        if($shouldNotify) {
            $productData->user->notify(new ProductRejectedNotification($productData));
        }

        return back()->with('flashMessage', (new Flash(content: __('admin/flash.product.reject_success')))->get());
    }
}
