@php
    $appName = config('app.name');
    $supportEmail = config('contacts.support_email') ?: config('mail.from.address');
@endphp

<div class="legal-doc">
    <section class="legal-doc-hero">
        <span class="legal-doc-kicker">Account & Data</span>
        <h1>Account Deletion</h1>
        <p class="mt-4 text-par-l leading-8 text-lab-pr2">
            This page explains how {{ $appName }} users can request deletion of their account and associated account
            data from inside the app or from the web.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>Delete your account inside {{ $appName }}</h2>
        <p>
            If you can sign in, open Settings, go to Account Actions, enter your password, add an optional message,
            and submit Delete Account. This starts the account deletion flow for the signed-in account.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>Request deletion from the web</h2>
        <p>
            If you no longer have the app installed or cannot access your account, you can request account deletion by
            contacting support with the email address or username connected to the account.
        </p>
        <p>
            Email:
            <a href="mailto:{{ $supportEmail }}?subject={{ rawurlencode($appName . ' account deletion request') }}" class="legal-doc-anchor">
                {{ $supportEmail }}
            </a>
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>What to include</h2>
        <ul>
            <li>Your username or account email address.</li>
            <li>A clear statement that you want your {{ $appName }} account deleted.</li>
            <li>Any extra detail that helps us verify the request belongs to you.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>Data retained for limited reasons</h2>
        <p>
            Some records may be retained where reasonably required for security, fraud prevention, payment records,
            legal compliance, dispute handling, abuse review, backups, or enforcement of the Terms of Use. Public
            content, marketplace listings, messages, and wallet-related records may also be affected by operational or
            legal retention requirements described in the Privacy Policy.
        </p>
    </section>

    <div class="legal-doc-note">
        Related pages:
        <a href="{{ route('document.privacy.index') }}" class="legal-doc-anchor">Privacy Policy</a>,
        <a href="{{ route('document.terms.index') }}" class="legal-doc-anchor">Terms of Use</a>, and
        <a href="{{ route('document.help.index') }}" class="legal-doc-anchor">Help Center</a>.
    </div>
</div>
