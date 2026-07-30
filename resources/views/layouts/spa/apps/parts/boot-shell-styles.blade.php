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
        font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .zulors-boot-shell,
    .zulors-boot-shell * {
        box-sizing: border-box;
    }

    .zulors-boot-mobile-header {
        position: sticky;
        top: 0;
        z-index: 2;
        display: grid;
        grid-template-columns: 80px 1fr 80px;
        align-items: center;
        height: 64px;
        padding: 0 18px;
        border-bottom: 1px solid #f1f5f9;
        background: #ffffff;
    }

    .zulors-boot-title {
        justify-self: center;
        font-size: 28px;
        font-weight: 800;
        line-height: 1;
        letter-spacing: 0;
    }

    .zulors-boot-dot {
        width: 22px;
        height: 22px;
        border-radius: 999px;
        background: #111827;
    }

    .zulors-boot-feed {
        width: min(100%, 640px);
        margin: 0 auto;
        padding: 18px 18px 96px;
    }

    .zulors-boot-story {
        display: flex;
        align-items: center;
        gap: 12px;
        height: 76px;
        border-bottom: 1px solid #f1f5f9;
    }

    .zulors-boot-avatar {
        flex: 0 0 auto;
        width: 48px;
        height: 48px;
        border-radius: 999px;
        background: #e8eef7;
    }

    .zulors-boot-post {
        padding: 18px 0 24px;
        border-bottom: 1px solid #f1f5f9;
    }

    .zulors-boot-post-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    .zulors-boot-lines {
        display: grid;
        gap: 8px;
        width: 100%;
    }

    .zulors-boot-line {
        height: 12px;
        border-radius: 999px;
        background: #edf1f6;
    }

    .zulors-boot-line--short {
        width: 36%;
    }

    .zulors-boot-line--medium {
        width: 58%;
    }

    .zulors-boot-media {
        width: 100%;
        aspect-ratio: 16 / 9;
        border-radius: 8px;
        overflow: hidden;
        background: linear-gradient(90deg, #edf1f6 0%, #f8fafc 46%, #edf1f6 100%);
        background-size: 220% 100%;
        animation: zulorsBootShimmer 1.15s ease-in-out infinite;
    }

    .zulors-boot-actions {
        display: flex;
        align-items: center;
        gap: 22px;
        margin-top: 14px;
    }

    .zulors-boot-action {
        width: 22px;
        height: 22px;
        border: 2px solid #111827;
        border-radius: 999px;
    }

    .zulors-boot-shell--desktop {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) minmax(560px, 640px) minmax(280px, 1fr);
        min-width: 1200px;
    }

    .zulors-boot-desktop-sidebar {
        padding: 36px 28px;
        border-right: 1px solid #f1f5f9;
    }

    .zulors-boot-nav-item {
        width: 148px;
        height: 22px;
        margin-top: 28px;
        border-radius: 999px;
        background: #edf1f6;
    }

    .zulors-boot-desktop-main {
        min-height: 100dvh;
        border-right: 1px solid #f1f5f9;
    }

    .zulors-boot-desktop-header {
        height: 64px;
        display: flex;
        align-items: center;
        padding: 0 18px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 22px;
        font-weight: 800;
    }

    .zulors-boot-desktop-aside {
        padding: 36px 28px;
    }

    .zulors-boot-suggestion {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 22px;
    }

    .zulors-boot-suggestion .zulors-boot-line {
        width: 160px;
    }

    @keyframes zulorsBootShimmer {
        0% {
            background-position: 120% 0;
        }

        100% {
            background-position: -120% 0;
        }
    }

    @media (max-width: 1023px) {
        .zulors-boot-shell--desktop {
            display: block;
            min-width: 0;
        }

        .zulors-boot-desktop-sidebar,
        .zulors-boot-desktop-aside,
        .zulors-boot-desktop-header {
            display: none;
        }

        .zulors-boot-desktop-main {
            min-height: 100dvh;
            border-right: 0;
        }
    }
</style>
