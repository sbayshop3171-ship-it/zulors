<article class="business-ads-card">
    <a href="{{ route('business.ads.show', ['adId' => $adData->id]) }}" class="business-ads-card-media">
        <img src="{{ $adData->preview_image_url }}" alt="{{ $adData->title }}">
    </a>

    <div class="business-ads-card-body">
        <a href="{{ route('business.ads.show', ['adId' => $adData->id]) }}" class="business-ads-card-title">
            {{ $adData->title }}
        </a>

        <p class="business-ads-card-content">
            {{ $adData->content }}
        </p>

        <div class="business-ads-card-badges">
        @if($adData->approval->isPending())
                <span class="business-ads-badge">{{ $adData->approval->label() }} {{ $adData->approval->emoji() }}</span>
        @else
                <span class="business-ads-badge">{{ $adData->status->label() }} {{ $adData->status->emoji() }}</span>
        @endif
        </div>

        <div class="business-ads-card-stats">
            <div>
                <span>{{ __('business/ads.spent_budget') }}</span>
                <strong>{{ $adData->formatted_spent_budget }}</strong>
            </div>

            <div>
                <span>{{ __('business/ads.total_budget') }}</span>
                <strong>{{ $adData->formatted_total_budget }}</strong>
            </div>

            <div>
                <span>{{ __('labels.views') }}</span>
                <strong>{{ $adData->formatted_views_count }}</strong>
            </div>

            <div>
                <span>{{ __('business/ads.clicks') }}</span>
                <strong>{{ $adData->formatted_clicks_count }}</strong>
            </div>

            <div>
                <span>{{ __('business/ads.remaining_budget') }}</span>
                <strong>{{ $adData->formatted_remaining_budget }}</strong>
            </div>

            <div>
                <span>ID</span>
                <strong>{{ $adData->formatted_id }}</strong>
            </div>
        </div>

        <div class="business-ads-card-footer">
            <span>{{ __('business/ads.ad_from', ['date' => $adData->created_at->getFormatted()]) }}</span>

            <div class="business-ads-card-actions">
                <a href="{{ route('business.ads.show', ['adId' => $adData->id]) }}" class="business-ads-card-action" aria-label="{{ __('business/ads.show_title') }}">
                    <span class="business-ads-card-action-icon">
                        <x-ui-icon type="line" name="layout-alt-02"></x-ui-icon>
                    </span>
                </a>

                <a href="{{ route('business.ads.edit', ['adId' => $adData->id]) }}" class="business-ads-card-action" aria-label="{{ __('business/ads.edit_title') }}">
                    <span class="business-ads-card-action-icon">
                        <x-ui-icon type="line" name="edit-03"></x-ui-icon>
                    </span>
                </a>
            </div>
        </div>
    </div>
</article>
