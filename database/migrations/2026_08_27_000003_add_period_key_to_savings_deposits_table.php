<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_deposits', function (Blueprint $table) {
            // السحب من هدف يُسجَّل صفّاً بمبلغ سالب — فالعمود لازم يقبل الإشارة
            $table->bigInteger('amount')->change();
            $table->string('period_key', 7)->nullable()->after('deposited_at');
        });

        DB::table('savings_deposits')->whereNull('period_key')->update([
            'period_key' => DB::raw("substr(deposited_at, 1, 7)"),
        ]);

        Schema::table('savings_deposits', function (Blueprint $table) {
            $table->index(['user_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::table('savings_deposits', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'period_key']);
            $table->dropColumn('period_key');
            $table->unsignedBigInteger('amount')->change();
        });
    }
};
