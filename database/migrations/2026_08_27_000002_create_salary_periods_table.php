<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 7);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->bigInteger('income_total')->default(0);
            $table->bigInteger('expenses_total')->default(0);
            $table->bigInteger('commitments_total')->default(0);
            $table->bigInteger('savings_total')->default(0);
            $table->bigInteger('surplus')->default(0);
            $table->enum('surplus_action', ['saved', 'rolled', 'split', 'pending'])->default('pending');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_key']);
            $table->index(['user_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_periods');
    }
};
