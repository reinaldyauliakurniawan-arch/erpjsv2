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

        // Update cash_flow_category untuk semua akun yang sudah ada di DB
        $categoryMap = [
            // Asset - Operating
            '1001' => 'operating', '1002' => 'operating', '1003' => 'operating',
            '1003a'=> 'operating', '1004' => 'operating', '1007' => 'operating',
            '1008' => 'operating', '1009' => 'operating',
            // Asset - Investing
            '1005' => 'investing', '1101' => 'investing', '1102' => 'investing',
            '1006' => 'investing',
            // Liability - Operating
            '2001' => 'operating', '2002' => 'operating', '2003' => 'operating',
            '2004' => 'operating', '2005' => 'operating',
            // Equity
            '3001' => 'financing', '3002' => 'financing', '3003' => 'financing',
            // Revenue - Operating
            '4000b'=> 'operating', '4010' => 'operating', '4011' => 'operating',
            '4020' => 'operating', '4021' => 'operating', '4030' => 'operating',
            '4031' => 'operating', '4040' => 'operating', '4041' => 'operating',
            '4050' => 'operating', '4051' => 'operating', '4060' => 'operating',
            '4061' => 'operating', '4070' => 'operating', '4080' => 'operating',
            '4081' => 'operating', '4090' => 'operating', '4101' => 'operating',
            '4102' => 'operating', '4111' => 'operating', '4200' => 'operating',
            '4201' => 'operating', '4202' => 'operating', '4203' => 'operating',
            '4901' => 'operating', '4902' => 'operating', '4903' => 'operating',
            '4911' => 'operating', '4912' => 'operating', '4913' => 'operating',
            '4921' => 'operating', '4922' => 'operating', '4923' => 'operating',
            '4931' => 'operating', '4932' => 'operating', '4933' => 'operating',
            '4941' => 'operating', '4942' => 'operating', '4943' => 'operating',
            '4944' => 'operating', '4951' => 'operating', '4952' => 'operating',
            '4953' => 'operating', '4961' => 'operating', '4962' => 'operating',
            '4963' => 'operating', '4971' => 'operating', '4972' => 'operating',
            '4973' => 'operating', '4981' => 'operating', '4982' => 'operating',
            '4983' => 'operating', '4990' => 'operating', '4991' => 'operating',
            '4992' => 'operating', '4993' => 'operating', '4994' => 'operating',
            // Expense - Operating
            '5000b'=> 'operating', '5001' => 'operating', '5002' => 'operating',
            '5003' => 'operating', '5004' => 'operating', '5005' => 'operating',
            '5006' => 'operating', '5101' => 'operating', '5102' => 'operating',
            '5103' => 'operating', '5104' => 'operating', '5105' => 'operating',
            '5106' => 'operating', '5107' => 'operating', '5109' => 'operating',
            '5201' => 'operating', '5202' => 'operating', '5203' => 'operating',
            '5204' => 'operating', '5205' => 'operating', '5206' => 'operating',
            '5207' => 'operating', '5301' => 'operating', '5302' => 'operating',
            '5303' => 'operating', '5401' => 'operating', '5402' => 'operating',
            '5403' => 'operating', '5501' => 'operating', '5502' => 'operating',
            // Expense - Non-cash (investing related)
            '5108' => 'investing', '5110' => 'investing',
        ];

        foreach ($accounts as $account) {
            DB::table('accounts')->updateOrInsert(
                ['code' => $account['code']],
                array_merge($account, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // Update cash_flow_category untuk semua akun di DB
        foreach ($categoryMap as $code => $category) {
            DB::table('accounts')->where('code', (string)$code)->update(['cash_flow_category' => $category]);
        }
    }
}
