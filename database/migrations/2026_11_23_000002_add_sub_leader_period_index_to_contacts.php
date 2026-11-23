<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Speeds up the Asisten Marketing input page, which is reloaded after every
     * save and filters by sub_leader_id + period_key (then orders by created_at).
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['sub_leader_id', 'period_key', 'created_at'], 'contacts_sub_leader_period_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('contacts_sub_leader_period_created_index');
        });
    }
};
