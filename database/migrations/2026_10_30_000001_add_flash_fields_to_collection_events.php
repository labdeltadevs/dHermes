<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_events', function (Blueprint $table) {
            $table->uuid('invoice_id')->nullable()->change();

            $table->string('flash_invoice_number', 32)->nullable();
            $table->string('flash_cardcode', 32)->nullable();
            $table->string('flash_customer_name', 255)->nullable();

            $table->index(
                ['tenant_id', 'flash_invoice_number'],
                'collection_events_tenant_flash_invoice_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('collection_events', function (Blueprint $table) {
            $table->dropIndex('collection_events_tenant_flash_invoice_index');

            $table->dropColumn([
                'flash_invoice_number',
                'flash_cardcode',
                'flash_customer_name',
            ]);

            $table->uuid('invoice_id')->nullable(false)->change();
        });
    }
};
