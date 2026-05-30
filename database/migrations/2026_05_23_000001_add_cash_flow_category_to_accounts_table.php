<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('cash_flow_category')->nullable()->after('type');
            // values: 'cash', 'operating', 'investing', 'financing'
            // null = tidak masuk laporan arus kas
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('cash_flow_category');
        });
    }
};
