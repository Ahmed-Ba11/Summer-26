<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commitments', function (Blueprint $table): void {
            $table->enum('notify_when', ['before_3', 'on_due', 'none'])
                ->default('before_3')
                ->after('notify_late');
        });

        DB::table('commitments')
            ->select(['id', 'due_type', 'due_day', 'due_date', 'notify_before', 'notify_on_due'])
            ->orderBy('id')
            ->each(function (object $commitment): void {
                $dueType = $commitment->due_type;
                $dueDay = $commitment->due_day;

                if ($dueType === 'fixed_date') {
                    $dueType = 'month_day';
                    $dueDay = $commitment->due_date
                        ? (int) Carbon::parse($commitment->due_date)->day
                        : 1;
                }

                $notifyWhen = $commitment->notify_before
                    ? 'before_3'
                    : ($commitment->notify_on_due ? 'on_due' : 'none');

                DB::table('commitments')
                    ->where('id', $commitment->id)
                    ->update([
                        'due_type' => $dueType,
                        'due_day' => $dueDay,
                        'notify_when' => $notifyWhen,
                    ]);
            });

        DB::table('commitments')
            ->where('icon', 'home')
            ->update(['icon' => 'house']);

        Schema::table('commitments', function (Blueprint $table): void {
            $table->enum('due_type', ['salary_day', 'month_day'])
                ->default('month_day')
                ->change();
            $table->dropColumn(['due_date', 'notify_before', 'notify_on_due', 'notify_late']);
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table): void {
            $table->date('due_date')->nullable();
            $table->boolean('notify_before')->default(true);
            $table->boolean('notify_on_due')->default(true);
            $table->boolean('notify_late')->default(true);
            $table->enum('due_type', ['salary_day', 'month_day', 'fixed_date'])
                ->default('month_day')
                ->change();
        });

        DB::table('commitments')
            ->select(['id', 'notify_when'])
            ->orderBy('id')
            ->each(function (object $commitment): void {
                DB::table('commitments')
                    ->where('id', $commitment->id)
                    ->update([
                        'notify_before' => $commitment->notify_when === 'before_3',
                        'notify_on_due' => $commitment->notify_when === 'on_due',
                        'notify_late' => false,
                    ]);
            });

        Schema::table('commitments', function (Blueprint $table): void {
            $table->dropColumn('notify_when');
        });
    }
};
