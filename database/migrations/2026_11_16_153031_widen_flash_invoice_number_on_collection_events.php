<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_events', function (Blueprint $table): void {
            $table->string('flash_invoice_number', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('collection_events', function (Blueprint $table): void {
            $table->string('flash_invoice_number', 32)->nullable()->change();
        });
    }
};
