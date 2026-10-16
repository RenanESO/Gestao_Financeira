<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\FinancialModel;
use App\Models\Notification;
use App\Models\PaymentType;
use App\Models\User;

class FinancialRecordPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->exists;
    }

    public function view(User $usuario, FinancialModel $registro): bool
    {
        return $usuario->exists && $registro->belongsToOwner($usuario);
    }

    public function create(User $usuario): bool
    {
        return $usuario->exists;
    }

    public function update(User $usuario, FinancialModel $registro): bool
    {
        // Registros de baixa, parcelas e recibos nao possuem atualizacao CRUD generica.
        return $this->view($usuario, $registro)
            && ($registro instanceof Category || $registro instanceof Contact
                || $registro instanceof FinancialAccount || $registro instanceof PaymentType
                || $registro instanceof Notification);
    }

    public function delete(User $usuario, FinancialModel $registro): bool
    {
        return $this->view($usuario, $registro) && $registro instanceof Contact;
    }

    public function restore(User $usuario, FinancialModel $registro): bool
    {
        return $this->view($usuario, $registro) && $registro instanceof Contact;
    }

    public function forceDelete(User $usuario, FinancialModel $registro): bool
    {
        return false;
    }
}
