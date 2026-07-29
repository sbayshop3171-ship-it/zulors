<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		<x-table.avatar :avatarSrc="$paymentData->user->avatar_url" :name="$paymentData->user->name" :link="route('admin.users.show', $paymentData->user->id)" />
	</x-table.td>
	<x-table.td variant="money">
		{{ $paymentData->formatted_amount }}
	</x-table.td>
	<x-table.td variant="strong" weight="medium">
		{{ $paymentData->payment_type->label() }} {{ $paymentData->payment_type->emoji() }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $paymentData->provider_name }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $paymentData->status->label() }} {{ $paymentData->status->emoji() }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $paymentData->created_at->getDate() }}
	</x-table.td>
	<x-table.td variant="muted" numeric>
		{{ $paymentData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.payments.show', $paymentData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>