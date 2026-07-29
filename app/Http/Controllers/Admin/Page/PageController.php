<?php

namespace App\Http\Controllers\Admin\Page;

use App\Support\Languages;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Support\Views\Flash;

class PageController extends Controller
{
    private $availableLocales;
    private $appLanguages;

    public function __construct()
    {
        $this->appLanguages = new Languages();
        $this->availableLocales = $this->appLanguages->getLanguages()->pluck('alpha_2_code')->toArray();
    }

    public function index()
    {
        return view('admin::pages.index.index');
    }

    public function edit(Request $request, string $pageName)
    {
        if(! in_array($pageName, array_column(config('pages.static'), 'name'))) {
           abort(404);
        }

        $selectedLocale = $request->string('locale')->value;
        $selectedLocale = in_array($selectedLocale, $this->availableLocales) ? $selectedLocale : reset($this->availableLocales);

        $pageContentPath = $this->getPageFilePath($pageName, $selectedLocale);

        return view('admin::pages.edit.index', [
            'pageName' => $pageName,
            'appLanguages' => $this->appLanguages->getLanguages(),
            'selectedLocale' => $selectedLocale,
            'pageContent' => $this->getPageContent($pageContentPath)
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'page_name' => ['required', 'string', Rule::in(array_column(config('pages.static'), 'name'))],
            'locale' => ['required', 'string', Rule::in($this->availableLocales)],
            'content' => ['nullable', 'string'],
        ]);

        $pageContentPath = $this->getPageFilePath($request->page_name, $request->locale);

        if(! $this->pageWritable($pageContentPath)) {
            return redirect()->back()->withErrors([
                'content' => 'This page is not writable. Please check the file permissions.',
            ]);
        }
        else {
            file_put_contents($pageContentPath, $request->content);
        }

        return redirect()->back()->with('flashMessage', (new Flash(content: __('admin/flash.page.update_success')))->get());
    }

    private function getPageContent(string $pageContentPath)
    {
        if(! $this->pageExists($pageContentPath)) {
            return '';
        }

        return file_get_contents($pageContentPath);
    }

    private function pageExists(string $pageContentPath)
    {
        return file_exists($pageContentPath);
    }

    private function pageWritable(string $pageContentPath)
    {
        return is_writable($pageContentPath) || is_writable(dirname($pageContentPath));
    }

    private function getPageFilePath(string $pageName, string $locale)
    {
        return resource_path("views/apps/mpa/document/{$pageName}/i18n/{$locale}.blade.php");
    }
}
