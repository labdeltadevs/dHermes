<?php

use App\Support\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::createDhermesSchema();
    }

    public function down(): void
    {
        //
    }
};
