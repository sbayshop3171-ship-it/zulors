<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
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

namespace App\Http\Controllers\Business\Market;

use Illuminate\Http\Request;
use App\Support\Views\Flash;
use App\Enums\Product\ProductStatus;
use App\Http\Controllers\Controller;
use App\Actions\Product\DeleteProductAction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MarketController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $type = $request->string('type', 'all');
        $type = in_array($type, ['all', 'active', 'archived']) ? $type : 'all';
        
        $productsList = me()->products()->with([
            'category',
            'media'
        ])->active()->when($type, function($query) use ($type) {
            if($type == 'active') {
                $query->where('status', ProductStatus::ACTIVE);
            }
            else if($type == 'archived') {
                $query->where('status', ProductStatus::INACTIVE);
            }
        })->latest('id')->paginate(10);

        return view('business::market.index.index', [
            'type' => $type,
            'productsList' => $productsList
        ]);
    }

    public function create()
    {
        return view('business::market.create', [
            'productData' => $this->fetchOrInitializeDraftProduct()
        ]);
    }

    public function edit($productId)
    {
        $productData = me()->products()->where('id', $productId)->firstOrFail();

        return view('business::market.edit', [
            'productData' => $productData
        ]);
    }

    public function show($productId)
    {
        $productData = me()->products()->where('id', $productId)->with([
            'category',
            'media',
            'user'
        ])->firstOrFail();

        return view('business::market.show.index', [
            'productData' => $productData
        ]);
    }

    private function fetchOrInitializeDraftProduct()
    {
        $productData = me()->products()->where('status', ProductStatus::DRAFT)->first();
    
        if(empty($productData)) {
            me()->products()->create([
                'currency' => $this->defaultCurrency(),
                'status' => ProductStatus::DRAFT
            ]);

            return me()->products()->where('status', ProductStatus::DRAFT)->first();
        }

        else {
            return $productData;
        }
    }

    private function defaultCurrency(): string
    {
        return config('app.default_currency') ?: 'USD';
    }

    public function unpublish($productId)
    {
        $productData = me()->products()->where('id', $productId)->firstOrFail();

        $productData->update([
            'status' => ProductStatus::INACTIVE
        ]);
        
        return redirect()->route('business.market.show', $productId)
            ->with('flashMessage', (new Flash(content: __('business/flash.market.product_unpublished')))->get());
    }

    public function publish($productId)
    {
        $productData = me()->products()->where('id', $productId)->firstOrFail();

        $productData->update([
            'status' => ProductStatus::ACTIVE
        ]);
        
        return redirect()->route('business.market.show', $productId)
            ->with('flashMessage', (new Flash(content: __('business/flash.market.product_published')))->get());
    }

    public function destroy($productId)
    {
        $productData = me()->products()->where('id', $productId)->firstOrFail();

        (new DeleteProductAction($productData))->execute();

        return redirect()->route('business.market.index')
            ->with('flashMessage', (new Flash(content: __('business/flash.market.product_deleted')))->get());
    }
}
