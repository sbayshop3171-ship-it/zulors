<?php

namespace App\Http\Controllers\Admin\Lang;

use Exception;
use App\Models\User;
use App\Models\Locale;
use App\Support\Languages;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Enums\Flash\FlashType;
use App\Http\Controllers\Controller;
use App\Actions\Locale\CDLocaleAction;
use Illuminate\Support\Facades\Validator;

class LangController extends Controller
{
    public function index()
    {
        $languages = Locale::latest()->paginate(10);

        return view('admin::lang.index.index', [
            'languages' => $languages
        ]);
    }

    public function create()
    {
        return view('admin::lang.create.index');
    }

    public function store(Request $request)
    {
        $validator = Validator::make([
            'name' => $request->name,
            'native_name' => $request->native_name,
            'alpha_2' => $request->alpha_2,
            'direction' => $request->direction,
        ], [
            'name' => ['required', 'string', 'max:22'],
            'native_name' => ['required', 'string', 'max:22'],
            'alpha_2' => ['required', 'string', 'size:2', 'unique:locales,alpha_2_code'],
            'direction' => ['required', 'string', 'in:ltr,rtl'],
        ], attributes: [
            'name' => __('admin/lang.form.name'),
            'native_name' => __('admin/lang.form.native_name'),
            'alpha_2' => __('admin/lang.form.alpha_2'),
            'direction' => __('admin/lang.form.direction'),
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $langData = Locale::create([
                'name' => $request->name,
                'native_name' => $request->native_name,
                'alpha_2_code' => $request->alpha_2,
                'direction' => $request->direction,
                'status' => true,
                'is_default' => false,
            ]);
    
            (new Languages())->refreshCache();

            (new CDLocaleAction($langData->alpha_2_code))->createLocale();
            
            return redirect()->route('admin.lang.index')->with('flashMessage', (new Flash(content: __('admin/flash.lang.create_lang_success', ['lang' => $langData->name])))->get());
        } catch (Exception $e) {
            return redirect()->route('admin.lang.index')->with('flashMessage', (new Flash(content: $e->getMessage(), type: FlashType::ERROR))->get());
        }
    }

    public function destroy(int $localeId)
    {
        $localeData = Locale::findOrFail($localeId);
        
        if($localeData->is_default) {
            return back()->with('flashMessage', (new Flash(content: __('admin/flash.lang.delete_default_lang'), type: FlashType::ERROR))->get());
        }

        if($localeData->isPermanent()) {
            return back()->with('flashMessage', (new Flash(content: __('admin/flash.lang.delete_permanent_lang', [
                'locales' => implode(', ', config('localization.permanent_locales'))
            ]), type: FlashType::ERROR))->get());
        }

        $localeData->delete();

        (new Languages())->refreshCache();

        try {
            (new CDLocaleAction($localeData->alpha_2_code))->deleteLocale();

            return redirect()->route('admin.lang.index')->with('flashMessage', (new Flash(content: __('admin/flash.lang.delete_lang_success')))->get());
        } catch (Exception $e) {
            return redirect()->route('admin.lang.index')->with('flashMessage', (new Flash(content: $e->getMessage(), type: FlashType::ERROR))->get());
        }
    }

    public function show(int $localeId)
    {
        $localeData = Locale::findOrFail($localeId);

        return view('admin::lang.show.index', [
            'localeData' => $localeData
        ]);
    }

    public function enable(int $localeId)
    {
        $localeData = Locale::where('status', false)->findOrFail($localeId);

        $localeData->update([
            'status' => true
        ]);

        (new Languages())->refreshCache();

        return back()->with('flashMessage', (new Flash(content: __('admin/flash.lang.enable_lang_success')))->get());
    }

    public function makeDefault(int $localeId)
    {
        $localeData = Locale::where([
            'status' => true,
            'is_default' => false
        ])->findOrFail($localeId);

        $localeData->update([
            'is_default' => true
        ]);

        Locale::whereNot('id', $localeId)->update([
            'is_default' => false
        ]);

        (new Languages())->refreshCache();
        
        return back()->with('flashMessage', (new Flash(content: __('admin/flash.lang.make_default_lang_success', ['lang' => $localeData->name])))->get());
    }

    public function disable(int $localeId)
    {
        $localeData = Locale::findOrFail($localeId);

        if($localeData->is_default) {
            return back()->with('flashMessage', (new Flash(content: __('admin/flash.lang.disable_default_lang'), type: FlashType::ERROR))->get());
        }
        
        (new Languages())->refreshCache();

        $localeData->update([
            'status' => false
        ]);

        User::where('language', $localeData->alpha_2_code)->update([
            'language' => (new Languages())->getDefaultLanguageAlpha2Code()
        ]);

        return back()->with('flashMessage', (new Flash(content: __('admin/flash.lang.disable_lang_success')))->get());
    }
}
