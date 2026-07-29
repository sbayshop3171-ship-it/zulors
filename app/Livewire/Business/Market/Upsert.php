<?php

namespace App\Livewire\Business\Market;

use Throwable;
use App\Rules\X\XRule;
use App\Models\Product;
use Livewire\Component;
use App\Models\Category;
use App\Constants\Filesystem;
use App\Support\Views\Flash;
use App\Enums\Media\MediaType;
use Illuminate\Validation\Rule;
use App\Enums\Media\MediaStatus;
use App\Enums\Product\ProductType;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductApproval;
use App\Enums\Product\ProductCondition;
use App\Actions\Media\DeleteMediaAction;
use Illuminate\Validation\ValidationException;
use App\Services\Currency\Fiat\FiatCurrencyService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class Upsert extends Component
{
    use WithFileUploads;

    public Product $productData;
    public $categories = [];
    public $currencies = [];
    public $conditions = [];
    public $formData = [];
    public $image = null;
    public $productMedia = null;
    public $upsertType = 'create';

    public function mount()
    {
        $fiatCurrencyService = app(FiatCurrencyService::class);

        $this->categories = Category::getMarketplaceCategories();
        $this->currencies = $fiatCurrencyService->getPairedCurrencies()->toArray();
        $this->formData = [
            'with_discount' => (empty($this->productData->discount)) ? 'no' : 'yes',
            'title' =>  $this->productData->title,
            'description' => $this->productData->description,
            'currency' => $this->productData->currency,
            'category_id' => $this->productData->category_id,
            'condition' => $this->productData->condition->value,
            'stock_quantity' => $this->productData->stock_quantity,
            'price' => $this->productData->price,
            'discount' => $this->productData->discount,
            'address' => $this->productData->address,
            'media' => $this->productData->media,
            'type' => $this->productData->type->value,
        ];

        $this->conditions = $this->getProductTypeConditions();
        $this->productMedia = $this->productData->media;

        if(empty($this->formData['category_id'])) {
            if(! empty($this->categories)) {
                $this->formData['category_id'] = $this->categories[0]['key'];
            }
        }

        if(empty($this->formData['condition'])) {
            if(! empty($this->conditions)) {
                $this->formData['condition'] = $this->conditions[0]['key'];
            }
        }
    }

    public function updated($propertyName)
    {
        if ($propertyName === 'formData.type') {
            $this->conditions = $this->getProductTypeConditions();
        }
    }

    public function updatedImage()
    {
        if($this->image) {
            $this->uploadProductImage();
        }
    }

    public function render()
    {
        return view('livewire.business.market.upsert');
    }

    public function getRules()
    {
        $rules = [
            'formData.title' => [
                'required',
                'string',
                XRule::join('max', config('marketplace.product.validation.title.max')),
            ],
            'formData.description' => [
                'required',
                'string',
                XRule::join('max', config('marketplace.product.validation.desc.max')),
            ],
            'formData.category_id' => ['required', 'integer'],
            'formData.type' => ['required', 'string', 'in:digital,physical'],
            'formData.condition' => [
                'required', 'string', Rule::in(collect($this->conditions)->pluck('key')->toArray())
            ],
            'formData.stock_quantity' => ['required', 'integer', 'min:0', XRule::join('max', config('marketplace.product.validation.stock_quantity.max'))],
            'formData.price' => ['required', 'numeric', 'min:0.1', XRule::join('max', config('marketplace.product.validation.price.max'))],
            'formData.currency' => ['required', 'string', Rule::in(array_column($this->currencies, 'key'))],
            'formData.address' => ['nullable', 'string', XRule::join('max', config('marketplace.product.validation.address.max'))]
        ];

        if($this->formData['with_discount'] == 'yes') {
            $rules['formData.discount'] = ['required', 'numeric', 'between:0.1,100'];
        }

        return $rules;
    }

    public function submitForm()
    {
        $this->validate(rules: $this->getRules(), attributes: [
            'formData.title' => __('business/market.form.name'),
            'formData.description' => __('business/market.form.desc'),
            'formData.category_id' => __('business/market.form.category'),
            'formData.type' => __('business/market.form.type'),
            'formData.condition' => __('business/market.form.condition'),
            'formData.stock_quantity' => __('business/market.form.stock_qty'),
            'formData.price' => __('business/market.form.price'),
            'formData.currency' => __('business/market.form.currency'),
            'formData.discount' => __('business/market.form.discount_rate'),
        ]);

        $upsertData = [
            'title' => e($this->formData['title']),
            'description' => e($this->formData['description']),
            'category_id' => $this->formData['category_id'],
            'condition' => $this->formData['condition'],
            'stock_quantity' => $this->formData['stock_quantity'],
            'price' => $this->formData['price'],
            'currency' => $this->formData['currency'],
            'address' => e($this->formData['address']),
            'discount' => ($this->formData['with_discount'] == 'yes') ? $this->formData['discount'] : 0,
            'type' => $this->formData['type'],
        ];

        if($this->upsertType == 'create') {
            // Never change status on update. Add once on create.
            // User can change it from overview page.

            $upsertData['status'] = ProductStatus::ACTIVE;
        }

        else if(! $this->productData->approval->isPending()) {
            // Updates are reviewed again so an approved listing cannot be changed after moderation.
            $upsertData['approval'] = ProductApproval::PENDING;
        }

        $this->productData->update($upsertData);

        return redirect()->route('business.market.index')->with('flashMessage', (new Flash(content: __('business/flash.market.product_submitted')))->get());
    }

    private function getProductTypeConditions()
    {
        if($this->formData['type'] == ProductType::PHYSICAL->value) {
            return ProductCondition::physicalProductConditions();
        }
        else{
            return ProductCondition::digitalProductConditions();
        }
    }

    public function deleteProductImage(int $mediaId)
    {
        $productMediaItem = $this->productMedia->find($mediaId);

        if($productMediaItem) {
            try {
                (new DeleteMediaAction($productMediaItem))->execute();

                $this->productMedia = $this->productData->media;
            }

            catch (Throwable $th) {
                $this->addError('image', $th->getMessage());
            }
        }
    }

    private function uploadProductImage()
    {
        if($this->productMedia->count() < 5) {
            try {
                $this->validate(rules: [
                    'image' => [
                        'required',
                        'image',
                        XRule::join('mimes', config('marketplace.product.validation.image.mimes')),
                        XRule::join('mimetypes', config('marketplace.product.validation.image.mimetypes')),
                        XRule::join('max', config('marketplace.product.validation.image.max'))
                    ]
                ], attributes: [
                    'image' => __('business/market.form.product_image')
                ]);

                $imageUploadService = app(ImageUploadService::class);
                $roundRobinService = app(RoundRobinService::class);

                $imageData = $imageUploadService
                    ->load($this->image->getRealPath())
                    ->setNamespace(Filesystem::mediaNamespace('products/images'))
                    ->setStorageDisk($roundRobinService->getNextDisk())
                    ->watermark()
                    ->crop(config('marketplace.product.image_width'), config('marketplace.product.image_height'))
                    ->compress()
                    ->upload();

                if($imageData) {
                    $this->productData->media()->create([
                        'source_path' => $imageData['image_path'],
                        'disk' => $imageData['disk'],
                        'type' => MediaType::IMAGE,
                        'status' => MediaStatus::PROCESSED,
                        'extension' => $this->image->getClientOriginalExtension(),
                        'mime' => $this->image->getClientMimeType(),
                        'size' => $imageData['image_size'],
                        'metadata' => []
                    ]);

                    $this->productMedia = $this->productData->media;
                }
            }

            catch (ValidationException $e) {
                $this->addError('image', $e->getMessage());
            }
        }
        else {
            $this->addError('image', __('business/market.validation.image.count'));
        }
    }
}
