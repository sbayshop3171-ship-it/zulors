@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/users.wallet_title') }}"></x-page-title>

    <x-sided-content>
		<x-slot:sideContent>
			<x-entity.previews.user :userData="$userData"></x-entity.previews.user>
		</x-slot:sideContent>

        <div class="mb-8">
            <x-entity.header
                avatarUrl="{{ $userData->avatar_url }}"
                name="{{ $userData->name }}"
                caption="{{ $userData->caption }}">
            </x-entity.header>
        </div>

        @livewire('admin.user.wallet', [
            'userData' => $userData
        ])
    </x-sided-content>
@endsection
