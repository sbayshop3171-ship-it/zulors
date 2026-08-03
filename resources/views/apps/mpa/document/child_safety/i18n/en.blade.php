@php
    $appName = config('app.name');
    $configuredEmail = config('contacts.support_email') ?: config('mail.from.address');
    $safetyEmail = (blank($configuredEmail) || str_contains($configuredEmail, 'example.com'))
        ? 'oficial.vykepay18@gmail.com'
        : $configuredEmail;
    $effectiveDate = 'August 3, 2026';
@endphp

<div class="legal-doc">
    <section class="legal-doc-hero">
        <span class="legal-doc-kicker">Safety Standards</span>
        <h1>Child Safety Standards</h1>
        <p class="mt-4 text-par-l leading-8 text-lab-pr2">
            {{ $appName }} has zero tolerance for child sexual abuse and exploitation (CSAE), child sexual abuse
            material (CSAM), grooming, sextortion, sexual solicitation of minors, or any attempt to use the platform
            to harm, exploit, endanger, or sexualize children.
        </p>
        <p class="mt-4 text-par-m leading-7 text-lab-sc">
            These standards explain how {{ $appName }} prevents, reviews, removes, reports, and escalates child safety
            concerns across public content, profiles, comments, messages, media uploads, reports, and support channels.
        </p>
        <div class="legal-doc-meta">
            <span><strong>Effective date:</strong> {{ $effectiveDate }}</span>
            <span><strong>Designated contact:</strong> {{ $safetyEmail }}</span>
            <span><strong>Scope:</strong> CSAE prevention, reporting, moderation, and legal compliance</span>
        </div>
    </section>

    <section class="legal-doc-grid">
        <div class="legal-doc-card">
            <h3>Immediate reporting</h3>
            <p>
                Users can report child safety concerns from inside the app through report flows on content, profiles,
                conversations, and other user-generated areas. Reports are reviewed for urgent safety action.
            </p>
        </div>
        <div class="legal-doc-card">
            <h3>Strict enforcement</h3>
            <p>
                Confirmed violations may result in content removal, account restriction, permanent account
                termination, preservation of evidence, and reports to relevant authorities where required or allowed by
                law.
            </p>
        </div>
    </section>

    <section class="legal-doc-section">
        <h2>1. Prohibited child safety content and conduct</h2>
        <p>{{ $appName }} prohibits all content, behavior, links, requests, or coordination involving:</p>
        <ul>
            <li>Child sexual abuse material, including real, apparent, manipulated, AI-generated, animated, or illustrative sexual content involving minors.</li>
            <li>Grooming, coercion, blackmail, sextortion, sexual solicitation, trafficking, or attempts to move a child into unsafe off-platform communication.</li>
            <li>Sexualized comments, roleplay, fantasies, instructions, groups, keywords, usernames, profile details, or coded language involving minors.</li>
            <li>Sharing, requesting, selling, buying, trading, storing, or linking to CSAM or CSAE-related material.</li>
            <li>Threats, intimidation, or retaliation against anyone who reports child exploitation or safety concerns.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>2. In-app reporting and user escalation</h2>
        <p>
            {{ $appName }} provides in-app reporting tools so users can report content, accounts, messages, or behavior
            that may involve child safety concerns. Reports should include enough context for review, such as the
            account, content, chat, time, and reason for concern.
        </p>
        <p>
            Users may also escalate child safety or CSAM concerns by emailing
            <a href="mailto:{{ $safetyEmail }}?subject={{ rawurlencode($appName . ' child safety concern') }}" class="legal-doc-anchor">
                {{ $safetyEmail }}
            </a>.
            The designated contact is responsible for receiving child safety compliance questions and coordinating
            urgent review.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>3. Review and enforcement process</h2>
        <p>
            When {{ $appName }} receives a child safety report or detects suspected CSAE activity, we review available
            account, content, messaging, media, technical, and report information. Depending on severity and confidence,
            we may remove content, restrict visibility, disable features, suspend accounts, permanently terminate
            accounts, preserve relevant records, and escalate the matter for external reporting.
        </p>
        <p>
            We may prioritize suspected child exploitation reports over routine support requests because immediate
            action can be necessary to protect users and comply with applicable law.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>4. Reporting to authorities</h2>
        <p>
            {{ $appName }} complies with relevant child safety laws and cooperates with regional and national
            authorities when legally required or when urgent safety concerns justify escalation. Where applicable, this
            may include reporting suspected CSAM or CSAE activity to law enforcement, child protection authorities,
            national reporting centers, or other legally recognized reporting bodies.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>5. Prevention and platform controls</h2>
        <p>{{ $appName }} uses safety practices intended to reduce the risk of child exploitation, including:</p>
        <ul>
            <li>Rules that ban CSAE, CSAM, grooming, solicitation, sexualization of minors, and related coordination.</li>
            <li>User reporting workflows for content, profiles, messaging, and other user-generated areas.</li>
            <li>Moderation review of abuse reports and account-level enforcement for serious violations.</li>
            <li>Retention of relevant safety information where needed to investigate abuse, enforce rules, or meet legal obligations.</li>
            <li>Restrictions or removals for accounts, posts, comments, messages, media, links, or other material that threatens child safety.</li>
        </ul>
    </section>

    <section class="legal-doc-section">
        <h2>6. User responsibilities</h2>
        <p>
            Users must not upload, request, distribute, promote, or engage with content or conduct that exploits or
            endangers children. Users who encounter suspected child exploitation should report it immediately through
            in-app reporting tools or by contacting the designated safety email. Users should not share, download,
            forward, or further distribute suspected CSAM.
        </p>
    </section>

    <section class="legal-doc-section">
        <h2>7. Related policies</h2>
        <p>
            These standards work together with the
            <a href="{{ route('document.terms.index') }}" class="legal-doc-anchor">Terms of Use</a>,
            <a href="{{ route('document.privacy.index') }}" class="legal-doc-anchor">Privacy Policy</a>, and
            <a href="{{ route('document.help.index') }}" class="legal-doc-anchor">Help Center</a>.
            If these standards conflict with other platform guidance, the stricter child safety rule applies.
        </p>
    </section>

    <div class="legal-doc-note">
        For child safety compliance questions, contact
        <a href="mailto:{{ $safetyEmail }}?subject={{ rawurlencode($appName . ' child safety standards') }}" class="legal-doc-anchor">
            {{ $safetyEmail }}
        </a>.
    </div>
</div>
