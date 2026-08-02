@php
    $appName = config('app.name');
    $effectiveDate = 'July 26, 2026';
@endphp

<div class="legal-doc">
    <section class="legal-doc-hero">
        <span class="legal-doc-kicker">Cookies & Storage</span>
        <h1>Cookies Policy</h1>
        <p class="mt-4 text-par-l leading-8 text-lab-pr2">
            This Cookies Policy explains how {{ $appName }} uses cookies, local storage, session storage, and similar
            technologies to operate core platform features, improve security, remember preferences, and support product
            delivery across web and mobile web experiences.
        </p>
        <div class="legal-doc-meta">
            <span><strong>Effective date:</strong> {{ $effectiveDate }}</span>
            <span><strong>Related documents:</strong> Terms of Use and Privacy Policy</span>
        </div>
    </section>

    <section class="legal-doc-grid">
        <div class="legal-doc-card">
            <h3>Why these technologies matter</h3>
            <p>
                Some platform features cannot work correctly without session and device-level storage. This includes
                authentication state, preferences, security controls, language selection, and parts of the in-app
                browsing experience.
            </p>
        </div>
        <div class="legal-doc-card">
            <h3>What this page covers</h3>
            <p>
                This page describes the main categories of storage technologies used by the platform and how they may
                affect functionality, security, personalization, analytics, and user experience.
            </p>
        </div>
    </section>

    <section class="legal-doc-section">
        <h2>1. Types of technologies we use</h2>
        <ul>
            <li><strong>Essential cookies:</strong> help maintain sessions, authentication, request routing, and service reliability.</li>
            <li><strong>Preference storage:</strong> remembers choices such as theme, locale, interface behavior, and selected settings.</li>
            <li><strong>Security storage:</strong> supports fraud detection, CSRF protection, abuse prevention, and session integrity.</li>
            <li><strong>Performance or diagnostic storage:</strong> helps us understand errors, load behavior, and feature stability.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>2. How cookies are used on {{ $appName }}</h2>
        <p>
            Cookies and similar storage tools may be used to keep users signed in, maintain platform state while moving
            between pages, load desktop and mobile features correctly, protect message and account actions, support
            wallet or payment interactions, and make sure marketplace, jobs, and settings modules behave consistently.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>3. Third-party technologies</h2>
        <p>
            Some third-party services connected to {{ $appName }} may also place or read their own cookies or storage
            values when needed to provide functions such as payments, analytics, embedded media, social login, fraud
            review, or verification workflows. Their use is governed by their own policies and service terms.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>4. Browser controls and limitations</h2>
        <p>
            Most browsers allow users to manage or block cookies. However, blocking essential cookies or wiping session
            storage may prevent sign-in, break settings persistence, interrupt wallet or payment actions, remove saved
            state, or reduce platform security. If you disable these technologies, parts of {{ $appName }} may not work
            correctly.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>5. Relationship to our Privacy Policy</h2>
        <p>
            Information collected through cookies and similar storage may be linked to account, device, or usage data
            as described in the
            <a href="{{ route('document.privacy.index') }}" class="legal-doc-anchor">Privacy Policy</a>.
            That policy explains how we use, share, retain, and protect personal information collected through the
            platform.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>6. Changes to this policy</h2>
        <p>
            We may update this Cookies Policy when product functionality, legal obligations, or platform infrastructure
            changes. The latest version posted on this page is the version that applies unless a later effective date
            is explicitly stated.
        </p>
    </section>

    <div class="legal-doc-note">
        This policy is designed for the current product architecture. If you later add analytics dashboards, consent
        banners, ad networks, or regional cookie-management rules, this page should be expanded to match that final
        setup.
    </div>
</div>
