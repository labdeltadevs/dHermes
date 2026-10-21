<?php

use App\Enums\InvoiceStatus;
use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('docentry');
            $table->string('number');
            $table->string('series')->nullable();
            $table->uuid('customer_id');
            $table->uuid('sales_rep_id')->nullable();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->enum('status', InvoiceStatus::values())->default(InvoiceStatus::Unknown->value);
            $table->boolean('cancelled')->default(false);
            $table->char('currency', 3);
            $table->decimal('total', 19, 6);
            $table->decimal('paid', 19, 6)->default(0);
            $table->decimal('balance', 19, 6);
            $table->char('local_currency', 3)->nullable();
            $table->decimal('local_total', 19, 6)->nullable();
            $table->decimal('local_paid', 19, 6)->nullable();
            $table->decimal('local_balance', 19, 6)->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'docentry']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'sales_rep_id'])->references(['tenant_id', 'id'])->on('sales_reps')->restrictOnDelete();
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'sales_rep_id', 'status']);
            $table->index(['tenant_id', 'due_date']);
        });

        DatabaseSchema::addUuidDefaults('invoices');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
