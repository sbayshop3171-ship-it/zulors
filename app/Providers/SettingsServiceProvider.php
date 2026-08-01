<?php

namespace App\Providers;

use App\Settings\{AppSettings, FFMPegSettings, MailSettings, GoogleLoginSettings, BrandSettings, AuthSettings, CodeSettings, WalletSettings};
use App\Settings\Acquiring\{PaypalSettings, StripeSettings, YooKassaSettings, RobokassaSettings};
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        try {
            $this->mergeAppSettings();
            $this->mergeMailSettings();
            $this->mergeFFMpegSettings();
            $this->mergeAcquiringSettings();
            $this->mergeGoogleLoginSettings();
            $this->mergeWalletSettings();
            $this->mergeAuthSettings();
            $this->mergeCodeSettings();
            $this->mergeBrandSettings();
        } catch (\Throwable $th) {
            //
        }
    }

    private function mergeAppSettings(): void
    {
        $appSettings = app(AppSettings::class);

        config([
            'app.name' => $appSettings->name,
            'app.description' => $appSettings->description,
            'app.keywords' => $appSettings->keywords,
            'app.documentation_url' => $appSettings->documentation_url,
            'app.timezone' => $appSettings->timezone,
            'app.locale' => $appSettings->locale,
            'app.fallback_locale' => $appSettings->fallback_locale,
            'app.faker_locale' => $appSettings->faker_locale,
            'app.admin_locale' => $appSettings->admin_locale,
            'app.admin_prefix' => $appSettings->admin_prefix,
            'app.salt' => $appSettings->salt,
            'app.api_key' => $appSettings->api_key,
            'app.version' => $appSettings->version,
            'app.default_currency' => $appSettings->default_currency,
            'app.mobile_app_android_link' => $appSettings->mobile_app_android_link,
            'app.mobile_app_ios_link' => $appSettings->mobile_app_ios_link,
            'app.pwa_enabled' => $appSettings->pwa_enabled,
            'app.hide_sensitive_data' => $appSettings->hide_sensitive_data,
        ]);

        App::setLocale($appSettings->locale);
        App::setFallbackLocale($appSettings->fallback_locale);
    }

    private function mergeMailSettings(): void
    {
        $mailSettings = app(MailSettings::class);
        $mailTransport = $mailSettings->transport;

        if (App::environment('local')) {
            $mailTransport = config('mail.default', $mailTransport);
        }

        config([
            'mail.default' => $mailTransport,
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $mailSettings->host,
            'mail.mailers.smtp.port' => $mailSettings->port,
            'mail.mailers.smtp.timeout' => $mailSettings->timeout,
            'mail.mailers.smtp.username' => $mailSettings->username,
            'mail.mailers.smtp.password' => $mailSettings->password,
            'mail.mailers.smtp.encryption' => $mailSettings->encryption,
            'mail.mailers.smtp.from_address' => $mailSettings->from_address,
            'mail.mailers.smtp.from_name' => $mailSettings->from_name,
            'mail.mailers.smtp.local_domain' => $mailSettings->local_domain,
            'mail.mailers.brevo_api.transport' => 'brevo_api',
            'mail.mailers.brevo_api.key' => $mailSettings->brevo_api_key,
            'mail.mailers.brevo_api.timeout' => $mailSettings->timeout,
            'mail.from.address' => $mailSettings->from_address,
            'mail.from.name' => $mailSettings->from_name,
        ]);
    }

    private function mergeFFMpegSettings(): void
    {
        $ffmpegSettings = app(FFMPegSettings::class);

        config([
            'ffmpeg.ffmpeg_path' => $ffmpegSettings->ffmpeg_path,
            'ffmpeg.ffprobe_path' => $ffmpegSettings->ffprobe_path,
            'ffmpeg.timeout' => $ffmpegSettings->timeout,
            'ffmpeg.threads' => $ffmpegSettings->threads,
            'ffmpeg.temporary_directory' => $ffmpegSettings->temporary_directory,
        ]);
    }

    private function mergeAcquiringSettings(): void
    {
        $stripeSettings = app(StripeSettings::class);
        $paypalSettings = app(PaypalSettings::class);
        $ykSettings = app(YooKassaSettings::class);
        $rkSettings = app(RobokassaSettings::class);

        config([
            'payment.providers.stripe.name' => $stripeSettings->stripe_name,
            'payment.providers.stripe.driver' => $stripeSettings->getDriver(),
            'payment.providers.stripe.logo' => $stripeSettings->getLogo(),
            'payment.providers.stripe.status' => $stripeSettings->stripe_status,
            'payment.providers.stripe.credentials.secret_key' => $stripeSettings->stripe_secret_key,
            'payment.providers.stripe.credentials.public_key' => $stripeSettings->stripe_public_key,
            'payment.providers.stripe.webhook.webhook_tolerance' => $stripeSettings->stripe_webhook_tolerance,
            'payment.providers.stripe.webhook.secret' => $stripeSettings->stripe_webhook_secret,
            'payment.providers.stripe.payment_method_types' => $this->normalizeCsvList($stripeSettings->stripe_payment_method_types),
            'payment.providers.stripe.redirect_route' => $stripeSettings->getRedirectRoute(),
            'payment.providers.stripe.cancel_route' => $stripeSettings->getCancelRoute(),
        ]);

        config([
            'payment.providers.paypal.name' => $paypalSettings->paypal_name,
            'payment.providers.paypal.driver' => $paypalSettings->getDriver(),
            'payment.providers.paypal.logo' => $paypalSettings->getLogo(),
            'payment.providers.paypal.status' => $paypalSettings->paypal_status,
            'payment.providers.paypal.credentials.client_id' => $paypalSettings->paypal_client_id,
            'payment.providers.paypal.credentials.secret_key' => $paypalSettings->paypal_secret_key,
            'payment.providers.paypal.mode' => $paypalSettings->paypal_mode,
            'payment.providers.paypal.webhook' => $paypalSettings->paypal_webhook,
            'payment.providers.paypal.redirect_route' => $paypalSettings->getRedirectRoute(),
            'payment.providers.paypal.cancel_route' => $paypalSettings->getCancelRoute(),
        ]);

        config([
            'payment.providers.yoo_kassa.driver' => $ykSettings->getDriver(),
            'payment.providers.yoo_kassa.name' => $ykSettings->yk_name,
            'payment.providers.yoo_kassa.logo' => $ykSettings->getLogo(),
            'payment.providers.yoo_kassa.webhook' => $ykSettings->yk_webhook,
            'payment.providers.yoo_kassa.status' => $ykSettings->yk_status,
            'payment.providers.yoo_kassa.credentials.shop_id' => $ykSettings->yk_shop_id,
            'payment.providers.yoo_kassa.credentials.secret_key' => $ykSettings->yk_secret_key,
            'payment.providers.yoo_kassa.redirect_route' => $ykSettings->getRedirectRoute(),
            'payment.providers.yoo_kassa.cancel_route' => $ykSettings->getCancelRoute(),
            'payment.providers.yoo_kassa.currency' => $ykSettings->yk_currency,
        ]);

        config([
            'payment.providers.robokassa.name' => $rkSettings->rk_name,
            'payment.providers.robokassa.driver' => $rkSettings->getDriver(),
            'payment.providers.robokassa.logo' => $rkSettings->getLogo(),
            'payment.providers.robokassa.status' => $rkSettings->rk_status,
            'payment.providers.robokassa.credentials.merchant_login' => $rkSettings->rk_merchant_login,
            'payment.providers.robokassa.credentials.pass_one' => $rkSettings->rk_pass_one,
            'payment.providers.robokassa.credentials.pass_two' => $rkSettings->rk_pass_two,
            'payment.providers.robokassa.currency' => $rkSettings->rk_currency,
            'payment.providers.robokassa.mode' => $rkSettings->rk_mode,
            'payment.providers.robokassa.webhook' => $rkSettings->rk_webhook,
            'payment.providers.robokassa.redirect_route' => $rkSettings->getRedirectRoute(),
            'payment.providers.robokassa.cancel_route' => $rkSettings->getCancelRoute(),
        ]);
    }

    private function normalizeCsvList(string $value): array
    {
        $items = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_map(
            fn (string $item) => strtolower(trim($item)),
            $items
        )));
    }

    private function mergeGoogleLoginSettings(): void
    {
        $googleLoginSettings = app(GoogleLoginSettings::class);

        config([
            'social-login.providers.google.enabled' => $googleLoginSettings->enabled,
            'social-login.providers.google.credentials.client_id' => $googleLoginSettings->client_id,
            'social-login.providers.google.credentials.client_secret' => $googleLoginSettings->client_secret,
            'social-login.providers.google.credentials.redirect' => $googleLoginSettings->getCallbackPath(),
            'social-login.providers.google.meta.name' => $googleLoginSettings->getName(),
            'social-login.providers.google.meta.url' => $googleLoginSettings->getRedirectRoute(),
            'social-login.providers.google.meta.logo' => $googleLoginSettings->getLogo(),
        ]);
    }

    private function mergeWalletSettings(): void
    {
        $walletSettings = app(WalletSettings::class);

        config([
            'features.wallet.enabled' => $walletSettings->enabled
        ]);

        config([
            'wallet.name' => $walletSettings->name,
            'wallet.enabled' => $walletSettings->enabled,
            'wallet.about_link' => $walletSettings->about_link,
            'wallet.wallet_number_prefix' => $walletSettings->wallet_number_prefix,
            'wallet.default_balance' => $walletSettings->default_balance,
            'wallet.deposit.min_amount' => $walletSettings->deposit_min_amount,
            'wallet.deposit.max_amount' => $walletSettings->deposit_max_amount,
            'wallet.transfer.min_amount' => $walletSettings->transfer_min_amount,
            'wallet.transfer.max_amount' => $walletSettings->transfer_max_amount,
            'wallet.commission.deposit' => $walletSettings->commission_deposit,
            'wallet.commission.transfer' => $walletSettings->commission_transfer,
            'wallet.commission.withdraw' => $walletSettings->commission_withdraw,
            'wallet.withdraw.min_amount' => $walletSettings->withdraw_min_amount,
            'wallet.withdraw.max_amount' => $walletSettings->withdraw_max_amount,
            'wallet.cashout.methods' => explode(',', $walletSettings->cashout_methods),
        ]);
    }

    private function mergeAuthSettings(): void
    {
        $authSettings = app(AuthSettings::class);

        config([
            'auth_settings.captcha_enabled' => $authSettings->captcha_enabled,
            'auth_settings.registration_enabled' => $authSettings->registration_enabled,
            'auth_settings.login_enabled' => $authSettings->login_enabled,
            'auth_settings.reg_verification_enabled' => $authSettings->reg_verification_enabled,
            'auth_settings.auto_verification_enabled' => $authSettings->auto_verification_enabled,
            'auth_settings.reg_verification_type' => $authSettings->reg_verification_type,
            'auth_settings.switch_account_enabled' => $authSettings->switch_account_enabled,
            'auth_settings.link_accounts_enabled' => $authSettings->link_accounts_enabled,
        ]);

        config([
            'features.registration.enabled' => $authSettings->registration_enabled,
            'features.login.enabled' => $authSettings->login_enabled,
            'features.reg_verification.enabled' => $authSettings->reg_verification_enabled,
            'features.auto_verification.enabled' => $authSettings->auto_verification_enabled,
            'features.switch_account.enabled' => $authSettings->switch_account_enabled,
            'features.link_accounts.enabled' => $authSettings->link_accounts_enabled,
        ]);
    }

    private function mergeCodeSettings(): void
    {
        $codeSettings = app(CodeSettings::class);

        config([
            'code.header_code' => $codeSettings->header_code,
            'code.footer_code' => $codeSettings->footer_code,
            'code.header_code_enabled' => $codeSettings->header_code_enabled,
            'code.footer_code_enabled' => $codeSettings->footer_code_enabled,
        ]);
    }

    private function mergeBrandSettings(): void
    {
        $brandSettings = app(BrandSettings::class);

        config([
            'brand.dark_theme_enabled' => $brandSettings->dark_theme_enabled,
            'brand.default_theme' => $brandSettings->default_theme,
            'brand.images_watermark_enabled' => $brandSettings->images_watermark_enabled,
            'brand.videos_watermark_enabled' => $brandSettings->videos_watermark_enabled,
        ]);

        config([
            'features.dark_theme.enabled' => $brandSettings->dark_theme_enabled,
        ]);
    }
}
