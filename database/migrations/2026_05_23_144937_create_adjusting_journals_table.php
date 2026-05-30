<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('adjusting_journals', function (Blueprint $table) {
            $table->id();
            $table->date('period');                          // akhir bulan, misal 2024-01-31
            $table->string('reference')->unique();           // AJE-2024-01-001
            $table->string('description');
            $table->enum('type', ['depreciation', 'amortization', 'deferred_revenue', 'manual']);
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->unsignedBigInteger('source_id')->nullable();   // fixed_asset id / journal_item id
            $table->string('source_type')->nullable();             // App\Models\FixedAsset, dll
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->unsignedBigInteger('posted_journal_id')->nullable(); // link ke journals table setelah post
            $table->timestamps();
        });

        Schema::create('adjusting_journal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjusting_journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjusting_journal_items');
        Schema::dropIfExists('adjusting_journals');
    }
};
