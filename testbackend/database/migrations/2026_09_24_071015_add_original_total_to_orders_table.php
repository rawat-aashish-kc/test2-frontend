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
        Schema::table('orders', function (Blueprint $table) {
            // Nullable at the DB level (avoids a doctrine/dbal column-change dependency);
            // application code always sets it at order placement, so it's never actually
            // null for a new order. Backfilled below for any order placed before this column existed.
            $table->decimal('original_total', 10, 2)->nullable()->after('total');
        });

        DB::table('orders')->whereNull('original_total')->update(['original_total' => DB::raw('total')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('original_total');
        });
    }
};
