<?php

namespace App\Http\Resources\User\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\User\Wallet\TransactionResource;

class TransactionCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function($transactionItem) {
            return TransactionResource::make($transactionItem->resource);
        })->all();
    }
}
