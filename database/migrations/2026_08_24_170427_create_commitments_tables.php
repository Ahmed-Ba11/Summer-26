<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جداول الالتزامات الموحّدة (فواتير · إيجارات · أقساط · اشتراكات).
     * تُرحّل بيانات «bills» و«installments» القديمة إليها، ثم تصبح المصدر الوحيد.
     */
    public function up(): void
    {
        Schema::create('commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['bill', 'rent', 'installment', 'subscription']);
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->boolean('is_variable')->default(false);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->integer('months_count')->default(0);
            $table->integer('months_paid')->default(0);
            $table->enum('payment_method', ['auto', 'manual'])->default('manual');
            $table->enum('due_type', ['salary_day', 'month_day', 'fixed_date'])->default('month_day');
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('notify_before')->default(true);
            $table->boolean('notify_on_due')->default(true);
            $table->boolean('notify_late')->default(true);
            $table->boolean('reserve_in_budget')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('commitment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commitment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_at');
            $table->char('period_key', 7);
            $table->enum('source', ['auto', 'manual'])->default('manual');
            $table->timestamps();

            $table->unique(['commitment_id', 'period_key']);
            $table->index(['paid_at']);
        });

        $this->migrateBills();
        $this->migrateInstallments();
    }

    /** نسخ الفواتير الحالية إلى التزامات نوع «فاتورة». */
    private function migrateBills(): void
    {
        if (! Schema::hasTable('bills')) {
            return;
        }

        DB::table('bills')->whereNull('deleted_at')->orderBy('id')->each(function (object $bill): void {
            DB::table('commitments')->insert([
                'user_id' => $bill->user_id,
                'kind' => 'bill',
                'name' => $bill->name,
                'icon' => $bill->icon,
                'color' => null,
                'amount' => $bill->amount,
                'is_variable' => false,
                'total_amount' => 0,
                'months_count' => 0,
                'months_paid' => 0,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => (int) Carbon::parse($bill->due_date)->day,
                'due_date' => null,
                'notify_before' => true,
                'notify_on_due' => true,
                'notify_late' => true,
                'reserve_in_budget' => true,
                'is_active' => true,
                'created_at' => $bill->created_at,
                'updated_at' => $bill->updated_at,
            ]);
        });
    }

    /** نسخ الأقساط الحالية إلى التزامات نوع «قسط». */
    private function migrateInstallments(): void
    {
        if (! Schema::hasTable('installments')) {
            return;
        }

        DB::table('installments')->whereNull('deleted_at')->orderBy('id')->each(function (object $installment): void {
            DB::table('commitments')->insert([
                'user_id' => $installment->user_id,
                'kind' => 'installment',
                'name' => $installment->name,
                'icon' => $installment->icon,
                'color' => null,
                'amount' => $installment->monthly_amount,
                'is_variable' => false,
                'total_amount' => $installment->total_amount,
                'months_count' => $installment->total_months,
                'months_paid' => $installment->paid_months,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => 1,
                'due_date' => null,
                'notify_before' => true,
                'notify_on_due' => true,
                'notify_late' => true,
                'reserve_in_budget' => true,
                'is_active' => ! (bool) $installment->is_completed,
                'created_at' => $installment->created_at,
                'updated_at' => $installment->updated_at,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commitment_payments');
        Schema::dropIfExists('commitments');
    }
};
