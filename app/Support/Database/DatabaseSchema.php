<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DatabaseSchema
{
    public static function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    public static function createDhermesSchema(): void
    {
        if (! self::isPostgres()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS "dhermes"');
    }

    public static function addUuidDefaults(string ...$tables): void
    {
        if (! self::isPostgres()) {
            return;
        }

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN "id" SET DEFAULT gen_random_uuid()',
                $table,
            ));
        }
    }
}
