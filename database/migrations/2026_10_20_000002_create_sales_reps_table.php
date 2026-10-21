<?php

use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_reps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('slpcode');
            $table->string('name');
            $table->string('email')->nullable();
            $table->boolean('active')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'slpcode']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'active']);
        });

        DatabaseSchema::addUuidDefaults('sales_reps');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_reps');
    }
};
