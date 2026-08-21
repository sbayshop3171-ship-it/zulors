@php
	$apiMessagesPath = base_path('lang/' . app()->getLocale() . '/api/index.php');
	$fallbackApiMessagesPath = base_path('lang/en/api/index.php');
	$apiMessages = file_exists($apiMessagesPath) ? require $apiMessagesPath : (file_exists($fallbackApiMessagesPath) ? require $fallbackApiMessagesPath : []);
    $startupTranslationNamespaces = [
        'auth',
        'chat',
        'create_labels',
        'dd',
        'editor',
        'empty_state',
        'labels',
        'notifications',
        'prompt',
        'settings',
        'story',
        'toast',
        'wallet',
    ];
    $startupMessages = array_intersect_key($apiMessages, array_flip($startupTranslationNamespaces));
@endphp

<script>
	window.BackendEmbeds = {
		startup_translations: @json($startupMessages),
		translations: @json($apiMessages),
		assets: {
			images: {
				upload_avatar: '{{ asset('assets/avatars/upload-avatar.png') }}',
				bio_avatar: '{{ asset('assets/avatars/bio-avatar.png') }}'
			},
			logos: {
				url: '{{ $logotypeUrl }}'
			},
			emojis: {
				animated: @json(config('emojis.animated'))
			}
		},
		translation_service: {
			name: '{{ config('services.translation.name') }}',
			url: '{{ config('services.translation.url') }}',
			logo_url: '{{ config('services.translation.logo') }}'
		},
		routes: {
			business_dashboard_index: "{{ route('business.dashboard.index') }}",
			business_ads_index: "{{ route('business.ads.index') }}",
			business_ads_create: "{{ route('business.ads.create') }}",
			business_market_index: "{{ route('business.market.index') }}",
			business_market_create: "{{ route('business.market.create') }}",
			business_jobs_index: "{{ route('business.jobs.index') }}",
			business_jobs_create: "{{ route('business.jobs.create') }}",
			business_wallet_cashouts: "{{ route('business.wallet.cashouts') }}",
			user_auth_index: "{{ route('user.auth.index') }}",
			terms_of_use: "{{ route('document.terms.index') }}",
			privacy_policy: "{{ route('document.privacy.index') }}",
			cookies_policy: "{{ route('document.cookies.index') }}",
			api_developers: "{{ route('document.developers.index') }}",
			help_center: "{{ route('document.help.index') }}",
			user_linker_index: "{{ route('user.linker.index') }}",
			verification_rules: "{{ route('document.verification.index') }}",
			become_author: "{{ route('document.author.index') }}"
		},
		sharing: {
			stories: @json(config('content.sharing.stories'))
		},
		links: {
			assets_url: "{{ asset('/') }}",
			base_url: "{{ url('/') }}/",
			assets: {
				emoji: "{{ asset('assets/emoji/img-apple-64') }}/"
			},
			guide_links: {
				publication_rules: "{{ asset('documents/publication-rules.pdf') }}",
			}
		},
		locale: '{{ app()->getLocale() }}',
		locale_name: '{{ $localeName }}',
		available_locales: @json(available_locales()),
		theme: '{{ theme_name() }}',
		theme_preference: '{{ theme_preference_name() }}',
		config: {
			features: @json(config('features')),
			app: {
				name: '{{ config('app.name') }}',
				currency: @json(default_currency())
			},
			verification: {
				service_url: '{{ config('verification.service_url') }}'
			},
			validation: {
				user: {
					bio: @json(config('user.validation.bio'))
				}
			},
			chat: {
				group: {
					invite_expire_days: {{ config('chat.group.invite_expire_days') }}
				}
			},
			user: {
				default_avatar: '{{ asset(config('user.avatar')) }}',
				default_cover: '{{ asset(config('user.cover')) }}'
			},
			wallet: {
				name: '{{ config('wallet.name') }}',
				about_link: '{{ config('wallet.about_link') }}',
				deposit: {
					max_amount: {{ config('wallet.deposit.max_amount') }},
					min_amount: {{ config('wallet.deposit.min_amount') }},
					commission: {{ config('wallet.commission.deposit') }}
				},
				transfer: {
					max_amount: {{ config('wallet.transfer.max_amount') }},
					min_amount: {{ config('wallet.transfer.min_amount') }},
					commission: {{ config('wallet.commission.transfer') }}
				},
				withdraw: {
					max_amount: {{ config('wallet.withdraw.max_amount') }},
					min_amount: {{ config('wallet.withdraw.min_amount') }},
					commission: {{ config('wallet.commission.withdraw') }}
				}
			},
			sounds: {
				chat: {
					active_chat_message_received: '{{ asset(config('chat.sounds.active_chat_message_received')) }}',
					chat_message_sent: '{{ asset(config('chat.sounds.chat_message_sent')) }}',
					background_chat_message_received: '{{ asset(config('chat.sounds.background_chat_message_received')) }}'
				},
				notification: {
					received: '{{ asset(config('notifications.sounds.notification_received')) }}',
					ui_feedback: '{{ asset(config('notifications.sounds.ui_feedback')) }}'
				}
			}
		},
		contacts: {
			support_email: '{{ config('contacts.support_email') }}',
			support_phone: '{{ config('contacts.support_phone') }}',
			address: '{{ config('contacts.address') }}'
		}
	};
</script>
