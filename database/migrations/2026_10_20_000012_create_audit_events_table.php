<?php

use App\Enums\ActorKind;
use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('actor_user_id')->nullable();
            $table->enum('actor_kind', ActorKind::values());
            $table->string('action');
            $table->string('entity');
            $table->string('entity_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->jsonb('details')->default('{}');
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'entity', 'entity_id']);
        });

        DatabaseSchema::addUuidDefaults('audit_events');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
