<?php

namespace App\Policies;

use App\Models\TitleSettlement;
use App\Models\User;

class TitleSettlementPolicy extends FinancialRecordPolicy
{
    public function reverse(User $usuario, TitleSettlement $baixa): bool
    {
        return $this->view($usuario, $baixa);
    }
}
