@php
    $appName = config('app.name');
    $effectiveDate = 'July 26, 2026';
@endphp

<div class="legal-doc">
    <section class="legal-doc-hero">
        <span class="legal-doc-kicker">Legal Terms</span>
        <h1>Terms of Use</h1>
        <p class="mt-4 text-par-l leading-8 text-lab-pr2">
            These Terms of Use govern access to and use of {{ $appName }}, including its website, mobile experiences,
            community feed, messaging tools, stories, bookmarks, marketplace, jobs board, business tools, wallet
            features, advertising tools, verification workflows, and related APIs or support features made available by
            the platform.
        </p>
        <p class="mt-4 text-par-m leading-7 text-lab-sc">
            By creating an account, browsing public areas, publishing content, or using any service inside
            {{ $appName }}, you agree to these Terms. If you do not agree, you should not access or use the platform.
        </p>
        <div class="legal-doc-meta">
            <span><strong>Effective date:</strong> {{ $effectiveDate }}</span>
            <span><strong>Applies to:</strong> web, mobile, API, business, and community services</span>
            <span><strong>Related documents:</strong> Privacy Policy and Cookies Policy</span>
        </div>
    </section>

    <section class="legal-doc-grid">
        <div class="legal-doc-card">
            <h3>What these Terms cover</h3>
            <p>
                These Terms explain the rules for using the platform, what you may publish, how paid and business
                features work, how moderation decisions are handled, and what rights {{ $appName }} needs in order to
                host and operate the service.
            </p>
        </div>
        <div class="legal-doc-card">
            <h3>Main platform areas</h3>
            <p>
                The service may include profiles, follows, posts, comments, reactions, stories, messaging, groups,
                saved items, marketplace listings, job listings, ads, wallet activity, verification, authorship
                requests, and future product modules released under the same platform name.
            </p>
        </div>
    </section>

    <section class="legal-doc-section">
        <h2>1. Eligibility and account responsibility</h2>
        <p>
            You may use {{ $appName }} only if you can lawfully enter into a binding agreement under the laws that
            apply to you. If local law requires parental or guardian consent for younger users, you must obtain that
            consent before using the platform.
        </p>
        <ul>
            <li>You are responsible for keeping your login credentials secure and for activity performed through your account.</li>
            <li>You must provide accurate registration, profile, payment, verification, and business information.</li>
            <li>You may not impersonate another person, brand, organization, or government body.</li>
            <li>You may not transfer or sell an account in a way that misleads users, advertisers, or platform administrators.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>2. Community, content, and conduct rules</h2>
        <p>
            {{ $appName }} is designed for lawful social interaction and commerce. You remain responsible for the
            content you upload, send, publish, store, or otherwise make available on the platform.
        </p>
        <ul>
            <li>Do not publish unlawful, deceptive, abusive, defamatory, hateful, sexually exploitative, or violent content.</li>
            <li>Do not upload malware, harmful code, automated spam, or tools intended to scrape, disrupt, or abuse the service.</li>
            <li>Do not infringe copyrights, trademarks, privacy rights, publicity rights, or trade secret protections.</li>
            <li>Do not use direct messages, comments, stories, groups, or mentions for scams, harassment, phishing, or extortion.</li>
            <li>Do not attempt to bypass visibility settings, moderation actions, verification decisions, or account restrictions.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>3. Your content and the license you grant</h2>
        <p>
            You retain ownership of the content you create and submit to {{ $appName }}. However, in order to operate
            the service, you grant {{ $appName }} a non-exclusive, worldwide, royalty-free license to host, store,
            reproduce, format, process, translate, display, distribute, and otherwise use that content solely as
            needed to run, secure, improve, promote, moderate, and support the platform.
        </p>
        <p>
            This license ends within a commercially reasonable period after the content is permanently removed from the
            service, except where retention is required for backups, fraud prevention, legal compliance, dispute
            handling, or enforcement of these Terms.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>4. Marketplace, jobs, ads, and business tools</h2>
        <p>
            If you use business or commercial features, you are solely responsible for the legality, accuracy, and
            completeness of your listings, offers, promotions, and related communications.
        </p>
        <ul>
            <li>Marketplace listings must describe real, lawful goods or services and must not include prohibited or counterfeit items.</li>
            <li>Job posts must be truthful, non-discriminatory where required by law, and must not misrepresent pay, duties, or hiring conditions.</li>
            <li>Ads, campaigns, and sponsored content must comply with applicable advertising, consumer, and disclosure laws.</li>
            <li>Business accounts may be reviewed, limited, or removed if trust, compliance, or safety concerns arise.</li>
            <li>Submission of a listing or campaign does not guarantee publication, approval, traffic, sales, applications, or conversion results.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>5. Wallet, payments, withdrawals, and paid features</h2>
        <p>
            Some areas of {{ $appName }} may involve wallet balances, payment providers, deposits, transfers,
            withdrawals, tips, paid promotion, or other paid features. These services may be processed by third-party
            providers and may be subject to additional provider terms, verification checks, transaction limits, fees,
            reversals, fraud review, and payout delays.
        </p>
        <ul>
            <li>You must use your own authorized payment methods and accurate payout details.</li>
            <li>Wallet balances are platform balances and are not bank deposits, insured accounts, or investment products.</li>
            <li>We may delay, reject, reverse, or hold transactions to investigate fraud, chargebacks, abuse, sanctions, or legal compliance issues.</li>
            <li>You are responsible for taxes, invoicing duties, and reporting obligations related to your commercial activity.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>6. Verification, authorship, and platform trust signals</h2>
        <p>
            {{ $appName }} may offer verification, authorship, business status, profile badges, moderation labels, or
            other trust-related indicators. These signals are granted at the platform's discretion and may be revoked,
            limited, or adjusted if supporting facts change.
        </p>
        <ul>
            <li>You may be required to submit identity, business, social presence, or supporting documentation.</li>
            <li>Submitting documents does not guarantee approval.</li>
            <li>False, altered, expired, or misleading verification material may lead to rejection, suspension, or permanent removal.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>7. Moderation, reports, enforcement, and account actions</h2>
        <p>
            To protect users and the platform, {{ $appName }} may investigate content, listings, transactions,
            messages, reports, or account behavior. We may take action without prior notice when reasonably necessary
            for safety, fraud prevention, legal compliance, or service integrity.
        </p>
        <ul>
            <li>Actions may include warning, visibility reduction, rejection, demonetization, content removal, feature limits, suspension, or termination.</li>
            <li>Content may remain unavailable while under review or while an approval workflow is pending.</li>
            <li>We may preserve evidence and audit records related to abuse, reports, financial disputes, or legal requests.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>8. Intellectual property and platform materials</h2>
        <p>
            The platform software, layout, branding, designs, code, interfaces, databases, documentation, moderation
            systems, and other platform-owned materials are protected by copyright, trademark, trade dress, and other
            intellectual property laws. Except where we clearly allow it, you may not copy, reverse engineer,
            redistribute, resell, or create derivative services from platform materials.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>9. Third-party services and external content</h2>
        <p>
            {{ $appName }} may integrate with or link to payment processors, identity services, login providers,
            websites, app stores, analytics tools, maps, media hosts, or other external services. We are not
            responsible for the availability, security, policies, or content of third-party services you choose to use
            or visit.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>10. Availability, changes, and disclaimers</h2>
        <p>
            {{ $appName }} is provided on an "as is" and "as available" basis. We may modify, improve, pause, or
            discontinue features at any time. We do not promise uninterrupted service, permanent availability of any
            feature, or that all content, messages, wallet functions, business tools, or notifications will always
            operate without delay or error.
        </p>
        <p>
            To the maximum extent allowed by law, we disclaim implied warranties of merchantability, fitness for a
            particular purpose, non-infringement, and uninterrupted performance.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>11. Limitation of liability</h2>
        <p>
            To the fullest extent allowed by law, {{ $appName }} and its operators will not be liable for indirect,
            incidental, special, consequential, exemplary, or punitive damages, or for lost profits, lost revenue,
            lost data, business interruption, reputational harm, or losses arising from user content, listings,
            transactions, moderation, scams, third-party conduct, or unauthorized account access.
        </p>
        <p>
            If liability cannot be excluded, it will be limited to the amount you paid directly to the platform for the
            specific service giving rise to the claim during the 3 months before the event, or the minimum amount
            required by applicable law if no such payment was made.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>12. Suspension, termination, and survival</h2>
        <p>
            You may stop using the platform at any time. We may suspend or terminate access to all or part of the
            service if you violate these Terms, create risk for users or the platform, fail verification checks, misuse
            payment tools, abuse moderation systems, or where continuing to provide the service would create legal or
            operational risk.
        </p>
        <p>
            Provisions regarding payments, licenses, moderation records, intellectual property, disclaimers, liability,
            and dispute-related retention survive termination to the extent reasonably required.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>13. Changes to these Terms and contact</h2>
        <p>
            We may update these Terms from time to time to reflect product changes, legal requirements, security needs,
            or operational improvements. The revised version becomes effective when posted unless a later date is
            stated. Your continued use of {{ $appName }} after the update means you accept the revised Terms.
        </p>
        <p>
            Questions about these Terms can be directed through the
            <a href="{{ route('document.help.index') }}" class="legal-doc-anchor">Help Center</a>
            or the contact details published on the
            <a href="{{ route('document.about.index') }}" class="legal-doc-anchor">About Project</a>
            page.
        </p>
    </section>

    <div class="legal-doc-note">
        This Terms page should be kept aligned with your actual moderation, payment, verification, and commercial
        workflows. If you enable new regulated features or operate in a jurisdiction with strict consumer or privacy
        rules, a local legal review is strongly recommended before production launch.
    </div>
</div>
