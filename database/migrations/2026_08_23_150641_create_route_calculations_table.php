<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_calculations', function (Blueprint $table): void {
            $table->id();

            $table->string('origin');
            $table->string('destination');

            /*
             * The vehicle is a starting point, not a dependency: picking one fills
             * the consumption and wear fields, and both stay editable afterwards.
             * So every figure below is copied onto the row rather than read
             * through the relation — retuning a truck, or moving the exchange
             * rate, must not silently rewrite the profit on a route quoted six
             * months ago.
             */
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('distance_km', 10, 2);

            // Revenue is priced per tonne in euro and settled in lei, so both the
            // load and the rate that converted it belong on the row.
            $table->decimal('tonnes', 10, 2)->default(0);
            $table->decimal('price_per_tonne', 10, 2)->default(0);
            $table->decimal('eur_rate', 10, 4);

            $table->decimal('consumption', 8, 4);
            $table->decimal('fuel_price', 10, 3);
            $table->decimal('wear_per_km', 10, 3)->default(0);

            // Flat per run. Defaulted from the settings, then editable — a route
            // that crosses no border simply carries a zero.
            $table->decimal('customs', 10, 2)->default(0);
            $table->decimal('vignette', 10, 2)->default(0);
            $table->decimal('other_costs', 10, 2)->default(0);

            // Driver pay: the first day, then a lower rate for each day after it.
            $table->unsignedSmallInteger('days')->default(1);
            $table->decimal('driver_first_day', 10, 2)->default(0);
            $table->decimal('driver_extra_day', 10, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_calculations');
    }
};
