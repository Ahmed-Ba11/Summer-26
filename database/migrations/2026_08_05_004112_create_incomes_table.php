<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('source');
            $table->string('description')->nullable();
            $table->date('income_date');
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'income_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
