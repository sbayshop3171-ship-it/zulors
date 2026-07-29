<?php

namespace App\Http\Controllers\Admin\Config;

use Throwable;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ConfigController extends Controller
{
    public function index()
    {
        return view('admin::config.index');
    }

    public function branding()
    {
        return view('admin::config.branding.index');
    }

    public function codeInjection()
    {
        return view('admin::config.code.index');
    }

    public function general()
    {
        return view('admin::config.general.index');
    }

    public function ffmpeg()
    {
        $tab = request()->input('tab', 'ffmpeg');
        $tab = in_array($tab, ['ffmpeg', 'ffmpeg-test']) ? $tab : 'ffmpeg';

        return view('admin::config.ffmpeg.index', [
            'tab' => $tab,
        ]);
    }

    public function email()
    {
        $tab = request()->input('tab', 'email');
        $tab = in_array($tab, ['email', 'testing']) ? $tab : 'email';

        return view('admin::config.email.index', [
            'tab' => $tab,
        ]);
    }

    public function notifications()
    {
        return view('admin::config.notifications.index');
    }

    public function api()
    {
        return view('admin::config.api.index');
    }

    public function verification()
    {
        return view('admin::config.verification.index');
    }

    public function emailTesting(Request $request)
    {
        $validator = Validator::make([
            'email' => $request->email,
        ], [
            'email' => ['required', 'email'],
        ], attributes: [
            'email' => __('admin/config.email_testing.form.email'),
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $email = $request->input('email');

        try {
            Mail::raw(__('email.testing.body', ['app_name' => config('app.name')]), function ($message) use ($email) {
                $message->to($email)->subject(__('email.testing.subject', ['app_name' => config('app.name')]));
            });

            return redirect()->back()->with('flashMessage', (new Flash(content: __('admin/flash.email_testing.send_success')))->get());
        } catch (Throwable $th) {
            return redirect()->back()->withErrors([
                'email' => $th->getMessage(),
            ]);
        }
    }

    public function socialLogin()
    {
        return view('admin::social.google.index');
    }

    public function wallet()
    {
        return view('admin::config.wallet.index');
    }

    public function auth()
    {
        return view('admin::config.auth.index');
    }

    public function mobileApps()
    {
        $tab = request()->route('tab', 'mobile');
        $tab = in_array($tab, ['mobile', 'pwa']) ? $tab : 'mobile';

        return view('admin::config.mobile.index', [
            'tab' => $tab,
        ]);
    }

    public function backup()
    {
        return view('admin::config.backup.index');
    }
}
