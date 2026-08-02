@php
    $appName = config('app.name');
    $effectiveDate = 'July 26, 2026';
@endphp

<div class="legal-doc">
    <section class="legal-doc-hero">
        <span class="legal-doc-kicker">Data & Privacy</span>
        <h1>Privacy Policy</h1>
        <p class="mt-4 text-par-l leading-8 text-lab-pr2">
            This Privacy Policy explains how {{ $appName }} collects, uses, stores, protects, and shares personal
            information when people access the platform, create accounts, communicate with others, publish content,
            operate business tools, apply for verification, or use wallet and payment features.
        </p>
        <p class="mt-4 text-par-m leading-7 text-lab-sc">
            It applies to public pages, registered user areas, mobile experiences, APIs, support flows, and any other
            product surface operated under {{ $appName }}.
        </p>
        <div class="legal-doc-meta">
            <span><strong>Effective date:</strong> {{ $effectiveDate }}</span>
            <span><strong>Related documents:</strong> Terms of Use and Cookies Policy</span>
            <span><strong>Coverage:</strong> account, content, messaging, business, wallet, and support data</span>
        </div>
    </section>

    <section class="legal-doc-grid">
        <div class="legal-doc-card">
            <h3>Information this policy covers</h3>
            <p>
                We describe the personal information we collect directly from users, receive automatically from device
                activity, generate through platform operations, or receive from integrated providers such as payment,
                login, moderation, and verification services.
            </p>
        </div>
        <div class="legal-doc-card">
            <h3>Important privacy principle</h3>
            <p>
                Visibility on {{ $appName }} depends on your actions and settings. Some profile and listing data is
                intentionally public when you publish it, while other data is used only for security, verification,
                support, and legal compliance.
            </p>
        </div>
    </section>

    <section class="legal-doc-section">
        <h2>1. Information we collect</h2>
        <p>Depending on how you use {{ $appName }}, we may collect the following categories of information:</p>
        <ul>
            <li>Account and profile data such as name, username, email address, phone number, date of birth, country, city, avatar, bio, social links, privacy preferences, and notification settings.</li>
            <li>Content and communication data such as posts, comments, stories, reactions, bookmarks, follows, group activity, messages, attachments, product listings, job listings, ads, reports, and support submissions.</li>
            <li>Business and trust data such as store information, pricing, application details, authorship requests, verification documents, and moderation history.</li>
            <li>Wallet and transaction data such as wallet number, deposits, transfers, withdrawals, payment references, payment provider responses, and fraud or chargeback review outcomes.</li>
            <li>Technical and usage data such as IP address, browser type, device model, operating system, language, session activity, page interactions, approximate location signals, cookies, and diagnostic logs.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>2. How we collect information</h2>
        <p>
            We collect information when you provide it directly, when your device interacts with the platform, when
            other users engage with your content or account, and when trusted service providers send us status results
            related to payments, verification, social login, security review, or technical delivery.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>3. Why we use personal information</h2>
        <p>We use personal information to operate, improve, and protect {{ $appName }}. This includes:</p>
        <ul>
            <li>Creating and securing accounts, authenticating logins, and managing sessions across devices.</li>
            <li>Delivering the feed, stories, chats, profile pages, marketplace pages, jobs, groups, and saved content.</li>
            <li>Processing wallet activity, payments, withdrawals, refunds, fraud screening, and accounting records.</li>
            <li>Providing business features such as listings, ads, campaigns, analytics, and seller or employer workflows.</li>
            <li>Reviewing reports, enforcing platform rules, detecting abuse, preventing spam, and protecting users.</li>
            <li>Evaluating verification, authorship, and trust-related requests.</li>
            <li>Sending essential service notices, security alerts, moderation updates, and feature notifications according to your settings.</li>
            <li>Monitoring performance, debugging issues, and developing new or improved features.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>4. What may become public or visible to others</h2>
        <p>
            Some information is intended to be visible because that is how the service works. Depending on your
            settings and the feature you use, other users may see your name, username, avatar, bio, profile links,
            public posts, comments, reactions, followers, business pages, product or job listings, story activity,
            marketplace content, and any information you deliberately publish or send to them.
        </p>
        <p>
            Information submitted for payments, fraud review, identity checks, internal moderation, and administrative
            verification is generally not made public unless disclosure is required by law or necessary to defend the
            rights, safety, or integrity of the platform.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>5. How we share information</h2>
        <p>We do not sell personal information as a standalone data product. We may share information with:</p>
        <ul>
            <li>Service providers that help us host the platform, process payments, detect fraud, deliver email or notifications, store media, or verify identity and business status.</li>
            <li>Other users when your content, profile details, listings, or messages are intentionally shared through platform features.</li>
            <li>Professional advisers, auditors, insurers, or acquirers where reasonably necessary for operations, restructuring, claims, or transactions.</li>
            <li>Law enforcement or regulators when disclosure is required by law, subpoena, court order, sanctions review, anti-fraud obligations, or urgent safety concerns.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>6. Cookies, local storage, and similar technologies</h2>
        <p>
            {{ $appName }} may use cookies, session storage, local storage, and related technologies to keep you signed
            in, remember settings, improve performance, protect accounts, measure feature usage, and support product
            delivery across desktop and mobile experiences.
        </p>
        <p>
            For more detail on operational and preference-related tracking technologies, please review the
            <a href="{{ route('document.cookies.index') }}" class="legal-doc-anchor">Cookies Policy</a>.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>7. Payments, wallet activity, and verification data</h2>
        <p>
            Payment and wallet features may require additional information such as transaction identifiers, deposit or
            payout method details, payment status, anti-fraud indicators, and provider responses. Verification or
            authorship features may require identity documents, business records, public presence materials, or profile
            evidence. We use this information to review eligibility, prevent fraud, meet compliance duties, and resolve
            disputes.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>8. Data retention</h2>
        <p>
            We keep information for as long as reasonably necessary to provide the service, maintain records, detect and
            investigate abuse, handle legal obligations, enforce our Terms, and resolve disputes. Retention periods may
            differ depending on the type of data, whether the information is tied to financial activity, whether the
            content was reported, and whether a deletion request can be honored immediately.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>9. Security</h2>
        <p>
            We use administrative, technical, and organizational measures designed to reduce the risk of unauthorized
            access, misuse, alteration, or loss. No internet-based system is perfectly secure, so we cannot guarantee
            absolute security. You also play an important role by using strong passwords, controlling device access, and
            reporting suspected compromise quickly.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>10. Your choices and controls</h2>
        <p>
            Depending on the feature and your jurisdiction, you may be able to access, update, or remove certain
            profile details, manage notification and privacy settings, limit public visibility of selected information,
            disconnect social links, control marketplace or job listings, or request account closure.
        </p>
        <ul>
            <li>You can edit many account details directly from your settings screens.</li>
            <li>You can control parts of your profile visibility, direct message permissions, mentions, invites, and payment permissions through account settings.</li>
            <li>You can remove content that you own, although copies may remain in backups, legal records, or abuse review logs for a limited time.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>11. Children's privacy</h2>
        <p>
            {{ $appName }} is not intended to be used in violation of laws protecting children. If we learn that
            personal information was collected unlawfully from a child under the minimum age required by applicable law,
            we may remove the information and restrict or close the related account.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>12. Changes to this Policy and contact</h2>
        <p>
            We may update this Privacy Policy to reflect platform changes, legal requirements, new features, or
            security improvements. The updated version becomes effective when posted unless another date is stated.
        </p>
        <p>
            Questions, access requests, or privacy concerns can be directed through the
            <a href="{{ route('document.help.index') }}" class="legal-doc-anchor">Help Center</a>
            or the contact details listed on the
            <a href="{{ route('document.about.index') }}" class="legal-doc-anchor">About Project</a>
            page.
        </p>
    </section>

    <div class="legal-doc-note">
        This Privacy Policy is written to match the current platform structure, including social content, messaging,
        commerce, moderation, verification, and wallet-related features. If you later add sensitive regulated features
        or operate under stricter regional privacy regimes, a jurisdiction-specific legal review is recommended.
    </div>
</div>
