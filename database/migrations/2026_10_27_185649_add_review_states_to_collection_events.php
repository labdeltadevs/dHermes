<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_events', function (Blueprint $table): void {
            $table->string('erp_docnum', 32)->nullable()->after('status');
            $table->uuid('reviewed_by')->nullable()->after('erp_docnum');
            $table->timestampTz('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            Schema::table('collection_events', function (Blueprint $table): void {
                $table->foreign('reviewed_by')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            });
        }

        $this->mapLegacyStatuses();

        Schema::table('collection_events', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'erp_docnum'], 'collection_events_tenant_erp_docnum_unique');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            Schema::table('collection_events', function (Blueprint $table): void {
                $table->dropForeign('collection_events_verified_receipt_id_foreign');
            });

            Schema::withoutForeignKeyConstraints(function (): void {
                Schema::table('collection_events', function (Blueprint $table): void {
                    $table->dropColumn(['verified_receipt_id', 'verified_at']);
                });
            });
        }
    }

    public function down(): void
    {
        DB::table('collection_events')
            ->where('status', 'accepted')
            ->update(['status' => 'verified']);

        DB::table('collection_events')
            ->where('status', 'rejected')
            ->update(['status' => 'discrepancy']);

        Schema::table('collection_events', function (Blueprint $table): void {
            $table->dropUnique('collection_events_tenant_erp_docnum_unique');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            Schema::table('collection_events', function (Blueprint $table): void {
                $table->dropForeign(['reviewed_by']);
            });
        }

        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::table('collection_events', function (Blueprint $table): void {
                $table->dropColumn(['erp_docnum', 'reviewed_by', 'reviewed_at', 'rejection_reason']);
            });
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            Schema::table('collection_events', function (Blueprint $table): void {
                $table->uuid('verified_receipt_id')->nullable();
                $table->timestampTz('verified_at')->nullable();

                $table->foreign(['tenant_id', 'verified_receipt_id'], 'collection_events_verified_receipt_id_foreign')
                    ->references(['tenant_id', 'id'])
                    ->on('receipts')
                    ->restrictOnDelete();
            });
        }
    }

    private function mapLegacyStatuses(): void
    {
        $legacyVerified = DB::table('collection_events as events')
            ->leftJoin('receipts as receipts', function ($join): void {
                $join->on('receipts.id', '=', 'events.verified_receipt_id')
                    ->on('receipts.tenant_id', '=', 'events.tenant_id');
            })
            ->whereIn('events.status', ['verified', 'matched'])
            ->get([
                'events.id',
                'events.tenant_id',
                'events.verified_at',
                'receipts.number as receipt_number',
            ]);

        /** @var array<string, true> $usedDocnums */
        $usedDocnums = [];

        foreach ($legacyVerified as $event) {
            $erpDocnum = is_string($event->receipt_number) && $event->receipt_number !== ''
                ? $event->receipt_number
                : null;

            if ($erpDocnum !== null) {
                $documentKey = $event->tenant_id.'|'.$erpDocnum;

                if (isset($usedDocnums[$documentKey])) {
                    $erpDocnum = null;
                } else {
                    $usedDocnums[$documentKey] = true;
                }
            }

            DB::table('collection_events')
                ->where('id', $event->id)
                ->update([
                    'status' => $erpDocnum === null ? 'pending' : 'accepted',
                    'erp_docnum' => $erpDocnum,
                    'reviewed_at' => $erpDocnum === null ? null : $event->verified_at,
                ]);
        }

        DB::table('collection_events')
            ->where('status', 'registered')
            ->update(['status' => 'pending']);

        DB::table('collection_events')
            ->where('status', 'discrepancy')
            ->update(['status' => 'rejected']);
    }
};
