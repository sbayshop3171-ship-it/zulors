<style>
    :root {
        background: #ffffff;
    }

    html,
    body {
        min-height: 100%;
        background: #ffffff;
    }

    #zulors-mobile-app,
    #zulors-desktop-app {
        min-height: 100dvh;
        background: #ffffff;
    }

    .zulors-boot-shell {
        min-height: 100dvh;
        background: #ffffff;
        color: #111827;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .zulors-boot-shell,
    .zulors-boot-shell * {
        box-sizing: border-box;
    }

    .zulors-boot-shell--desktop {
        min-width: 1200px;
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
        color: #6b7280;
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
