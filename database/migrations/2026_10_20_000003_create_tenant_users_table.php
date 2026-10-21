<?php

use App\Enums\TenantRole;
use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->enum('role', TenantRole::values());
            $table->uuid('sales_rep_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'user_id']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'sales_rep_id'])->references(['tenant_id', 'id'])->on('sales_reps')->restrictOnDelete();
            $table->index(['tenant_id', 'role', 'active']);
        });

        DatabaseSchema::addUuidDefaults('tenant_users');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
