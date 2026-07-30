@php
    $bootVariant = $variant ?? 'mobile';
@endphp

@if($bootVariant === 'desktop')
    <div class="zulors-boot-shell zulors-boot-shell--desktop" role="status" aria-label="Loading Zulors">
        <span class="zulors-boot-corner zulors-boot-corner--left">Hi, there 👋</span>
        <span class="zulors-boot-corner zulors-boot-corner--right">Just a moment...</span>
        <img src="{{ $logotypeUrl }}" alt="{{ config('app.name') }}" class="zulors-boot-logo">
    </div>
@else
    <div class="zulors-boot-shell zulors-boot-shell--mobile" role="status" aria-label="Loading Zulors">
        <img src="{{ $logotypeUrl }}" alt="{{ config('app.name') }}" class="zulors-boot-logo">
    </div>
@endif
