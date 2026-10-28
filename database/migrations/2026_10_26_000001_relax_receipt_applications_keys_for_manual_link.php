<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_applications', function (Blueprint $table) {
            $table->string('docnum')->nullable()->change();
            $table->integer('invoiceid')->nullable()->change();
            $table->integer('instid')->nullable()->change();
        });

        DB::statement(
            'CREATE UNIQUE INDEX receipt_applications_manual_link_unique '
            .'ON receipt_applications (tenant_id, receipt_id, invoice_id) WHERE instid IS NULL',
        );
    }

    public function down(): void
    {
        Schema::table('receipt_applications', function (Blueprint $table) {
            $table->string('docnum')->change();
            $table->integer('invoiceid')->change();
            $table->integer('instid')->change();
        });

        DB::statement('DROP INDEX IF EXISTS receipt_applications_manual_link_unique');
    }
};
