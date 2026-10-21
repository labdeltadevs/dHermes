<?php

use App\Enums\CashSessionStatus;
use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('seller_user_id');
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->enum('status', CashSessionStatus::values())->default(CashSessionStatus::Open->value);
            $table->char('currency', 3);
            $table->decimal('expected_amount', 19, 6)->default(0);
            $table->decimal('counted_amount', 19, 6)->nullable();
            $table->decimal('difference', 19, 6)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('seller_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'seller_user_id', 'status']);
        });

        DatabaseSchema::addUuidDefaults('cash_sessions');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
