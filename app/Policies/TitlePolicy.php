<?php

namespace App\Policies;

use App\Models\FinancialModel;
use App\Models\User;

class TitlePolicy extends FinancialRecordPolicy
{
    public function update(User $usuario, FinancialModel $registro): bool
    {
        return $this->view($usuario, $registro);
    }

    public function delete(User $usuario, FinancialModel $registro): bool
    {
        return $this->view($usuario, $registro);
    }

    public function restore(User $usuario, FinancialModel $registro): bool
    {
        return $this->view($usuario, $registro);
    }
}
