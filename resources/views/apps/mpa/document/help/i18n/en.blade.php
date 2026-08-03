@php
    $appName = config('app.name');
@endphp

<div class="legal-doc">
    <section class="legal-doc-hero">
        <span class="legal-doc-kicker">Support & Guidance</span>
        <h1>Help Center</h1>
        <p class="mt-4 text-par-l leading-8 text-lab-pr2">
            Welcome to the {{ $appName }} Help Center. This page explains the main areas users typically need help
            with, from account access and security to content publishing, messaging, marketplace, jobs, wallet actions,
            and verification.
        </p>
    </section>

    <section class="legal-doc-grid">
        <div class="legal-doc-card">
            <h3>Fastest place to start</h3>
            <p>
                If something looks wrong, first confirm whether the issue is related to login, visibility settings,
                pending approval, moderation, or a temporary payment or delivery delay. Many platform actions depend on
                review state, account status, and feature access.
            </p>
        </div>
        <div class="legal-doc-card">
            <h3>Best support approach</h3>
            <p>
                When reporting a problem, include your username, the page or feature involved, what you expected to
                happen, what actually happened, and whether the issue affects desktop, mobile, or both.
            </p>
        </div>
    </section>

    <section class="legal-doc-section">
        <h2>1. Account access and security</h2>
        <ul>
            <li>Use your correct email and password when signing in.</li>
            <li>If login fails, confirm your account credentials and any required verification step.</li>
            <li>Review active sessions and security settings if you suspect unauthorized access.</li>
            <li>Keep your password private and rotate it immediately after any suspicious activity.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>2. Profile, privacy, and notifications</h2>
        <p>
            You can update key account information from settings, including personal information, social links, privacy
            preferences, blocked users, and notification controls. Some fields affect how people find or contact you,
            while others control what remains visible on your profile.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>3. Posts, comments, stories, and bookmarks</h2>
        <p>
            If content does not appear as expected, check whether it is still processing, subject to moderation, or
            limited by privacy settings. Stories may also expire automatically according to platform behavior. Saved and
            bookmarked content depends on the relevant item still being available on the platform.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>4. Messages and media attachments</h2>
        <p>
            Direct messaging features may include files, previews, reactions, typing signals, and read states. If a
            message or attachment does not appear, it may be delayed by connectivity, file processing, moderation, or a
            visibility rule inside the related chat. If the problem continues, capture the chat location and time of
            the issue before requesting support.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>5. Marketplace and jobs support</h2>
        <ul>
            <li>Listings and jobs may remain pending until admin approval is completed.</li>
            <li>Rejected or removed items may be hidden from public discovery areas.</li>
            <li>Incorrect pricing, title, visibility, or approval state should be checked from the business account area first.</li>
            <li>If an item was deleted, its public listing and related approval presence may also disappear.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>6. Wallet, payments, and withdrawals</h2>
        <p>
            Wallet and payment features may be affected by provider responses, review checks, transfer limits,
            withdrawal thresholds, or administrative controls. If a balance, payout, or transaction status looks wrong,
            collect the transaction time, amount, and related wallet or payment details before escalating the issue.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>7. Verification and authorship requests</h2>
        <p>
            Verification and authorship requests are reviewed based on platform rules and supporting materials. Approval
            is not automatic. If a request remains pending or is declined, confirm that the submitted information is
            accurate, current, and complete.
        </p>
        <p>
            You can review the public requirements on the
            <a href="{{ route('document.verification.index') }}" class="legal-doc-anchor">Verification Rules</a>
            page.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>8. Reports, abuse, and account restrictions</h2>
        <p>
            Users can report harmful or violating behavior through the platform's reporting flows. Depending on severity
            and evidence, enforcement may include warnings, removal, rejection, visibility limits, or account-level
            action. Reports should be factual and specific to help investigation move faster.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>9. Useful policy pages</h2>
        <ul>
            <li><a href="{{ route('document.terms.index') }}" class="legal-doc-anchor">Terms of Use</a></li>
            <li><a href="{{ route('document.privacy.index') }}" class="legal-doc-anchor">Privacy Policy</a></li>
            <li><a href="{{ route('document.cookies.index') }}" class="legal-doc-anchor">Cookies Policy</a></li>
            <li><a href="{{ route('document.account-deletion.index') }}" class="legal-doc-anchor">Account Deletion</a></li>
            <li><a href="{{ route('document.child-safety.index') }}" class="legal-doc-anchor">Child Safety Standards</a></li>
            <li><a href="{{ route('document.about.index') }}" class="legal-doc-anchor">About Project</a></li>
            <li><a href="{{ route('document.developers.index') }}" class="legal-doc-anchor">Developers API</a></li>
        </ul>
    </section>

    <div class="legal-doc-note">
        For production use, you can later replace this general Help Center with a more operational version that includes
        live support channels, ticket SLAs, business escalation paths, and region-specific compliance guidance.
    </div>
</div>
