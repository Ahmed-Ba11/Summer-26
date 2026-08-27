<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط المصروف بالالتزام الذي سدّده.
     *
     * سداد فاتورة الكهرباء مصروفٌ فعلي وسدادُ التزام في آن. بلا هذا العمود
     * كان المستخدم يسجّله مرّتين — مصروفاً في «المصاريف» وسداداً في
     * «الالتزامات» — أو يسجّله مصروفاً فقط فيبقى الالتزام «متأخّراً» في
     * التقويم وقد دُفع فعلاً.
     *
     * `nullOnDelete`: حذف الالتزام لا يمحو تاريخ الصرف من التقارير.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('commitment_id')
                ->nullable()
                ->after('category_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commitment_id');
        });
    }
};
