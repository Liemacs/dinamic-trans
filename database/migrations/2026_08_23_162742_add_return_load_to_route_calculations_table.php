<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The return load: sometimes the truck does not come back empty.
 *
 * It detours to a third point, picks up someone else's cargo and carries it
 * home. That adds kilometres, adds revenue, and adds a day's loading work the
 * driver is paid extra for.
 *
 * Every column defaults to zero or null, so the routes already saved are
 * untouched — they simply have no return load, which is what they had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_calculations', function (Blueprint $table): void {
            // Where it goes to load. Its presence is what marks a route as
            // having a return leg at all.
            $table->string('return_destination')->nullable()->after('destination');

            // The detour, on top of the main run — kept separate rather than
            // folded into distance_km so the original leg stays the figure that
            // was quoted for it.
            $table->decimal('return_distance_km', 10, 2)->default(0)->after('distance_km');

            $table->decimal('return_tonnes', 10, 2)->default(0)->after('tonnes');
            $table->decimal('return_price_per_tonne', 10, 2)->default(0)->after('price_per_tonne');

            // Paid once, for the day spent loading. Snapshotted like every other
            // rate on the row.
            $table->decimal('loading_day_bonus', 10, 2)->default(0)->after('driver_extra_day');
        });
    }

    public function down(): void
    {
        Schema::table('route_calculations', function (Blueprint $table): void {
            $table->dropColumn([
                'return_destination',
                'return_distance_km',
                'return_tonnes',
                'return_price_per_tonne',
                'loading_day_bonus',
            ]);
        });
    }
};
