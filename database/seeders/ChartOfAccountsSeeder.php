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
                'code' => AccountCode::CASH->value,
                'name' => 'Cash',
                'type' => 'Asset',
                'cash_flow_category' => 'cash',
            ],
            [
                'code' => AccountCode::BANK->value,
                'name' => 'Bank',
                'type' => 'Asset',
                'cash_flow_category' => 'cash',
            ],
            [
                'code' => AccountCode::DEFERRED_REVENUE->value,
                'name' => 'Deferred Revenue',
                'type' => 'Liability',
                'cash_flow_category' => 'operating',
            ],
            [
                'code' => AccountCode::TUTOR_PAYABLE->value,
                'name' => 'Tutor Payable',
                'type' => 'Liability',
                'cash_flow_category' => 'operating',
            ],
            [
                'code' => AccountCode::REVENUE_TUITION_FEES->value,
                'name' => 'Revenue - Tuition Fees',
                'type' => 'Revenue',
                'cash_flow_category' => 'operating',
            ],
            [
                'code' => AccountCode::REVENUE_ADMIN_FEE->value,
                'name' => 'Revenue - Admin Fee',
                'type' => 'Revenue',
                'cash_flow_category' => 'operating',
            ],
            [
                'code' => AccountCode::EXPENSE_TUTOR_FEE->value,
                'name' => 'Expense - Tutor Fee',
                'type' => 'Expense',
                'cash_flow_category' => 'operating',
            ],
            [
                'code' => AccountCode::EXPENSE_DISCOUNT_PROMO->value,
                'name' => 'Expense - Discount/Promo',
                'type' => 'Expense',
                'cash_flow_category' => 'operating',
            ],
            [
                'code' => AccountCode::EXPENSE_REFUND->value,
                'name' => 'Expense - Refund',
                'type' => 'Expense',
                'cash_flow_category' => 'operating',
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
