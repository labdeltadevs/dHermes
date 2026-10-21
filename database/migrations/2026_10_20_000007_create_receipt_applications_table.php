<?php

use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('receipt_id');
            $table->uuid('invoice_id');
            $table->string('docnum');
            $table->integer('invoiceid');
            $table->string('docentry');
            $table->integer('instid');
            $table->string('invtype', 10)->default('13');
            $table->decimal('applied_amount', 19, 6);
            $table->decimal('local_applied_amount', 19, 6)->nullable();
            $table->date('application_date')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'receipt_id', 'invoiceid', 'instid']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'receipt_id'])->references(['tenant_id', 'id'])->on('receipts')->restrictOnDelete();
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->index(['tenant_id', 'invoice_id']);
        });

        DatabaseSchema::addUuidDefaults('receipt_applications');
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_applications');
    }
};
