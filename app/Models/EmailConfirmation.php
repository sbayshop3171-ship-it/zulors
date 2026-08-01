<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Base\SupportsHashIds;

class EmailConfirmation extends Model
{
    use SupportsHashIds;
    
    public $fillable = ['email', 'token', 'code', 'code_expires_at'];

    public $table = Table::EMAIL_CONF;

    protected $casts = [
        'code_expires_at' => 'datetime',
    ];

    public static function generateOtpCode(): string
    {
        return (string) random_int(1000, 9999);
    }

    public function refreshOtpCode(): void
    {
        $this->forceFill([
            'code' => static::generateOtpCode(),
            'code_expires_at' => now()->addMinutes(10),
        ])->save();
    }

    public function otpCodeExpired(): bool
    {
        return empty($this->code_expires_at) || $this->code_expires_at->isPast();
    }

    public function otpCodeMatches(string $code): bool
    {
        return hash_equals((string) $this->code, $code);
    }
}
