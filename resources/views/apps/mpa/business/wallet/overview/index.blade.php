@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/wallet.index_title') }}"></x-page-title>
	<div class="rounded-3xl border border-bord-sc bg-bg-pr p-2">
        <div class="rounded-2xl bg-fill-qt p-4 sm:p-6">
            <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="min-w-0 flex-1">
                    <p class="text-par-m text-lab-sc">
                        {{ __('business/wallet.balance_desc') }}
                    </p>
                    <h2 class="mt-1 text-4xl font-bold leading-none text-mint sm:text-5xl">
                        {{ $walletData->balance->getFormattedAmount() }}
                    </h2>
                </div>
                <div class="min-w-0 shrink-0 sm:max-w-xs" x-data="colibriUICode">
                    <div class="flex items-start gap-3 sm:text-right">
                        <div class="min-w-0 flex-1">
                            <h4 class="text-par-n font-bold text-lab-pr2 break-all" x-ref="code">{{ $walletData->wallet_number }}</h4>
                            <p class="text-par-s text-lab-sc">
                                {{ __('business/wallet.wallet_number') }}
                            </p>
                        </div>
                        <div class="shrink-0">
                            <x-ui.buttons.icon x-show="! copying" iconName="copy-06" iconType="line" color="muted" x-on:click="copy"></x-ui.buttons.icon>
                            <x-ui.buttons.icon x-show="copying" iconName="check-circle" iconType="line" color="success"></x-ui.buttons.icon>
                        </div>
                    </div>
                </div>
            </div>

            <div class="business-wallet-actions mt-8 grid grid-cols-1 gap-3 min-[360px]:grid-cols-2 sm:mt-12 sm:grid-cols-3">
                <a href="{{ url('wallet?action=deposit') }}" class="group min-h-32 rounded-3xl bg-bg-pr p-4 transition-all duration-300 ease-in-out active:bg-fill-tr sm:rounded-2xl">
                    <span class="mb-4 inline-flex size-10 items-center justify-center rounded-2xl bg-brand-900 text-white">
                        <x-ui-icon name="plus" type="solid"></x-ui-icon>
                    </span>
                    <strong class="block text-par-m font-bold leading-5 text-lab-pr2">
                        {{ __('api/wallet.deposit_money') }}
                    </strong>
                    <span class="mt-1 block text-par-s leading-5 text-lab-sc">
                        {{ __('api/wallet.add_money_to_wallet') }}
                    </span>
                </a>

                <a href="{{ url('wallet?action=transfer') }}" class="group min-h-32 rounded-3xl bg-bg-pr p-4 transition-all duration-300 ease-in-out active:bg-fill-tr sm:rounded-2xl">
                    <span class="mb-4 inline-flex size-10 items-center justify-center rounded-2xl bg-fill-pr text-brand-900">
                        <x-ui-icon name="arrow-up-right" type="line"></x-ui-icon>
                    </span>
                    <strong class="block text-par-m font-bold leading-5 text-lab-pr2">
                        {{ __('api/wallet.transfer_money') }}
                    </strong>
                    <span class="mt-1 block text-par-s leading-5 text-lab-sc">
                        {{ __('api/wallet.send_to_another') }}
                    </span>
                </a>

                <a href="{{ route('business.wallet.create-cashout') }}" class="business-wallet-action-wide group min-h-32 rounded-3xl bg-bg-pr p-4 transition-all duration-300 ease-in-out active:bg-fill-tr min-[360px]:col-span-2 sm:col-span-1 sm:rounded-2xl">
                    <span class="mb-4 inline-flex size-10 items-center justify-center rounded-2xl bg-fill-pr text-brand-900">
                        <x-ui-icon name="wallet-01" type="solar"></x-ui-icon>
                    </span>
                    <strong class="block text-par-m font-bold leading-5 text-lab-pr2">
                        {{ __('business/wallet.request_withdrawal') }}
                    </strong>
                    <span class="mt-1 block text-par-s leading-5 text-lab-sc">
                        {{ __('business/wallet.withdrawal_title') }}
                    </span>
                </a>
            </div>
        </div>
        <div class="px-4 py-4">
            <p class="text-center text-par-s leading-6 text-lab-sc">
                {!! __('business/wallet.about_wallet_text', [
                    'wallet_name' => config('wallet.name'),
                    'about_link' => config('wallet.about_link')
                ]) !!}
            </p>
        </div>
	</div>

    <div class="mt-10 pb-28 lg:mt-6 lg:pb-0">
        @include('business::wallet.overview.parts.cashouts-table', [
            'cashouts' => $cashouts
        ])
    </div>
@endsection
