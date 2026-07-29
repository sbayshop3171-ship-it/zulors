<h3 class="text-par-m font-bold text-lab-pr2 mb-4">
    {{ __('business/wallet.cashouts_title') }}
</h3>

<div class="space-y-3 md:hidden">
    @forelse ($cashouts as $cashout)
        <article class="rounded-2xl border border-bord-sc bg-bg-pr p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-cap-s font-medium text-lab-sc">
                        {{ __('table.labels.amount') }}
                    </span>
                    <strong class="block text-title-3 font-bold text-lab-pr2">
                        {{ $cashout->formatted_amount }}
                    </strong>
                </div>

                <x-badge variant="{{ $cashout->status->badgeVariant() }}">
                    {{ $cashout->status->label() }}
                </x-badge>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-par-s">
                <div class="min-w-0">
                    <span class="block text-lab-sc">
                        {{ __('table.labels.method') }}
                    </span>
                    <strong class="block truncate text-lab-pr2">
                        {{ $cashout->payment_method }}
                    </strong>
                </div>

                <div class="min-w-0 text-right">
                    <span class="block text-lab-sc">
                        {{ __('table.labels.commission') }}
                    </span>
                    <strong class="block truncate text-lab-pr2">
                        {{ $cashout->formatted_commission_fee }}
                    </strong>
                </div>

                <div class="min-w-0">
                    <span class="block text-lab-sc">
                        {{ __('table.labels.created_at') }}
                    </span>
                    <strong class="block truncate text-lab-pr2">
                        {{ $cashout->created_at->getFormatted() }}
                    </strong>
                </div>

                <div class="min-w-0 text-right">
                    <span class="block text-lab-sc">
                        {{ __('table.labels.request_code') }}
                    </span>
                    <strong class="block truncate text-lab-pr2">
                        {{ $cashout->request_code }}
                    </strong>
                </div>
            </div>
        </article>
    @empty
        <div class="rounded-2xl border border-bord-sc bg-bg-pr px-6 py-12 text-center">
            <h4 class="text-par-m font-bold text-lab-pr2">
                {{ __('business/table.empty') }}
            </h4>
            <p class="mt-1 text-par-s text-lab-sc">
                {{ __('business/wallet.cashouts_title') }}
            </p>
        </div>
    @endforelse
</div>

<div class="hidden md:block">
    <x-table.table>
        <x-table.thead>
            <x-table.th>
                {{ __('table.labels.amount') }}
            </x-table.th>
            <x-table.th>
                {{ __('table.labels.method') }}
            </x-table.th>
            <x-table.th>
                {{ __('table.labels.status') }}
            </x-table.th>
            <x-table.th>
                {{ __('table.labels.commission') }}
            </x-table.th>
            <x-table.th>
                {{ __('table.labels.created_at') }}
            </x-table.th>
            <x-table.th>
                {{ __('table.labels.request_code') }}
            </x-table.th>
        </x-table.thead>
        <x-table.tbody>
            @if($cashouts->isNotEmpty())
                @foreach ($cashouts as $cashout)
                    <x-table.tr>
                        <x-table.td variant="strong" weight="medium">
                            {{ $cashout->formatted_amount }}
                        </x-table.td>
                        <x-table.td>
                            {{ $cashout->payment_method }}
                        </x-table.td>

                        <x-table.td bgFill="bg-fill-pr">
                            <x-badge variant="{{ $cashout->status->badgeVariant() }}">
                                {{ $cashout->status->label() }}
                            </x-badge>
                        </x-table.td>

                        <x-table.td variant="muted">
                            {{ $cashout->formatted_commission_fee }}
                        </x-table.td>
                        <x-table.td variant="muted">
                            {{ $cashout->created_at->getFormatted() }}
                        </x-table.td>
                        <x-table.td variant="strong" weight="medium">
                            {{ $cashout->request_code }}
                        </x-table.td>
                    </x-table.tr>
                @endforeach
            @else
                <x-table.empty colspan="7"></x-table.empty>
            @endif
        </x-table.tbody>
    </x-table.table>
</div>

@unless($cashouts->isEmpty())
    <div class="mt-4">
        {{ $cashouts->onEachSide(1)->withQueryString()->links('pagination.index') }}
    </div>
@endif
