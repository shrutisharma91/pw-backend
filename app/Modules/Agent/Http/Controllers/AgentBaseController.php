<?php

namespace App\Modules\Agent\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Store;

abstract class AgentBaseController extends Controller
{
    protected function scopedSalesExecId(): int
    {
        return (int) request()->attributes->get('scoped_sales_exec_id');
    }

    protected function agentMerchants()
    {
        return Merchant::query()->where('sales_exec_id', $this->scopedSalesExecId());
    }

    protected function findAgentMerchant(int $id): Merchant
    {
        return $this->agentMerchants()->where('id', $id)->firstOrFail();
    }

    protected function findAgentStore(int $storeId): Store
    {
        return Store::query()
            ->where('id', $storeId)
            ->whereHas('merchant', fn ($q) => $q->where('sales_exec_id', $this->scopedSalesExecId()))
            ->firstOrFail();
    }
}
