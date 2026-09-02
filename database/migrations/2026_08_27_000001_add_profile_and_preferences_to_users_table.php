<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->bigInteger('monthly_income')->default(0)->after('display_name');
            $table->bigInteger('monthly_savings_target')->default(0)->after('monthly_income');
            $table->string('currency', 3)->default('SAR')->after('monthly_savings_target');
            $table->string('locale', 5)->default('ar')->after('currency');
            $table->enum('theme', ['light', 'dark', 'system'])->default('light')->after('locale');
            $table->enum('font_scale', ['sm', 'md', 'lg'])->default('md')->after('theme');
            $table->boolean('biometric_lock')->default(false)->after('font_scale');
            $table->boolean('notify_due')->default(true)->after('biometric_lock');
            $table->boolean('notify_budget')->default(true)->after('notify_due');
            $table->boolean('notify_salary')->default(true)->after('notify_budget');
            $table->tinyInteger('onboarding_step')->default(0)->after('notify_salary');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'monthly_income',
                'monthly_savings_target',
                'currency',
                'locale',
                'theme',
                'font_scale',
                'biometric_lock',
                'notify_due',
                'notify_budget',
                'notify_salary',
                'onboarding_step',
            ]);
        });
    }
};