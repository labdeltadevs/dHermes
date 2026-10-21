<?php

use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('cardcode');
            $table->string('name');
            $table->string('tax_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->uuid('sales_rep_id')->nullable();
            $table->uuid('collector_user_id')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('credit_limit', 19, 6)->nullable();
            $table->boolean('active')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'cardcode']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'sales_rep_id'])->references(['tenant_id', 'id'])->on('sales_reps')->restrictOnDelete();
            $table->foreign('collector_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'sales_rep_id']);
            $table->index(['tenant_id', 'collector_user_id']);
        });

        DatabaseSchema::addUuidDefaults('customers');
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
