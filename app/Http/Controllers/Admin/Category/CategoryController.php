<?php

namespace App\Http\Controllers\Admin\Category;

use App\Models\Product;
use App\Models\Category;
use App\Models\JobListing;
use App\Support\Languages;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\Category\CategoryType;
use App\Http\Controllers\Controller;
use App\Enums\Category\CategoryStatus;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    private $appLanguages;
    private $types;
    private $filters = [];

    public function __construct()
    {
        $this->appLanguages = new Languages();
        $this->types = CategoryType::cases();
    }

    public function index(Request $request)
    {
        $categoryType = $request->string('type')->value;
        $categoryType = in_array($categoryType, ['product', 'job']) ? $categoryType : 'all';

        $this->filters = [
            'type' => $categoryType
        ];

        $categories = Category::active()->when(($this->filters['type'] != 'all'), function($query) {
            $query->where('categorizable_type', ($this->filters['type'] == 'product') ? CategoryType::PRODUCT : CategoryType::JOB);
        })->latest('id')->paginate(10);

        return view('admin::categories.index.index', [
            'categories' => $categories,
            'filters' => $this->filters
        ]);
    }

    public function create()
    {
        $categoryData = $this->fetchOrInitializeDraftCategory();

        return view('admin::categories.upsert.index', [
            'categoryData' => $categoryData,
            'upsertType' => 'create',
            'types' => $this->types,
            'appLanguages' => $this->appLanguages->getLanguages()
        ]);
    }

    public function edit(int $categoryId)
    {
        $categoryData = $this->fetchCategoryById($categoryId);
        
        return view('admin::categories.upsert.index', [
            'categoryData' => $categoryData,
            'upsertType' => 'update',
            'types' => $this->types,
            'appLanguages' => $this->appLanguages->getLanguages()
        ]);
    }

    public function upsert(Request $request)
    {
        $categoryId = $request->integer('category_id');

        $categoryData = $this->fetchCategoryById($categoryId);
        
        $validator = Validator::make([
            'translations' => $request->translations,
            'type' => $request->type,
            'upsert_type' => $request->upsert_type,
        ], [
            'translations' => ['required', 'array'],
            'upsert_type' => ['required', 'string', Rule::in(['create', 'update'])],
            'translations.*' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in(array_column($this->types, 'value'))],
        ], attributes: [
            'translations' => __('admin/categories.form.translations'),
            'translations.*' => __('admin/categories.form.translation'),
            'type' => __('admin/categories.form.type'),
        ]);

        if($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $categoryData->update([
            'localization' => $request->translations,
            'categorizable_type' => $request->type,
            'status' => CategoryStatus::ACTIVE,
        ]);

        return redirect()->route('admin.categories.show', ['categoryId' => $categoryId])->with('flashMessage', (new Flash(content: match($request->upsert_type) {
            'create' => __('admin/flash.category.create_success'),
            'update' => __('admin/flash.category.update_success'),
        }))->get());
    }

    public function show(int $categoryId)
    {
        $categoryData = $this->fetchCategoryById($categoryId);
        
        return view('admin::categories.show.index', [
            'categoryData' => $categoryData
        ]);
    }

    public function destroy(int $categoryId)
    {
        $categoryData = $this->fetchCategoryById($categoryId);

        // TODO: Check if the category has any products or jobs associated with it.
        // If it does, then set the category_id to null for the products or jobs.

        if($categoryData->categorizable_type->isProduct()) {
            Product::where('category_id', $categoryId)->update([
                'category_id' => null
            ]);
        }
        else if($categoryData->categorizable_type->isJob()) {
            JobListing::where('category_id', $categoryId)->update([
                'category_id' => null
            ]);
        }

        $categoryData->delete();

        return redirect()->route('admin.categories.index')->with('flashMessage', (new Flash(content: __('admin/flash.category.delete_success')))->get());
    }

    private function fetchCategoryById(int $categoryId)
    {
        return Category::findOrFail($categoryId);
    }

    private function fetchOrInitializeDraftCategory()
    {
        $draftCategory = Category::draftable()->first();

        if(empty($draftCategory)) {
            $draftCategory = Category::create([
                'status' => CategoryStatus::DRAFT,
                'categorizable_type' => CategoryType::UNCATEGORIZED,
                'localization' => []
            ]);
        }

        return $draftCategory;
    }
}
