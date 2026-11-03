<?php

use App\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = ['collection_events', 'cash_session_items'];

    public function up(): void
    {
        $values = implode(', ', array_map(
            fn (string $value): string => "'".$value."'",
            PaymentMethod::values(),
        ));

        $connection = DB::connection($this->getConnection());

        foreach ($this->tables as $table) {
            $this->dropConstraint($table);

            $connection->table($table)
                ->where('payment_method', 'transfer')
                ->update(['payment_method' => PaymentMethod::DepositTransfer->value]);

            $this->addConstraint($table, $values);
        }
    }

    public function down(): void
    {
        $values = implode(', ', array_map(
            fn (string $value): string => "'".$value."'",
            ['cash', 'check', 'transfer', 'other'],
        ));

        $connection = DB::connection($this->getConnection());

        foreach ($this->tables as $table) {
            $this->dropConstraint($table);

            $connection->table($table)
                ->where('payment_method', PaymentMethod::DepositTransfer->value)
                ->update(['payment_method' => 'transfer']);

            $this->addConstraint($table, $values);
        }
    }

    private function constraintName(string $table): string
    {
        return $table.'_payment_method_check';
    }

    private function dropConstraint(string $table): void
    {
        $connection = DB::connection($this->getConnection());

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement('ALTER TABLE "'.$table.'" DROP CONSTRAINT IF EXISTS "'.$this->constraintName($table).'"');
    }

    private function addConstraint(string $table, string $values): void
    {
        $connection = DB::connection($this->getConnection());

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement('ALTER TABLE "'.$table.'" ADD CONSTRAINT "'.$this->constraintName($table).'" CHECK ("payment_method" IN ('.$values.'))');
    }
};
