<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\FinancialDefaultsService;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->chunkById(100, function ($usuarios) {
            foreach ($usuarios as $usuario) {
                app(FinancialDefaultsService::class)->initialize($usuario, 'payment_types');
            }
        });
    }
}
