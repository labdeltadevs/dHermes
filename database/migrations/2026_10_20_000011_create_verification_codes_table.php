<?php

use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('verifiable_type');
            $table->uuid('verifiable_id');
            $table->string('code', 64)->unique();
            $table->string('signature', 64);
            $table->timestampTz('issued_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'verifiable_type', 'verifiable_id'], 'verification_codes_subject_index');
            $table->index(['tenant_id', 'code']);
        });

        DatabaseSchema::addUuidDefaults('verification_codes');
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};
