@extends('adminLayout::index')

@section('pageContent')
	<div class="mb-4">
		<x-page-title titleText=" {{ __('admin/config.index_title') }}"></x-page-title>
	</div>
    <div class="grid grid-cols-1 gap-8">
        <div class="col-span-1">
            <x-navbar title="{{ __('admin/config.tabs.brand_seo') }}">
                <x-navbar.item href="{{ route('admin.config.branding') }}" icon="design-01">
                    {{ __('admin/sidebar.branding') }}
                </x-navbar.item>
                <x-navbar.item href="{{ route('admin.config.code-injection') }}" icon="code-02">
                    {{ __('admin/sidebar.custom_code') }}
                </x-navbar.item>
            </x-navbar>
        </div>

        <div class="col-span-1">
            <x-navbar title="{{ __('admin/config.tabs.system') }}">
                <x-navbar.item href="{{ route('admin.config.general') }}" icon="settings-01">
                    {{ __('admin/sidebar.general_settings') }}
                </x-navbar.item>

                <x-navbar.item href="{{ route('admin.config.email') }}" icon="mail-01">
                    {{ __('admin/sidebar.email_settings') }}
                </x-navbar.item>
                <x-navbar.item href="{{ route('admin.config.auth') }}" icon="user-plus-01">
                    {{ __('admin/sidebar.auth_settings') }}
                </x-navbar.item>
                <x-navbar.item href="{{ route('admin.config.social-login') }}" icon="login-01">
                    {{ __('admin/sidebar.social_login') }}
                </x-navbar.item>

                <x-navbar.item href="{{ route('admin.config.api') }}" icon="code-01">
                    {{ __('admin/sidebar.api_settings') }}
                </x-navbar.item>
                <x-navbar.item href="{{ route('admin.lang.index') }}" icon="globe-01">
                    {{ __('admin/sidebar.languages') }}
                </x-navbar.item>
                <x-navbar.item href="{{ route('admin.currency.index') }}" icon="banknote-01">
                    {{ __('admin/sidebar.currency') }}
                </x-navbar.item>
                <x-navbar.item href="{{ route('admin.categories.index') }}" icon="tag-01">
                    {{ __('admin/sidebar.categories') }}
                </x-navbar.item>

                <x-navbar.item href="{{ route('admin.pages.index') }}" icon="doc-01">
                    {{ __('admin/sidebar.static_pages') }}
                </x-navbar.item>

                <x-navbar.item href="{{ route('admin.config.wallet') }}" icon="wallet-01">
                    {{ __('admin/sidebar.wallet_settings') }}
                </x-navbar.item>
                <x-navbar.item href="{{ route('admin.config.mobile-apps') }}" icon="phone-01">
                    {{ __('admin/sidebar.mobile_app_settings') }}
                </x-navbar.item>
            </x-navbar>
        </div>
        <div class="col-span-1">
            <x-navbar title="{{ __('admin/config.tabs.storage') }}">
                <x-navbar.item href="{{ route('admin.storage.index') }}" icon="cloud-01">
                    {{ __('admin/sidebar.file_storage') }}
                </x-navbar.item>

                <x-navbar.item href="{{ route('admin.config.backup') }}" icon="database-01">
                    {{ __('admin/sidebar.db_backup') }}
                </x-navbar.item>
                <x-navbar.item href="{{ route('admin.config.ffmpeg') }}" icon="camera-01">
                    {{ __('admin/sidebar.ffmpeg_settings') }}
                </x-navbar.item>
            </x-navbar>
        </div>
        <div class="col-span-1">
            <x-navbar title="{{ __('admin/config.tabs.notifications') }}">
                <x-navbar.item href="{{ route('admin.config.notifications') }}" icon="bell-01">
                    {{ __('admin/sidebar.notifications') }}
                </x-navbar.item>
            </x-navbar>
        </div>
    </div>
@endsection
