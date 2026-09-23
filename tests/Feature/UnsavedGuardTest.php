<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\Calculator;
use App\Models\RouteCalculation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFleet;
use Tests\TestCase;

/**
 * The unsaved-changes guard is Alpine (resources/js/app.js) and cannot be
 * exercised from here — but the contract it leans on can.
 *
 * "Salvează și continuă" navigates only when save() returns true. If that ever
 * stopped being the case, a failed validation would send the user to the next
 * page and leave the errors behind on one they can no longer see.
 */
class UnsavedGuardTest extends TestCase
{
    use CreatesFleet;
    use RefreshDatabase;

    public function test_save_reports_true_only_once_the_route_is_stored(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $this->spreadsheetTruck()->id)
            ->set('distance_km', '440')
            ->set('days', '3')
            ->call('save')
            ->assertHasNoErrors()
            ->assertReturned(true);

        $this->assertSame(1, RouteCalculation::count());
    }

    public function test_save_does_not_report_true_when_validation_fails(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', '')
            ->set('distance_km', '')
            ->call('save')
            ->assertHasErrors(['destination', 'distance_km'])
            // Anything but a true keeps the guard on the page; the action never
            // reached its `return true`.
            ->assertReturned(fn ($returned): bool => $returned !== true);

        $this->assertSame(0, RouteCalculation::count());
    }

    /**
     * The guard reads dirtiness off the inputs, so the page has to carry the ids
     * it is told to watch. A renamed field would silently disarm it.
     */
    public function test_the_calculator_renders_every_field_the_guard_watches(): void
    {
        $html = $this->get(route('dashboard.calculator'))->assertOk()->getContent();

        foreach (['origin', 'destination', 'distance_km', 'tonnes', 'price_per_tonne', 'notes'] as $field) {
            $this->assertStringContainsString('id="field-'.$field.'"', $html, "Câmpul {$field} lipsește din pagină.");
        }

        $this->assertStringContainsString('unsavedGuard(', $html);
    }
}
