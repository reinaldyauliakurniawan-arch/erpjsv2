<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts')->nullOnDelete()->after('depreciation_method');
            $table->foreignId('accumulated_account_id')->nullable()->constrained('accounts')->nullOnDelete()->after('expense_account_id');
            $table->boolean('is_active')->default(true)->after('accumulated_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropForeign(['expense_account_id']);
            $table->dropForeign(['accumulated_account_id']);
            $table->dropColumn(['expense_account_id', 'accumulated_account_id', 'is_active']);
        });
    }
};
