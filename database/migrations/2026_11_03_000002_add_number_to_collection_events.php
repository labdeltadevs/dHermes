<?php

use App\Support\Collection\CollectionNumberAllocator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_events', function (Blueprint $table) {
            $table->unsignedBigInteger('number')->nullable()->after('status');
            $table->string('number_code', 32)->nullable()->after('number');

            $table->unique(['tenant_id', 'number'], 'collection_events_tenant_number_unique');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('collection_events', function (Blueprint $table) {
            $table->dropUnique('collection_events_tenant_number_unique');
            $table->dropColumn(['number', 'number_code']);
        });
    }

    private function backfill(): void
    {
        $connection = DB::connection($this->getConnection());

        $tenants = $connection->table('tenants')->select('id', 'code')->get();

        foreach ($tenants as $tenant) {
            $number = (int) $connection->table('collection_events')
                ->where('tenant_id', $tenant->id)
                ->max('number');

            $pending = $connection->table('collection_events')
                ->where('tenant_id', $tenant->id)
                ->whereNull('number')
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($pending as $id) {
                $number++;

                $connection->table('collection_events')
                    ->where('id', $id)
                    ->update([
                        'number' => $number,
                        'number_code' => CollectionNumberAllocator::format((string) $tenant->code, $number),
                    ]);
            }
        }
    }
};
