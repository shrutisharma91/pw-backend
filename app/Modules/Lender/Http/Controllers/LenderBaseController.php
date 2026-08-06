<?php

namespace App\Modules\Lender\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;

abstract class LenderBaseController extends Controller
{
    protected function scopedLenderId(): int
    {
        return (int) request()->attributes->get('scoped_lender_id');
    }

    protected function findLoanApplication(int $id): LoanApplication
    {
        return LoanApplication::query()
            ->where('lender_id', $this->scopedLenderId())
            ->where('id', $id)
            ->firstOrFail();
    }
}
