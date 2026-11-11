<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('marketing_channel', 32)->default('toploker')->after('team_id')->index();
            $table->dropUnique(['normalized_phone', 'dedup_week']);
            $table->unique(['normalized_phone', 'dedup_week', 'marketing_channel']);
        });

        Schema::table('contact_histories', function (Blueprint $table) {
            $table->string('marketing_channel', 32)->default('toploker')->after('team_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['normalized_phone', 'dedup_week', 'marketing_channel']);
            $table->unique(['normalized_phone', 'dedup_week']);
            $table->dropColumn('marketing_channel');
        });

        Schema::table('contact_histories', function (Blueprint $table) {
            $table->dropColumn('marketing_channel');
        });
    }
};

