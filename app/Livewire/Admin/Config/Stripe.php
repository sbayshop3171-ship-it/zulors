<?php

namespace App\Livewire\Admin\Config;

use App\Settings\Acquiring\StripeSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class Stripe extends Component
{
    public array $formData = [];
    public array $providerInfo = [];
    public string $webhookEndpoint = '';
    private const ALLOWED_PAYMENT_METHOD_TYPES = [
        'card',
        'acss_debit',
        'affirm',
        'afterpay_clearpay',
        'alipay',
        'au_becs_debit',
        'bacs_debit',
        'bancontact',
        'blik',
        'boleto',
        'cashapp',
        'crypto',
        'customer_balance',
        'eps',
        'fpx',
        'giropay',
        'grabpay',
        'ideal',
        'klarna',
        'konbini',
        'link',
        'mb_way',
        'multibanco',
        'oxxo',
        'p24',
        'pay_by_bank',
        'paynow',
        'paypal',
        'payto',
        'pix',
        'promptpay',
        'sepa_debit',
        'sofort',
        'swish',
        'upi',
        'us_bank_account',
        'wechat_pay',
        'revolut_pay',
        'mobilepay',
        'zip',
        'scalapay',
        'amazon_pay',
        'alma',
        'twint',
        'kr_card',
        'naver_pay',
        'kakao_pay',
        'payco',
        'nz_bank_account',
        'samsung_pay',
        'billie',
        'bizum',
        'paypay',
        'satispay',
        'sunbit',
    ];

    public function mount()
    {
        $stripeSettings = app(StripeSettings::class);

        $this->formData = [
            'name' => $stripeSettings->stripe_name,
            'status' => $stripeSettings->stripe_status,
            'secret_key' => $stripeSettings->stripe_secret_key,
            'public_key' => $stripeSettings->stripe_public_key,
            'webhook_secret' => $stripeSettings->stripe_webhook_secret,
            'webhook_tolerance' => $stripeSettings->stripe_webhook_tolerance,
            'payment_method_types' => $stripeSettings->stripe_payment_method_types,
        ];

        $this->providerInfo = [
            'name' => $stripeSettings->stripe_name,
            'logo' => $stripeSettings->getLogo(),
        ];

        $this->webhookEndpoint = url('/payment/stripe/webhook');
    }

    public function render()
    {
        return view('livewire.admin.config.stripe');
    }

    public function submitForm()
    {
        $paymentMethodTypes = $this->normalizePaymentMethodTypes($this->formData['payment_method_types'] ?? '');

        $this->validate([
            'formData.name' => ['required', 'string', 'max:255'],
            'formData.status' => ['required', 'boolean'],
            'formData.secret_key' => ['nullable', 'string', 'max:1200'],
            'formData.public_key' => ['nullable', 'string', 'max:1200'],
            'formData.webhook_secret' => ['nullable', 'string', 'max:1200'],
            'formData.payment_method_types' => ['required', 'string', 'max:255'],
        ], attributes: [
            'formData.name' => __('admin/config.form.provider_name'),
            'formData.status' => __('admin/config.form.provider_status'),
            'formData.secret_key' => __('admin/config.form.secret_key'),
            'formData.public_key' => __('admin/config.form.public_key'),
            'formData.webhook_secret' => __('admin/config.form.webhook_secret'),
            'formData.payment_method_types' => __('admin/config.form.payment_method_types'),
        ]);

        $invalidMethodTypes = array_diff($paymentMethodTypes, self::ALLOWED_PAYMENT_METHOD_TYPES);

        if (empty($paymentMethodTypes)) {
            $this->addError('formData.payment_method_types', __('admin/config.validation.stripe_payment_methods_required'));

            return;
        }

        if (! empty($invalidMethodTypes)) {
            $this->addError('formData.payment_method_types', __('admin/config.validation.stripe_payment_methods_invalid', [
                'methods' => implode(', ', $invalidMethodTypes)
            ]));

            return;
        }

        $stripeSettings = app(StripeSettings::class);
        $stripeSettings->stripe_name = $this->formData['name'];
        $stripeSettings->stripe_status = $this->formData['status'];
        $stripeSettings->stripe_secret_key = $this->formData['secret_key'];
        $stripeSettings->stripe_public_key = $this->formData['public_key'];
        $stripeSettings->stripe_webhook_secret = $this->formData['webhook_secret'];
        $stripeSettings->stripe_payment_method_types = implode(',', $paymentMethodTypes);
        $stripeSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.acquiring.edit', $stripeSettings->getDriver());
    }

    private function normalizePaymentMethodTypes(string $methodTypes): array
    {
        $methods = preg_split('/[\s,]+/', $methodTypes, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_map(
            fn (string $method) => strtolower(trim($method)),
            $methods
        )));
    }
}
