<?php
use App\Models\Confirmation;
use App\Models\User;
use App\Services\Currency\Fiat\FiatCurrencyService;
use App\Services\Language\LangDetectionService;
use App\Services\World\WorldService;
use App\Support\Languages;
use Hashids\Hashids;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (!function_exists('me')) {
    function me(): User
    {
        return auth()->user();
    }
}

if (!function_exists('auth_check')) {
    function auth_check()
    {
        return auth()->check();
    }
}

if (!function_exists('normalize_nls')) {
    function normalize_nls(string $text)
    {
        return Str::replaceMatches('/(\r?\n){3,}/', "\n\n", $text);
    }
}

if (!function_exists('theme_name')) {
    function theme_name()
    {
        return theme_runtime_name();
    }
}

if (!function_exists('theme_preference_name')) {
    function theme_preference_name()
    {
        $theme = config('brand.default_theme');

        if (auth_check()) {
            $theme = me()->theme;
        }

        elseif (Cookie::has('theme')) {
            $theme = Cookie::get('theme');
        }

        elseif (Session::has('theme')) {
            $theme = Session::get('theme');
        }

        return in_array($theme, ['light', 'dark', 'system']) ? $theme : config('brand.default_theme');
    }
}

if (!function_exists('theme_runtime_name')) {
    function theme_runtime_name(?string $theme = null)
    {
        $theme = $theme ?? theme_preference_name();

        if ($theme === 'system') {
            if (Cookie::has('theme_runtime')) {
                $theme = Cookie::get('theme_runtime');
            } elseif (Session::has('theme_runtime')) {
                $theme = Session::get('theme_runtime');
            } else {
                $theme = config('brand.default_theme');
            }
        }

        return in_array($theme, ['light', 'dark']) ? $theme : config('brand.default_theme');
    }
}

if (!function_exists('store_theme_preference')) {
    function store_theme_preference(string $theme, ?string $resolvedTheme = null)
    {
        $theme = in_array($theme, ['light', 'dark', 'system']) ? $theme : config('brand.default_theme');

        if ($theme === 'system') {
            $resolvedTheme = in_array($resolvedTheme, ['light', 'dark']) ? $resolvedTheme : theme_runtime_name();
        } else {
            $resolvedTheme = $theme;
        }

        if (auth_check()) {
            me()->update([
                'theme' => $theme
            ]);
        }

        Cookie::queue('theme', $theme, 60 * 24 * 365 * 3);
        Cookie::queue('theme_runtime', $resolvedTheme, 60 * 24 * 365 * 3);

        session()->put('theme', $theme);
        session()->put('theme_runtime', $resolvedTheme);

        return $resolvedTheme;
    }
}

if (!function_exists('route_is')) {
    function route_is(string $name = ''): bool|string
    {
        return request()->routeIs($name);
    }
}

if (!function_exists('world_countries')) {
    function world_countries()
    {
        $worldService = app(WorldService::class);

        return collect($worldService->countries())->map(function($value, $key) {
            return [
                'key' => $key,
                'value' => $value,
            ];
        })->values()->toArray();
    }
}

if (!function_exists('birth_years')) {
    function birth_years()
    {
        $currentYear = Carbon::now()->year;
        $birthYears = [];

        for ($year = $currentYear; $year >= $currentYear - 70; $year--) {
            $birthYears[] = [
                'key' => (string) $year,
                'value' => (string) $year,
            ];
        }

        return $birthYears;
    }
}

if (!function_exists('month_days')) {
    function month_days($month, $year = null)
    {
        $year = $year ?? Carbon::now()->year;
        $daysInMonth = Carbon::createFromDate($year, $month)->daysInMonth;

        $daysArray = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $daysArray[] = [
                'key' => (string) $day,
                'value' => (string) $day,
            ];
        }

        return $daysArray;
    }
}

if (!function_exists('year_months')) {
    function year_months()
    {
        return [
            ['key' => '01', 'value' => __('labels.months.01')],
            ['key' => '02', 'value' => __('labels.months.02')],
            ['key' => '03', 'value' => __('labels.months.03')],
            ['key' => '04', 'value' => __('labels.months.04')],
            ['key' => '05', 'value' => __('labels.months.05')],
            ['key' => '06', 'value' => __('labels.months.06')],
            ['key' => '07', 'value' => __('labels.months.07')],
            ['key' => '08', 'value' => __('labels.months.08')],
            ['key' => '09', 'value' => __('labels.months.09')],
            ['key' => '10', 'value' => __('labels.months.10')],
            ['key' => '11', 'value' => __('labels.months.11')],
            ['key' => '12', 'value' => __('labels.months.12')]
        ];
    }
}

if (!function_exists('confirmation_unique_code')) {
    function confirmation_unique_code()
    {
        do {
            $code = random_int(100000, 999999);
            $exists = Confirmation::where('code', $code)->exists();
        } while ($exists);

        return $code;
    }
}

