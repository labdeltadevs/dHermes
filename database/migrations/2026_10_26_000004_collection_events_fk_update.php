<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Drop the old composite foreign key on matched_receipt_id
        Schema::table('collection_events', function ($table): void {
            $table->dropForeign('collection_events_tenant_id_matched_receipt_id_foreign');
        });

        // Add foreign key on verified_receipt_id
        Schema::table('collection_events', function ($table): void {
            $table->foreign(['tenant_id', 'verified_receipt_id'], 'collection_events_verified_receipt_id_foreign')
                ->references(['tenant_id', 'id'])
                ->on('receipts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('collection_events', function ($table): void {
            $table->dropForeign(['verified_receipt_id']);
            $table->foreign(['tenant_id', 'matched_receipt_id'], 'collection_events_matched_receipt_id_foreign')
                ->references(['tenant_id', 'id'])
                ->on('receipts')
                ->restrictOnDelete();
        });
    }
};
