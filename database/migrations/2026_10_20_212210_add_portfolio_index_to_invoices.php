<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX invoices_open_tenant_due_date_index ON invoices (tenant_id, due_date) WHERE cancelled = false AND balance > 0');

            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'cancelled', 'balance', 'due_date'],
                'invoices_tenant_cancelled_balance_due_date_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS invoices_open_tenant_due_date_index');

            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_tenant_cancelled_balance_due_date_index');
        });
    }
};
