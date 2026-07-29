<?php

namespace App\Http\Resources\User\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferReceiverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'wallet_number' => $this->wallet_number,
            'relations' => [
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'avatar_url' => $this->user->avatar_url
                ]
            ]
        ];
    }
}
