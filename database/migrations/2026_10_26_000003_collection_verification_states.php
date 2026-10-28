<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE collection_events DROP CONSTRAINT IF EXISTS collection_events_status_check');
        }

        DB::table('collection_events')
            ->where('status', 'matched')
            ->update(['status' => 'verified']);

        Schema::table('collection_events', function ($table): void {
            $table->renameColumn('matched_receipt_id', 'verified_receipt_id');
            $table->renameColumn('matched_at', 'verified_at');
        });
    }

    public function down(): void
    {
        DB::table('collection_events')
            ->where('status', 'verified')
            ->update(['status' => 'matched']);

        Schema::table('collection_events', function ($table): void {
            $table->renameColumn('verified_receipt_id', 'matched_receipt_id');
            $table->renameColumn('verified_at', 'matched_at');
        });
    }
};
