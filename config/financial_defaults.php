<?php

return [
    'categories' => [
        ['name' => 'Salário', 'type' => 'income', 'color' => '#22c55e'],
        ['name' => 'Freelance', 'type' => 'income', 'color' => '#84cc16'],
        ['name' => 'Investimentos', 'type' => 'income', 'color' => '#06b6d4'],
        ['name' => 'Outros Recebimentos', 'type' => 'income', 'color' => '#a3a3a3'],
        ['name' => 'Alimentação', 'type' => 'expense', 'color' => '#ef4444'],
        ['name' => 'Moradia', 'type' => 'expense', 'color' => '#f97316'],
        ['name' => 'Transporte', 'type' => 'expense', 'color' => '#eab308'],
        ['name' => 'Saúde', 'type' => 'expense', 'color' => '#ec4899'],
        ['name' => 'Educação', 'type' => 'expense', 'color' => '#8b5cf6'],
        ['name' => 'Lazer', 'type' => 'expense', 'color' => '#f43f5e'],
        ['name' => 'Assinaturas', 'type' => 'expense', 'color' => '#6366f1'],
        ['name' => 'Outros Gastos', 'type' => 'expense', 'color' => '#a3a3a3'],
    ],
    'payment_types' => [
        ['name' => 'Dinheiro', 'type' => 'both'],
        ['name' => 'PIX', 'type' => 'both'],
        ['name' => 'Cartão de Débito', 'type' => 'both'],
        ['name' => 'Cartão de Crédito', 'type' => 'both'],
        ['name' => 'Boleto Bancário', 'type' => 'both'],
        ['name' => 'Transferência (TED/DOC)', 'type' => 'both'],
        ['name' => 'Cheque', 'type' => 'both'],
    ],
];
