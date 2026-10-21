<?php

use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('docentry');
            $table->string('number');
            $table->uuid('customer_id');
            $table->date('receipt_date');
            $table->boolean('cancelled')->default(false);
            $table->char('currency', 3);
            $table->decimal('total', 19, 6);
            $table->decimal('local_total', 19, 6)->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'docentry']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'receipt_date']);
        });

        DatabaseSchema::addUuidDefaults('receipts');
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
