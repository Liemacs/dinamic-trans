<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some customers pay in dollars.
 *
 * A route now records which currency it was quoted in, and `eur_rate` is renamed
 * to `quote_rate` because it no longer only ever holds a euro rate — it holds
 * however many lei one unit of that route's currency was worth on the day.
 *
 * Every row already in the table was quoted in euro, which is exactly the
 * default, so nothing needs backfilling beyond the rename.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_calculations', function (Blueprint $table): void {
            $table->string('quote_currency', 3)->default('EUR')->after('destination');
            $table->renameColumn('eur_rate', 'quote_rate');
        });
    }

    public function down(): void
    {
        Schema::table('route_calculations', function (Blueprint $table): void {
            $table->renameColumn('quote_rate', 'eur_rate');
            $table->dropColumn('quote_currency');
        });
    }
};
