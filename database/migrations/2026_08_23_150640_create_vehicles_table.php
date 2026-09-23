<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('plate')->nullable();

            // Litres per kilometre, as the source spreadsheet expresses it
            // (0.45 L/km for a loaded truck). Four decimals so the L/100km
            // figure a driver would quote survives the conversion intact.
            $table->decimal('consumption', 8, 4);

            /*
             * What the truck costs to keep on the road, held as the figures the
             * invoices actually carry rather than as one blended rate: insurance
             * and tyres arrive once a year, the GPS arrives every month, and a
             * single per-kilometre number hides both from anyone trying to check
             * it against a bill.
             *
             * The spreadsheet's "Uzură (MDL/km)" is derived from these against
             * annual_km — see Vehicle::wearPerKm().
             */
            $table->decimal('insurance_annual', 12, 2)->default(0);
            $table->decimal('service_annual', 12, 2)->default(0);
            $table->decimal('tyres_annual', 12, 2)->default(0);
            $table->decimal('other_annual', 12, 2)->default(0);

            // A subscription, so it is stored per month and annualised on read.
            $table->decimal('gps_monthly', 10, 2)->default(0);

            // The distance those yearly costs are spread over. Without it there
            // is no honest way to turn an annual invoice into a cost per km.
            $table->unsignedInteger('annual_km')->default(0);

            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
