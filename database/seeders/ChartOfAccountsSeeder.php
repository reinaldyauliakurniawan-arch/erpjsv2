<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Enums\AccountCode;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'code' => AccountCode::CASH_BANK->value,
                'name' => 'Cash/Bank',
                'type' => 'Asset',
            ],
            [
                'code' => AccountCode::DEFERRED_REVENUE->value,
                'name' => 'Deferred Revenue',
                'type' => 'Liability',
            ],
            [
                'code' => AccountCode::TUTOR_PAYABLE->value,
                'name' => 'Tutor Payable',
                'type' => 'Liability',
            ],
            [
                'code' => AccountCode::REVENUE_TUITION_FEES->value,
                'name' => 'Revenue - Tuition Fees',
                'type' => 'Revenue',
            ],
            [
                'code' => AccountCode::REVENUE_ADMIN_FEE->value,
                'name' => 'Revenue - Admin Fee',
                'type' => 'Revenue',
            ],
            [
                'code' => AccountCode::EXPENSE_TUTOR_FEE->value,
                'name' => 'Expense - Tutor Fee',
                'type' => 'Expense',
            ],
            [
                'code' => AccountCode::EXPENSE_DISCOUNT_PROMO->value,
                'name' => 'Expense - Discount/Promo',
                'type' => 'Expense',
            ],
            [
                'code' => AccountCode::EXPENSE_REFUND->value,
                'name' => 'Expense - Refund',
                'type' => 'Expense',
            ],
        ];

        foreach ($accounts as $account) {
            DB::table('accounts')->updateOrInsert(
                ['code' => $account['code']],
                array_merge($account, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
