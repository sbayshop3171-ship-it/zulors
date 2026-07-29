<?php

return [
	'index_title' => 'Wallet',
	'cashouts_title' => 'Cashout History',
    'withdrawal_title' => 'Request Withdrawal',
	'balance_desc' => 'Available Balance',
	'open_wallet_btn' => 'Open Wallet',
	'wallet_number' => 'Wallet number',
	'request_withdrawal' => 'Request Withdrawal',
	'about_wallet_text' => ':wallet_name wallet can only be used within the platform. Funds are intended to pay for services here and cannot be transferred outside <a href=":about_link" target="_blank" class="text-brand-900 hover:underline">Read more here.</a>',
    'form' => [
        'amount' => 'Amount',
        'amount_placeholder' => 'Enter amount',
        'amount_helper' => 'Enter the amount you want to withdraw.',
        'payment_method' => 'Payment Method',
        'payment_method_placeholder' => 'Select Method',
        'credentials' => 'Credentials',
        'credentials_placeholder' => 'Enter credentials',
        'credentials_helper' => 'Provide the account details for your chosen payment method (e.g., wallet address, bank account number and other required information).',
        'reviewer_notes' => 'Reviewer Notes (optional)',
        'reviewer_notes_placeholder' => 'Enter notes (optional)',
        'reviewer_notes_helper' => 'Provide any additional information that may be helpful for the reviewer. This will be visible to the reviewer only.',
    ],
    'validation' => [
        'amount' => [
            'insufficient_balance' => 'You have entered an amount that is greater than your available balance.',
            'pending_request' => 'You have a pending request. Please wait for it to be processed before making a new request.',
        ],
    ],
];
