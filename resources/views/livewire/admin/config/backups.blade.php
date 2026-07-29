<div>
    <div wire:click="createBackup" @if($this->isProcessing) wire:poll.60s="loadBackups" @endif class="border-2 border-dashed border-bord-sc hover:border-bord-card smoothing cursor-pointer rounded-2xl px-4 py-6 mb-8">
        <div class="text-center">
            <div class="text-lab-pr flex justify-center mb-2">
                <div class="size-6 text-green-900 {{ $this->isProcessing ? 'animate-pulse' : '' }}">
                    <x-ui-icon name="database-01" type="solar"></x-ui-icon>
                </div>
            </div>
            <h5 class="text-lab-pr2 font-medium text-title-3 mb-1 {{ $this->isProcessing ? 'animate-pulse' : '' }}">
                @if($this->isProcessing)
                    {{ __('admin/config.callout.backup_processing.title') }}
                @else
                    {{ __('admin/config.callout.new_backup.title') }}
                @endif
            </h5>
            <p class="text-lab-sc text-par-n tracking-normal">
                @if($this->isProcessing)
                    {{ __('admin/config.callout.backup_processing.caption') }}
                @else
                    {{ __('admin/config.callout.new_backup.caption') }}
                @endif
            </p>
        </div>
    </div>

    @if($this->hasError)
        <div class="mb-4">
            <x-form.valerr>{{ $this->error }}</x-form.valerr>
        </div>
    @endif

    <div class="mb-2">
        <h5 class="text-lab-pr2 font-semibold text-par-l">{{ __('admin/config.backup_history') }}</h5>
    </div>

    <x-table.table>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.name') }}</x-table.th>
			<x-table.th>{{ __('table.labels.created_at') }}</x-table.th>
            <x-table.th>{{ __('table.labels.size') }}</x-table.th>
            <x-table.th></x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($this->backups->isNotEmpty())
				@foreach ($this->backups as $backupData)
                    <x-table.tr>
                        <x-table.td>
                            {{ $backupData['name'] }}
                        </x-table.td>
                        <x-table.td>
                            {{ $backupData['date'] }}
                        </x-table.td>
                        <x-table.td variant="strong" weight="medium">
                            {{ $backupData['size'] }}
                        </x-table.td>
                        <x-table.td>
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.buttons.icon :disabled="$this->isDownloading" wire:click="downloadBackup('{{ $backupData['name'] }}')" iconName="download-01" iconType="line"></x-ui.buttons.icon>
                                <x-ui.buttons.icon :disabled="$this->isDownloading" wire:click="deleteBackup('{{ $backupData['path'] }}')" iconName="trash-04" color="danger" iconType="line"></x-ui.buttons.icon>
                            </div>
                        </x-table.td>
                    </x-table.tr>
				@endforeach
			@else
				<x-table.empty colspan="4"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>
</div>
