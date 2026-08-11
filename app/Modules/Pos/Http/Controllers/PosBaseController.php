<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PosBaseController extends Controller
{
    /**
     * Get the scoped store_id for the current request.
     */
    protected function getStoreId(Request $request): int
    {
        return $request->attributes->get('scoped_store_id');
    }

    /**
     * Get the scoped merchant_id for the current request.
     */
    protected function getMerchantId(Request $request): int
    {
        return $request->attributes->get('scoped_merchant_id');
    }
}
