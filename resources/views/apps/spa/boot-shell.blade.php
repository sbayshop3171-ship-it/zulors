@php
    $bootVariant = $variant ?? 'mobile';
@endphp

@if($bootVariant === 'desktop')
    <div class="zulors-boot-shell zulors-boot-shell--desktop" role="status" aria-label="Loading Zulors">
        <aside class="zulors-boot-desktop-sidebar" aria-hidden="true">
            <div class="zulors-boot-dot"></div>
            <div class="zulors-boot-nav-item"></div>
            <div class="zulors-boot-nav-item"></div>
            <div class="zulors-boot-nav-item"></div>
            <div class="zulors-boot-nav-item"></div>
            <div class="zulors-boot-nav-item"></div>
        </aside>

        <main class="zulors-boot-desktop-main">
            <header class="zulors-boot-desktop-header">Home</header>

            <div class="zulors-boot-feed">
                <div class="zulors-boot-story" aria-hidden="true">
                    <span class="zulors-boot-avatar"></span>
                    <span class="zulors-boot-line zulors-boot-line--short"></span>
                </div>

                @include('apps.spa.boot-shell-post')
                @include('apps.spa.boot-shell-post')
            </div>
        </main>

        <aside class="zulors-boot-desktop-aside" aria-hidden="true">
            <div class="zulors-boot-title">Zulors</div>
            <div class="zulors-boot-suggestion">
                <span class="zulors-boot-avatar"></span>
                <span class="zulors-boot-line"></span>
            </div>
            <div class="zulors-boot-suggestion">
                <span class="zulors-boot-avatar"></span>
                <span class="zulors-boot-line"></span>
            </div>
            <div class="zulors-boot-suggestion">
                <span class="zulors-boot-avatar"></span>
                <span class="zulors-boot-line"></span>
            </div>
        </aside>
    </div>
@else
    <div class="zulors-boot-shell zulors-boot-shell--mobile" role="status" aria-label="Loading Zulors">
        <header class="zulors-boot-mobile-header">
            <span class="zulors-boot-line zulors-boot-line--short" aria-hidden="true"></span>
            <span class="zulors-boot-title">Zulors</span>
            <span class="zulors-boot-dot" aria-hidden="true"></span>
        </header>

        <main class="zulors-boot-feed">
            <div class="zulors-boot-story" aria-hidden="true">
                <span class="zulors-boot-avatar"></span>
                <span class="zulors-boot-line zulors-boot-line--short"></span>
            </div>

            @include('apps.spa.boot-shell-post')
            @include('apps.spa.boot-shell-post')
        </main>
    </div>
@endif
