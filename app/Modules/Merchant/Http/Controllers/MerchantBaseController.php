<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;

abstract class MerchantBaseController extends Controller
{
    /**
     * Get the merchant ID from the authenticated scope.
     * This ensures all queries are strictly isolated to the logged-in merchant.
     */
    protected function scopedMerchantId(): int
    {
        return (int) request()->attributes->get('scoped_merchant_id');
    }

    /**
     * Check if the user is a store manager and get their scoped store ID if applicable.
     */
    protected function scopedStoreId(): ?int
    {
        $id = request()->attributes->get('scoped_store_id');
        return $id ? (int) $id : null;
    }
}
