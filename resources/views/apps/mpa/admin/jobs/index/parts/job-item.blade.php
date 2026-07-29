<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		<x-table.avatar :avatarSrc="$jobData->user->avatar_url" :name="$jobData->user->name" :link="route('admin.users.show', $jobData->user->id)" />
	</x-table.td>
	<x-table.td variant="strong" weight="medium">
		<a href="{{ route('admin.jobs.show', $jobData->id) }}" class="hover:underline">
			{{ truncate_text($jobData->title, 22) }}
		</a>
	</x-table.td>
	<x-table.td variant="muted">
		{{ $jobData->category_name }}
	</x-table.td>
	<x-table.td variant="muted">
		<x-badge variant="{{ $jobData->approval->badgeVariant() }}">
			{{ $jobData->approval->label() }} {{ $jobData->approval->emoji() }}
		</x-badge>
	</x-table.td>
	<x-table.td variant="muted">
		<x-badge variant="{{ $jobData->status->badgeVariant() }}">
			{{ $jobData->status->label() }} {{ $jobData->status->emoji() }}
		</x-badge>
	</x-table.td>
	<x-table.td variant="money" weight="medium">
		{{ $jobData->formatted_income }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $jobData->created_at->getDate() }}
	</x-table.td>
	<x-table.td variant="muted" numeric>
		{{ $jobData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end items-center gap-2">
			@if($jobData->approval->isPending())
				<x-ui.buttons.pill
					x-on:click="approveJob('{{ route('admin.jobs.approve', $jobData->id) }}')"
					size="xs"
					btnText="{{ __('admin/dd.job.approve') }}"
				></x-ui.buttons.pill>
				<x-ui.buttons.pill
					x-on:click="rejectJob('{{ route('admin.jobs.reject', $jobData->id) }}')"
					variant="danger"
					size="xs"
					btnText="{{ __('admin/dd.job.reject') }}"
				></x-ui.buttons.pill>
			@endif
			<a href="{{ route('admin.jobs.show', $jobData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>
