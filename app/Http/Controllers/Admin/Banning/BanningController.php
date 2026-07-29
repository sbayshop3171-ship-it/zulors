<?php

namespace App\Http\Controllers\Admin\Banning;

use App\Models\Blacklist;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BanningController extends Controller
{
    public function index()
    {
        $blacklistedContent = Blacklist::with(['admin'])->latest('id')->paginate(10);

        return view('admin::banning.index.index', [
            'blacklistedContent' => $blacklistedContent
        ]);
    }

    public function show(int $banId)
    {
        $blacklistedContent = Blacklist::with(['admin'])->findOrFail($banId);

        return view('admin::banning.show.index', [
            'blacklistedContent' => $blacklistedContent
        ]);
    }

    public function destroy(int $banId)
    {
        $blacklistedContent = Blacklist::findOrFail($banId);

        $blacklistedContent->delete();

        return redirect()->route('admin.banning.index')->with('flashMessage', (new Flash(content: __('admin/flash.ban.delete_success')))->get());
    }
}
