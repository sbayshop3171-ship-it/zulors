@php
    $bootIsDark = theme_name() === 'dark';
    $bootBackgroundColor = $bootIsDark ? '#111111' : '#ffffff';
    $bootTextColor = $bootIsDark ? 'rgba(255, 255, 255, 0.80)' : '#111827';
    $bootMutedTextColor = $bootIsDark ? 'rgba(235, 235, 245, 0.60)' : '#6b7280';
@endphp

<style>
    :root {
        background: {{ $bootBackgroundColor }};
    }

    html,
    body {
        min-height: 100%;
        background: var(--color-bg-pr, {{ $bootBackgroundColor }});
    }

    #zulors-mobile-app,
    #zulors-desktop-app {
        min-height: 100dvh;
        background: var(--color-bg-pr, {{ $bootBackgroundColor }});
    }

    .zulors-boot-shell {
        min-height: 100dvh;
        width: 100%;
        background: var(--color-bg-pr, {{ $bootBackgroundColor }});
        color: var(--color-lab-pr2, {{ $bootTextColor }});
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
        font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .zulors-boot-shell,
    .zulors-boot-shell * {
        box-sizing: border-box;
    }

    .zulors-boot-shell--desktop {
        min-width: 1200px;
    }

    .zulors-route-loader {
        position: fixed;
        inset: 0;
        z-index: 1000;
        min-width: 0;
        background: var(--color-bg-pr, {{ $bootBackgroundColor }});
        pointer-events: none;
    }

    .zulors-boot-shell--desktop.zulors-route-loader {
        left: var(--spacing-page-offset, 340px);
        width: calc(100% - var(--spacing-page-offset, 340px));
    }

    .zulors-boot-shell--mobile.zulors-route-loader {
        top: 56px;
        bottom: calc(56px + env(safe-area-inset-bottom));
        min-height: 0;
    }

    .zulors-boot-logo {
        display: block;
        width: 64px;
        height: 64px;
        object-fit: contain;
    }

    .zulors-boot-shell--mobile .zulors-boot-logo {
        width: 40px;
        height: 40px;
    }

    .zulors-boot-corner {
        position: absolute;
        top: 24px;
        color: var(--color-lab-sc, {{ $bootMutedTextColor }});
        font-size: 14px;
        line-height: 20px;
    }

    .zulors-boot-corner--left {
        left: 24px;
    }

    .zulors-boot-corner--right {
        right: 24px;
    }
</style>
