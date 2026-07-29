<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Product;

class ProductPolicy
{
    public function delete(User $user, Product $productData) {
        return $productData->user_id === $user->id;
    }

    public function update(User $user, Product $productData) {
        return $productData->user_id === $user->id;
    }
}
