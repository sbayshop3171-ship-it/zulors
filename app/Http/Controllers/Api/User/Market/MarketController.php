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

namespace App\Http\Controllers\Api\User\Market;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Enums\Product\ProductType;
use App\Http\Controllers\Controller;
use App\Enums\Product\ProductCondition;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Market\ProductResource;
use App\Services\Currency\Fiat\FiatCurrencyService;
use App\Http\Resources\User\Market\ProductCollection;
use App\Http\Resources\User\Market\CategoryCollection;
use App\Traits\Http\Controllers\Api\User\Market\ValidatesProductFilters;

class MarketController extends Controller
{
    use SupportsApiResponses, ValidatesProductFilters;

    public function getProducts(Request $request)
    {
        $filterOption = $this->getValidatedFilters($request);

        $products = Product::listable()->filter($filterOption)->withRelations()->latest('id')->take(12)->get();
        
        return $this->responseSuccess([
            'data' => ProductCollection::make($products)
        ]);
    }

    public function getProductData(Request $request, $productId)
    {
        $productData = Product::active()->withRelations()->findByHashId($productId);

	        if($productData) {
	            $isOwnerOrRoot = (me()->isRoot() || me()->id === $productData->user_id);
	            
	            if((! $productData->approval->isApproved() || ! $productData->status->isActive()) && ! $isOwnerOrRoot) {
	                return $this->responseResourceNotFoundError('Product', $productId);
	            }

	            return $this->responseSuccess([
                'data' => ProductResource::make($productData)
            ]);
        }

        return $this->responseResourceNotFoundError('Product', $productId);
    }

    public function getCategories(Request $request)
    {
        $categories = Category::active()->marketplace()->take(16)->get();

        return $this->responseSuccess([
            'data' => CategoryCollection::make($categories)
        ]);
    }

    public function bookmark(Request $request)
    {
        $productId = $request->integer('id');

        $productData = Product::listable()->find($productId);

        if ($productData) {
            $bookmarkedStatus = $productData->isBookmarkedBy(me()->id);

            if($bookmarkedStatus) {
                $productData->removeBookmark(me()->id);
            }
            else {
                $productData->addBookmark(me()->id);
            }

            return $this->responseSuccess([
                'data' => [
                    'bookmarked' => (! $bookmarkedStatus)
                ]
            ]);
        }

        else{
            return $this->responseResourceNotFoundError('Product', $productId);
        }
    }

	    public function getMetadata(Request $request)
	    {
	        $fiatCurrencyService = app(FiatCurrencyService::class);
        
        return $this->responseSuccess([
            'data' => [
                'filter' => [
                    'currencies' => $fiatCurrencyService->getPairedCurrencies(),
                    'conditions' => ProductCondition::physicalProductConditions(),
                    'types' =>  ProductType::types(),
                ]
	            ]
	        ]);
	    }

	    public function getBookmarks(Request $request)
	    {
	        $bookmarkedProducts = Product::listable()->whereHas('bookmarks', function ($query) {
	            $query->where('user_id', me()->id);
	        })->withRelations()->latest('id')->take(1000)->get();
	        
	        return $this->responseSuccess([
	            'data' => ProductCollection::make($bookmarkedProducts)
	        ]);
	    }
	}
