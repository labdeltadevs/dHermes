<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_events', function (Blueprint $table) {
            $table->string('voucher_code', 64)->nullable()->after('notes');
            $table->string('proof_disk', 32)->nullable()->after('voucher_code');
            $table->string('proof_path', 512)->nullable()->after('proof_disk');
            $table->string('proof_original_name', 255)->nullable()->after('proof_path');
            $table->string('proof_mime_type', 127)->nullable()->after('proof_original_name');
            $table->unsignedBigInteger('proof_size')->nullable()->after('proof_mime_type');

            $table->unique(['tenant_id', 'voucher_code'], 'collection_events_tenant_voucher_unique');
        });
    }

    public function down(): void
    {
        Schema::table('collection_events', function (Blueprint $table) {
            $table->dropUnique('collection_events_tenant_voucher_unique');

            $table->dropColumn([
                'voucher_code',
                'proof_disk',
                'proof_path',
                'proof_original_name',
                'proof_mime_type',
                'proof_size',
            ]);
        });
    }
};
