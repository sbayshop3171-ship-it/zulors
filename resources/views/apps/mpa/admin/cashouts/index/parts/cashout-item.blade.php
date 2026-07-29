<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		<x-table.avatar :avatarSrc="$cashoutData->user->avatar_url" :name="$cashoutData->user->name" :link="route('admin.users.show', $cashoutData->user->id)" />
	</x-table.td>
	<x-table.td variant="strong" weight="bold">
        <a href="{{ route('admin.cashouts.show', $cashoutData->id) }}" class="underline text-brand-900">
            {{ $cashoutData->request_code }}
        </a>
	</x-table.td>
	<x-table.td variant="money">
		{{ $cashoutData->formatted_amount }}
	</x-table.td>
	<x-table.td variant="strong" weight="medium">
		{{ $cashoutData->payment_method }}
	</x-table.td>
	<x-table.td bgFill="bg-fill-pr">
		<x-badge variant="{{ $cashoutData->status->badgeVariant() }}">
			{{ $cashoutData->status->label() }}
		</x-badge>
	</x-table.td>
	<x-table.td variant="muted">
		{{ $cashoutData->currency }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $cashoutData->created_at->getFormatted() }}
	</x-table.td>
    <x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.cashouts.show', $cashoutData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>
