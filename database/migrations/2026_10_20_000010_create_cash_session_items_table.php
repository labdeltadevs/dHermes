<?php

use App\Enums\PaymentMethod;
use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_session_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('cash_session_id');
            $table->uuid('collection_event_id')->nullable();
            $table->enum('payment_method', PaymentMethod::values());
            $table->decimal('amount', 19, 6);
            $table->string('reference')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'cash_session_id'])->references(['tenant_id', 'id'])->on('cash_sessions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'collection_event_id'])->references(['tenant_id', 'id'])->on('collection_events')->restrictOnDelete();
            $table->index(['tenant_id', 'cash_session_id']);
        });

        DatabaseSchema::addUuidDefaults('cash_session_items');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_session_items');
    }
};
