<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ليس كل التزام شهرياً.
 *
 * اشتراك إنترنت لشهر واحد ثم يُلغى كان يبقى ظاهراً في كل فترة راتب بلا
 * داعٍ، لأن مولّد الظهورات يفترض التكرار في كل التزام. الحقول الثلاثة
 * تفرّق بين الحالتين وتفتح باب الإيقاف بدل الحذف:
 *
 *   recurrence = monthly → ظهور في كل فترة (السلوك السابق، وهو الافتراضي
 *                           لكل الصفوف القائمة فلا يتغيّر شيء عندها).
 *   recurrence = once    → ظهور واحد في الفترة التي يقع فيها `due_on`.
 *   ends_on              → آخر تاريخ يُطالَب فيه المتكرّر؛ لا ظهور من
 *                           هذا التاريخ فصاعداً، وسجل الأشهر السابقة يبقى.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commitments', function (Blueprint $table): void {
            $table->enum('recurrence', ['monthly', 'once'])
                ->default('monthly')
                ->after('due_day');
            $table->date('due_on')->nullable()->after('recurrence');
            $table->date('ends_on')->nullable()->after('due_on');
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table): void {
            $table->dropColumn(['recurrence', 'due_on', 'ends_on']);
        });
    }
};
