<?php

use App\Enums\CollectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_events', function (Blueprint $table) {
            $table->string('status')->default(CollectionStatus::Pending->value)->change();
        });
    }

    public function down(): void
    {
        DB::table('collection_events')
            ->where('status', CollectionStatus::Cancelled->value)
            ->update(['status' => 'discrepancy']);

        Schema::table('collection_events', function (Blueprint $table) {
            $table->enum('status', CollectionStatus::values())->default(CollectionStatus::Pending->value)->change();
        });
    }
};
