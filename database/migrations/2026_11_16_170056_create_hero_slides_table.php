<?php

use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 120);
            $table->text('body');
            $table->boolean('is_published')->default(false);
            $table->integer('sort_order')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['is_published', 'sort_order']);
        });

        DatabaseSchema::addUuidDefaults('hero_slides');
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
