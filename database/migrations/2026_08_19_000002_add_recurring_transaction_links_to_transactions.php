<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('recurring_transaction_id')
                ->nullable()
                ->after('is_recurring')
                ->constrained('recurring_transactions')
                ->nullOnDelete();
        });

        Schema::table('incomes', function (Blueprint $table): void {
            $table->foreignId('recurring_transaction_id')
                ->nullable()
                ->after('is_recurring')
                ->constrained('recurring_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table): void {
            $table->dropForeign(['recurring_transaction_id']);
            $table->dropColumn('recurring_transaction_id');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropForeign(['recurring_transaction_id']);
            $table->dropColumn('recurring_transaction_id');
        });
    }
};
