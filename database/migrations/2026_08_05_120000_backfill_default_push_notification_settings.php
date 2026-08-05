<?php

use App\Database\Configs\Table;
use App\Enums\NotificationType;
use App\Models\UserNotificationSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pushDefaults = UserNotificationSettings::defaultPushPreferences();

        $missingPushSettings = DB::table(Table::USERS . ' as users')
            ->leftJoin(Table::USER_NOTIFICATION_SETTINGS . ' as push_settings', function ($join) {
                $join->on('push_settings.user_id', '=', 'users.id')
                    ->where('push_settings.type', NotificationType::PUSH->value);
            })
            ->whereNull('push_settings.id')
            ->select([
                'users.id as user_id',
                DB::raw("'" . NotificationType::PUSH->value . "' as type"),
                DB::raw((int) $pushDefaults['direct_messages'] . ' as direct_messages'),
                DB::raw((int) $pushDefaults['show_message_preview'] . ' as show_message_preview'),
                DB::raw((int) $pushDefaults['reactions'] . ' as reactions'),
                DB::raw((int) $pushDefaults['comments'] . ' as comments'),
                DB::raw((int) $pushDefaults['shared_posts'] . ' as shared_posts'),
                DB::raw((int) $pushDefaults['followers'] . ' as followers'),
                DB::raw((int) $pushDefaults['follow_request'] . ' as follow_request'),
                DB::raw((int) $pushDefaults['mentions'] . ' as mentions'),
            ]);

        DB::table(Table::USER_NOTIFICATION_SETTINGS)->insertUsing([
            'user_id',
            'type',
            'direct_messages',
            'show_message_preview',
            'reactions',
            'comments',
            'shared_posts',
            'followers',
            'follow_request',
            'mentions',
        ], $missingPushSettings);

        DB::table(Table::USER_NOTIFICATION_SETTINGS)
            ->where('type', NotificationType::PUSH->value)
            ->update($pushDefaults);
    }

    public function down(): void
    {
        // This is a one-way product-default backfill for push notification preferences.
    }
};
