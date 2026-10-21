<?php

use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('tax_id')->nullable();
            $table->string('timezone');
            $table->char('local_currency', 3);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        DatabaseSchema::addUuidDefaults('tenants');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
