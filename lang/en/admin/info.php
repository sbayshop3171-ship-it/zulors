<?php

return [
	'you_are_admin' => 'You are logged in as an administrator. 🛡️',
    'env_edit_notice' => [
		'title' => 'How to edit?',
		'line_one' => 'These settings are managed from the <code>.env</code> file (located in the root of your Zulors installation) and cannot be changed from the admin panel.',
		'line_two' => 'To update them, please edit the <code>.env</code> file directly and then just click to Reset Cache button.',
		'env_privacy' => '⚠️ Please do not share your .env file with anyone. It contains all application\'s confidential information.'
	],
	'payment_preview' => [
		'title' => 'Payment Object',
		'line_one' => 'Payment object is an administrative object to represent payment made by user.',
		'line_two' => 'It contains payment reference id and other data related to payment.',
		'line_three' => 'Please avoid editing or deleting this object until payment is completed or expired.'
	],
	'language_edit_notice' => [
		'title' => 'How to edit?',
		'line_one' => 'All language texts are stored in local files in <code>.php</code> and <code>.json</code> format.',
		'line_two' => 'To edit the texts, please edit the <code>.php</code> or <code>.json</code> file directly following <a class="text-brand-900 underline" href=":documentation_url" target="_blank">Documentation</a>.'
	],
	'translation_notice' => [
		'title' => 'Manual Translation Required!',
		'line_one' => 'All translation files will be copied from English (en - permanent locale) as a base.',
		'line_two' => 'Please note that new added language will not be translated by default.',
		'line_three' => 'You must manually update the translation files to reflect the correct language.',
		'line_four' => '👉 Follow the translation guide in the documentation for instructions.'
	],
	'currency_notice' => [
		'title' => 'Fiat Currency 💰',
		'line_one' => 'Currencies are fiat currencies that are used in the application for business content like jobs, products, etc.',
		'line_two' => 'Please avoid deleting currencies that are already in use by your users.'
	],
	'ban_notice' => [
		'title' => 'Banned Content 🚫',
		'line_one' => 'Banned content is content that has been banned from the application.',
		'line_two' => 'You can choose to ban several types of content like IP, email, phone, username, email domain, etc.',
		'line_three' => 'Banned content will be automatically removed after the expiration date if set.'
	],
	'round_robin_notice' => [
		'title' => 'Round Robin Storage 🔄',
		'line_one' => 'Zulors features a round-robin storage system that supports both S3 and FTP as backend options.',
		'line_two' => 'You can add as many S3 or FTP storage accounts as you need — whether from AWS, DigitalOcean, Vultr, or any other provider that supports these protocols.',
		'line_three' => 'Once configured, Zulors will automatically distribute files across the available storage accounts in a round-robin fashion, helping you balance storage usage seamlessly.'
	],
	'laravel_notice' => [
		'title' => 'Laravel Ecosystem 🚀',
		'line_one' => 'Zulors is built on top of Laravel :laravel_version. <a href="https://www.laravel.com" target="_blank" class="text-brand-900">Learn more</a>',
		'line_two' => 'It means that you are free to use any Laravel ecosystem tools, packages and services you want.'
	],
	'category_notice' => [
		'title' => 'Category',
		'line_one' => 'Create entity categories (e.g., products or vacancies) to fit your needs. Add translations so category names match each user’s selected language.',
	],
	'page_edit_notice' => [
		'title' => 'Static Page',
		'line_one' => 'Static pages are used to display legal or informational content related to your project. For example: Cookies Policy, Privacy Policy, Terms of Service, About your company, and similar pages.',
		'line_two' => 'You can add translations for each supported language so the information is shown to users in their preferred language.',
	],
	'chat_notice' => [
		'title' => 'Direct Chat',
		'line_one' => 'A chat is a private conversation between two or more users. In this version, message contents viewing is not supported from the admin panel.',
		'line_two' => 'You may delete an entire chat if necessary.',
	],
	'smtp_solutions' => [
		'title' => 'Recommended free setup: Brevo API or SMTP',
		'brevo_intro' => 'Use Brevo for outgoing OTP, signup verification, password reset, and notification email. Brevo API avoids SMTP IP allowlisting; keep Cloudflare Email Routing only for receiving/forwarding mail.',
		'step_domain' => '1. In Brevo, add and authenticate <strong>zulors.com</strong> under sender/domain settings.',
		'step_dns' => '2. Copy Brevo SPF/DKIM/DMARC DNS records into Cloudflare DNS, then wait until Brevo marks the domain authenticated.',
		'step_smtp_key' => '3. For API mode, create a Brevo <strong>API key</strong> and paste it into the Brevo API key field. For SMTP mode, generate an SMTP key and paste the SMTP login/key as Username/Password.',
		'step_sender' => '4. Verify a sender such as <strong>noreply@zulors.com</strong> or <strong>support@zulors.com</strong> before sending live mail.',
		'step_test' => '5. Save these settings, then open the Email (SMTP) Testing tab and send a test email to Gmail/Outlook.',
        'values' => [
            'transport' => 'Transport',
            'host' => 'Host',
            'port' => 'Port',
            'encryption' => 'Encryption',
            'username' => 'Username',
            'username_value' => 'Brevo SMTP login',
            'password' => 'Password',
            'password_value' => 'Brevo SMTP key',
            'from_address' => 'From address',
        ],
        'links' => [
            'smtp' => 'Brevo SMTP documentation',
            'domain' => 'Brevo domain authentication',
            'free_plan' => 'Brevo free plan limits',
        ],
	],
	'ffmpeg_notice' => [
		'title' => 'Where is FFMPEG used in :app_name?',
		'line_one' => 'FFMPEG is used in :app_name to compress user\'s videos before uploading to the platform to optimize the platform performance and reduce the storage usage.',
        'line_two' => 'It converts all uploaded videos to the platform default format (MP4).',
	],
    'acquiring_notice' => [
        'title' => 'What is Acquiring?',
        'line_one' => 'Acquiring enables users to top up their in-app wallet, which can be spent on in-app purchases and services.',
        'line_two' => 'To activate acquiring, choose a payment provider and configure its API credentials below.',
        'line_three' => 'API keys are issued directly by your payment provider.',
    ],
    'social_login_notice' => [
        'title' => 'What is Social Login?',
        'line_one' => 'Social login allows users to log in to your application using their social media accounts.',
        'line_two' => 'To activate social login, choose a social media provider and configure its API credentials below.',
        'line_three' => 'Credentials are issued directly by your social media provider.',
    ],
    'wallet_notice' => [
        'title' => 'What is Wallet?',
        'line_one' => 'Wallet empowers your users to have a in-app balance to pay for platform services and in-app purchases.',
        'line_two' => 'You can enable any payment provider you want to allow users to deposit funds to their wallet.',
    ],
];
