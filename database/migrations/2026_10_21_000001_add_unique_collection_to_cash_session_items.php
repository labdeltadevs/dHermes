<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_session_items', function (Blueprint $table) {
            $table->unique('collection_event_id', 'cash_session_items_collection_event_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cash_session_items', function (Blueprint $table) {
            $table->dropUnique('cash_session_items_collection_event_unique');
        });
    }
};
