<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * إصلاح الأهداف التي عُلّمت «مكتملة» بلا أن يبلغ رصيدها الهدف.
     *
     * مسار «إقفال الهدف» كان يكتب `is_completed = true` بلا مقارنة، فبقيت
     * صفوف تقول إنها مكتملة ورصيدها جزء من الهدف. تصحيح المسار لا يصلح ما
     * كُتب قبله — والراية تُقرأ في بطاقة الهدف وفي عدّاد «X من Y مكتمل»
     * وفي أهداف اللوحة، فتبقى الأرقام غلطاً حتى تُعاد الحسبة مرّة واحدة.
     *
     * `is_closed` لا تُمسّ: الإقفال قرار المستخدم ويبقى كما اختاره.
     */
    public function up(): void
    {
        // شرطان صريحان بدل CASE خام: القيمة المنطقية تُكتب كما يفهمها كل
        // محرّك (Postgres لا يقبل 1/0 في عمود boolean).
        DB::table('savings_goals')
            ->where('target_amount', '>', 0)
            ->whereColumn('current_amount', '>=', 'target_amount')
            ->update(['is_completed' => true]);

        DB::table('savings_goals')
            ->where('target_amount', '<=', 0)
            ->orWhereColumn('current_amount', '<', 'target_amount')
            ->update(['is_completed' => false]);
    }

    public function down(): void
    {
        // لا رجعة: القيمة السابقة كانت غلطاً، وإعادتها إعادة للخطأ نفسه.
    }
};
