@extends('emails.layouts.main')

@section('email_content')
    <x-emails.title>
        {{ __('email.greeting', ['user_name' => $notifiable->name]) }}
    </x-emails.title>

    <x-emails.spacer space="12"></x-emails.spacer>
    <x-emails.par>
        <b>
			{{ $data['actor']['name'] }}
		</b>
		<span>
			{{ __(join('.', ['notifications', $data['message_group'], $data['message_key']]), $data['message_params']) }}.
		</span>

		@if(in_array($notificationType, ['post.reacted', 'comment.reacted']))
			<img style="width: 20px; vertical-align: middle; display: inline;" src="{{ reaction_image_url($data['metadata']['reaction_unified_id']) }}" alt="Emoji">
		@endif
    </x-emails.par>
	
	@if(isset($data['entity']['content']))
		<x-emails.par>
			«{{ $data['entity']['content'] }}»
		</x-emails.par>
	@endif

	@if(isset($data['entity']['preview_lqip_base64']))
		<x-emails.par>
			<img style="width: 42px; height: 42px; object-fit: cover; border-radius: 4px;" src="{{ $data['entity']['preview_lqip_base64'] }}" alt="Image">
		</x-emails.par>
	@endif

	@if(isset($destinationLink))
		<x-emails.spacer space="32"></x-emails.spacer>
		<x-emails.par>
			<div style="text-align: center;">
				<x-emails.action :href="$destinationLink">
					{{ __('email.actions.view', locale: $locale) }}
				</x-emails.action>
			</div>
		</x-emails.par>
	@endif

    <x-emails.spacer space="12"></x-emails.spacer>
    <x-emails.par>
        {{ __('email.keep_best_experience', ['app_name' => config('app.name')]) }}
    </x-emails.par>
    <x-emails.spacer space="12"></x-emails.spacer>
    <x-emails.par>
        {{ __('email.regards') }},
        {{ __('email.team_caption', ['app_name' => config('app.name')]) }}
    </x-emails.par>
@endsection