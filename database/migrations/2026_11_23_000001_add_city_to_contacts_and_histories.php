<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Nullable: existing rows and other channels do not have a city.
            $table->string('city', 100)->nullable()->after('contact_name')->index();
            $table->index('contact_name');
        });

        Schema::table('contact_histories', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('contact_name')->index();
            $table->index('contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['contact_name']);
            $table->dropIndex(['city']);
            $table->dropColumn('city');
        });

        Schema::table('contact_histories', function (Blueprint $table) {
            $table->dropIndex(['contact_name']);
            $table->dropIndex(['city']);
            $table->dropColumn('city');
        });
    }
};
