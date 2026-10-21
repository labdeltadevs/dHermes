<?php

use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('invoice_id');
            $table->uuid('seller_user_id');
            $table->decimal('amount', 19, 6);
            $table->char('currency', 3);
            $table->decimal('local_amount', 19, 6)->nullable();
            $table->enum('payment_method', PaymentMethod::values());
            $table->date('collected_on');
            $table->enum('kind', CollectionKind::values());
            $table->enum('status', CollectionStatus::values())->default(CollectionStatus::Pending->value);
            $table->uuid('matched_receipt_id')->nullable();
            $table->timestampTz('matched_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['tenant_id', 'matched_receipt_id'])->references(['tenant_id', 'id'])->on('receipts')->restrictOnDelete();
            $table->foreign('seller_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['tenant_id', 'seller_user_id', 'status']);
            $table->index(['tenant_id', 'invoice_id']);
        });

        DatabaseSchema::addUuidDefaults('collection_events');
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_events');
    }
};
