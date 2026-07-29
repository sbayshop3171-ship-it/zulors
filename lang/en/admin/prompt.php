<?php

return [
	'delete_user' => [
		'title' => 'Delete user?',
		'description' => 'Are you sure you want to delete this user? This action cannot be undone.',
	],
	'delete_post' => [
		'title' => 'Delete post?',
		'description' => 'Are you sure you want to delete this post? This action cannot be undone.',
	],
	'delete_ad' => [
		'title' => 'Delete Ad?',
		'description' => 'Are you sure you want to delete this ad? This action cannot be undone.',
	],
	'approve_ad' => [
		'title' => 'Approve Ad?',
		'description' => 'Are you sure you want to approve this ad? This action cannot be undone.',
		'confirm_btn_text' => 'Yes, approve',
	],
	'reject_ad' => [
		'title' => 'Reject Ad?',
		'description' => 'Are you sure you want to reject this ad? Author will be able to update the ad and resubmit it for approval.',
		'confirm_btn_text' => 'Yes, reject',
	],
	'delete_story' => [
		'title' => 'Delete story?',
		'description' => 'Are you sure you want to delete this story? This action cannot be undone.',
	],
	'delete_product' => [
		'title' => 'Delete product?',
		'description' => 'Are you sure you want to delete this product? This action cannot be undone.',
	],
	'approve_product' => [
		'title' => 'Approve product?',
		'description' => 'Are you sure you want to approve this product? This action cannot be undone.',
		'confirm_btn_text' => 'Yes, approve',
	],
	'reject_product' => [
		'title' => 'Reject product?',
		'description' => 'Are you sure you want to reject this product? Author will be able to update the product and resubmit it for approval.',
		'confirm_btn_text' => 'Yes, reject',
	],
	'approve_job' => [
		'title' => 'Approve job?',
		'description' => 'Are you sure you want to approve this job? This action cannot be undone.',
		'confirm_btn_text' => 'Yes, approve',
	],
	'reject_job' => [
		'title' => 'Reject job?',
		'description' => 'Are you sure you want to reject this job? Author will be able to update the job and resubmit it for approval.',
		'confirm_btn_text' => 'Yes, reject',
	],
	'delete_job' => [
		'title' => 'Delete job?',
		'description' => 'Are you sure you want to delete this job? This action cannot be undone.',
	],
	'delete_report' => [
		'title' => 'Delete report?',
		'description' => 'Are you sure you want to delete this report? This action cannot be undone.',
	],
	'processed_report' => [
		'title' => 'Processed report?',
		'description' => 'Are you sure you want to mark this report as processed? Please note that this action does not make any affect to reported content.',
		'confirm_btn_text' => 'Yes, processed',
	],
	'ignore_report' => [
		'title' => 'Ignore report?',
		'description' => 'Are you sure you want to ignore this report? This action cannot be undone.',
		'confirm_btn_text' => 'Yes, ignore',
	],
	'disable_lang' => [
		'title' => 'Disable language?',
		'description' => 'Are you sure you want to disable this language? All users will be reset to the default language.',
		'confirm_btn_text' => 'Yes, disable',
	],
	'enable_lang' => [
		'title' => 'Enable language?',
		'description' => 'Are you sure you want to enable this language? Make sure all translations are available for this language.',
		'confirm_btn_text' => 'Yes, enable',
	],
	'make_default_lang' => [
		'title' => 'Make default language?',
		'description' => 'Are you sure you want to make this language the default language? It will be used as the default language for new users only.',
		'confirm_btn_text' => 'Yes, make default',
	],
	'delete_lang' => [
		'title' => 'Delete language?',
		'description' => 'Are you sure you want to delete this language? Note all translations for this language will be deleted.',
		'confirm_btn_text' => 'Yes, delete',
	],
	'remove_ban' => [
		'title' => 'Remove ban?',
		'description' => 'Are you sure you want to remove this ban? You can add it again later.',
	],
	'authorize_user' => [
		'title' => 'Authorize user?',
		'description' => 'Authors get a place in recommendations, their materials become available to subscribers. At the same time, you can always cancel authorship of this account whenever you want.',
		'confirm_btn_text' => 'Yes, authorize',
	],
	'unauthorize_user' => [
		'title' => 'Unauthorize user?',
		'description' => 'Are you sure you want to unauthorize this user? This action will remove the user from the authors list.',
		'confirm_btn_text' => 'Yes, unauthorize',
	],
	'reject_authorization' => [
		'title' => 'Reject request?',
		'description' => 'Are you sure you want to reject authorization request from this user? You can authorize user whenever you want.',
		'confirm_btn_text' => 'Yes, reject',
	],
	'verify_user' => [
		'title' => 'Verify user?',
		'description' => 'Verification is a way to verify that a user is who they say they are. Verified users also have a special verification badge in their profile.',
		'confirm_btn_text' => 'Yes, verify',
	],
	'unverify_user' => [
		'title' => 'Unverify user?',
		'description' => 'This action will remove the verification badge from the user\'s profile.',
		'confirm_btn_text' => 'Yes, unverify',
	],
	'delete_category' => [
		'title' => 'Delete category?',
		'description' => 'Are you sure you want to delete this category? Related entities will not be deleted but they will moved to the Uncategorized category.',
	],
	'delete_chat' => [
		'title' => 'Delete chat?',
		'description' => 'This chat will be deleted for all participants also. Including messages and other related data as messages, group, etc.',
	],
	'pin_post' => [
		'title' => 'Pin post?',
		'description' => 'Pinned posts appear in the global timeline and are visible to all users on main home page.',
		'confirm_btn_text' => 'Yes, pin',
	],
	'unpin_post' => [
		'title' => 'Unpin post?',
		'description' => 'If you unpin the post, it will no longer appear in the global timeline.',
		'confirm_btn_text' => 'Yes, unpin',
	],
	'delete_cashout' => [
		'title' => 'Delete cashout?',
		'description' => 'Are you sure you want to delete this cashout? This action cannot be undone.',
	],
    'reject_cashout' => [
        'title' => 'Reject cashout?',
        'description' => 'Money will be returned to the user\'s wallet. Are you sure you want to reject this cashout?',
        'confirm_btn_text' => 'Yes, reject',
    ],
    'mark_as_paid' => [
        'title' => 'Mark as paid?',
        'description' => 'This confirms the funds have been sent to the user. This action cannot be undone.',
        'confirm_btn_text' => 'Mark as paid',
    ],
];
