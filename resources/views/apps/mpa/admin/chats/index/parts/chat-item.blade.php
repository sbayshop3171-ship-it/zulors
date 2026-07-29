<x-table.tr>
	<x-table.td>
		<a href="{{ route('admin.chats.show', $chatData->id) }}" class="hover:underline">
			@if($chatData->type->isGroup())
				{{ ($chatData->group) ? $chatData->group->name : __('labels.unknown') }} - {{ __('labels.chat_type_labels.group') }}
			@else
				@if($chatData->participants->count())
					@foreach($chatData->participants as $participant)
						{{ ($participant->user) ? $participant->user->name : __('labels.unknown') }}
						@if(! $loop->last)
							<span class="text-gray-500">, </span>
						@endif
					@endforeach

					- {{ __('labels.chat_type_labels.direct') }}
				@else
					{{ __('labels.unknown') }}
				@endif
			@endif
		</a>
	</x-table.td>
	<x-table.td>
		<a href="{{ route('admin.chats.show', $chatData->id) }}" class="hover:underline">
			{{ $chatData->type->label() }} {{ $chatData->type->emoji() }}
		</a>
	</x-table.td>
	
	<x-table.td numeric>
		{{ $chatData->participants_count }}
	</x-table.td>
	<x-table.td numeric>
		{{ $chatData->messages_count }}
	</x-table.td>
	<x-table.td variant="muted">
		@if($chatData->last_activity)
			{{ $chatData->last_activity->getFormatted() }}
		@else
			{{ __('labels.never') }}
		@endif
	</x-table.td>
	<x-table.td numeric>
		{{ $chatData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.chats.show', $chatData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>