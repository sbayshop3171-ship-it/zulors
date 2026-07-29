<?php

namespace App\Http\Resources\User\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => [
                'raw' => $this->amount,
                'formatted' => $this->formatted_amount
            ],
            'total' => [
                'raw' => $this->amount,
                'formatted' => $this->formatted_amount
            ],
            'tnx_id' => $this->tnxId,
            'currency' => [
                'name' => $this->currency_name,
                'code' => $this->currency
            ],
            'source' => $this->metadata['source'],
            'commission' => [
                'rate' => $this->commission,
                'amount' => [
                    'raw' => $this->commission_amount,
                    'formatted' => $this->formatted_commission_amount
                ]
            ],
            'type' => [
                'key' => $this->transaction_type,
                'label' => $this->transaction_type->label()
            ],
            'direction' => $this->direction->value,
            'is_incoming' => $this->direction->isIncoming(),
            'is_internal' => $this->is_internal,
            'status' => [
                'key' => $this->status,
                'label' => $this->status->label()
            ],
            'date' => [
                'timestamp' => $this->created_at->getTimestamp(),
                'time_ago' => $this->created_at->getTimeAgo(),
                'formatted' => $this->created_at->getFormatted()
            ],
            'message' => (isset($this->metadata['message'])) ? $this->metadata['message'] : null
        ];
    }
}