if (!function_exists('storage_url')) {
    function storage_url($path, $disk = 'public') {
        return Storage::disk($disk)->url($path);
    }
}

if (!function_exists('storage_local_path')) {
    function storage_local_path($path) {
        return Storage::disk('local')->path($path);
    }
}

if (!function_exists('is_positive')) {
    function is_positive($number = 0) {
        return (is_numeric($number) && $number >= 1);
    }
}

if (!function_exists('parse_duration')) {
    function parse_duration($durationInSeconds = 0) {
        return [
            'hours' => sprintf('%02d', intval(floor($durationInSeconds / 3600))),
            'minutes' => sprintf('%02d', intval(floor(($durationInSeconds % 3600) / 60))),
            'seconds' => sprintf('%02d', intval($durationInSeconds % 60))
        ];
    }
}

if (!function_exists('reaction_image_url')) {
    function reaction_image_url(string $reactionUnifiedId) {
        return asset("assets/emoji/img-apple-64/{$reactionUnifiedId}.png");
    }
}

if (! function_exists('encode_id')) {
    function encode_id(int $id): string
    {
        $hashids = new Hashids(config('app.salt'), 16);
        return $hashids->encode($id);
    }
}

if (! function_exists('decode_id')) {
    function decode_id(string $hash): ?int
    {
        $hashids = new Hashids(config('app.salt'), 16);
        $decoded = $hashids->decode($hash);

        return isset($decoded[0]) ? $decoded[0] : null;
    }
}

if (! function_exists('data_get_integer')) {
    function data_get_integer(array $data, string $key, int $default = 0)
    {
        $value = data_get($data, $key, $default);

        if(is_positive($value)) {
            return $value;
        }

        return $default;
    }
}

if (! function_exists('detect_text_language')) {
    function detect_text_language(string $text) {
        return (new LangDetectionService($text))->detect();
    }
}

if (! function_exists('payment_log')) {
    function payment_log(string $message, array $context = []) {
        Log::channel('payment')->info($message, $context);
    }
}

if (! function_exists('story_log')) {
    function story_log(string $message, array $context = []) {
        Log::channel('story')->info($message, $context);
    }
}

if (! function_exists('timeline_log')) {
    function timeline_log(string $message, array $context = []) {
        Log::channel('timeline')->info($message, $context);
    }
}

if (! function_exists('requests_log')) {
    function requests_log(string $message, array $context = []) {
        Log::channel('requests')->info($message, $context);
    }
}

if (! function_exists('chat_log')) {
    function chat_log(string $message, array $context = []) {
        Log::channel('chat')->info($message, $context);
    }
}

if (! function_exists('default_currency')) {
    function default_currency() {

        $fiatCurrencyService = app(FiatCurrencyService::class);
        $currencyData = $fiatCurrencyService->getCurrencyData(config('app.default_currency'));

        if (! empty($currencyData)) {
            return $currencyData->toArray();
        }

        return [
            'alpha_3_code' => config('app.default_currency', 'USD'),
            'name' => 'United States dollar',
            'symbol' => '$',
            'symbol_native' => '$',
            'status' => true
        ];
    }
}

if (! function_exists('var_path')) {
    function var_path(string $path) {
        return base_path("var/{$path}");
    }
}

if (! function_exists('truncate_text')) {
    function truncate_text(string $text, int $length = 50) {
        return Str::limit($text, $length);
    }
}

if (! function_exists('available_locales')) {
    function available_locales() {
        return (new Languages())->getLanguages()->pluck('alpha_2_code')->toArray();
    }
}

if (! function_exists('file_size_format')) {
    function file_size_format($size, $precision = 2) {
        if ($size === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $base = log($size, 1024);

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
    }
}

if (! function_exists('social_links')) {
    function social_links() {
        $socialLinks = require(var_path('config/user/social_links.php'));

        return collect($socialLinks)->where('status', true)->toArray();
    }
}

if (! function_exists('static_storage_disk')) {
    function static_storage_disk() {
        return config('filesystems.static_storage_disk');
    }
}

if (! function_exists('hide_email_address')) {
    function hide_email_address(string $email): string
    {
        if (config('app.hide_sensitive_data') && ! me()->isRoot()) {
            [$name, $domain] = explode('@', $email);

            $visibleName = mb_substr($name, 0, 2);
            $maskedName = mb_substr(md5($name), 0, max(mb_strlen($name) - 2, 4));

            $domainParts = explode('.', $domain);
            $visibleDomain = mb_substr($domainParts[0], 0, 1);
            $maskedDomain = mb_substr(md5($domain), 0, max(mb_strlen($domainParts[0]) - 1, 4));
            $tld = end($domainParts);

            return "{$visibleName}{$maskedName}@{$visibleDomain}{$maskedDomain}.{$tld}";
        }

        return $email;
    }
}

if (! function_exists('calculate_commission')) {
    function calculate_commission(float $amount, float $commission): float
    {
        return floor($amount - ($amount * $commission / 100));
    }
}
