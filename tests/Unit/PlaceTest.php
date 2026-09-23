<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\RouteCalculation;
use App\Support\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Place names come from the routes themselves, and the same town typed two ways
 * has to end up as one.
 */
class PlaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Place::forget();
    }

    private function route(string $origin, string $destination, ?string $return = null): RouteCalculation
    {
        Place::forget();

        return RouteCalculation::create([
            'origin' => $origin,
            'destination' => $destination,
            'return_destination' => $return,
            'distance_km' => 220, 'return_distance_km' => 220,
            'tonnes' => 25, 'price_per_tonne' => 35, 'eur_rate' => 19.9,
            'consumption' => 0.45, 'fuel_price' => 32, 'wear_per_km' => 2,
            'days' => 3, 'driver_first_day' => 2200, 'driver_extra_day' => 1000,
        ]);
    }

    public function test_it_offers_nothing_before_the_first_route(): void
    {
        $this->assertSame([], Place::known());
    }

    public function test_it_collects_every_place_a_route_mentions(): void
    {
        $this->route('Hâncești', 'Brăila', 'Galați');
        Place::forget();

        $this->assertSame(['Brăila', 'Galați', 'Hâncești'], Place::known());
    }

    public function test_it_lists_a_town_once_however_many_routes_use_it(): void
    {
        $this->route('Chișinău', 'Iași');
        $this->route('Chișinău', 'Galați');
        Place::forget();

        $this->assertSame(['Chișinău', 'Galați', 'Iași'], Place::known());
    }

    /**
     * The whole point: a second spelling must not become a second town.
     */
    public function test_a_typed_name_snaps_onto_the_spelling_already_on_file(): void
    {
        $this->route('Chișinău', 'Brăila');
        Place::forget();

        $this->assertSame('Chișinău', Place::canonical('Chisinau'));
        $this->assertSame('Chișinău', Place::canonical('chisinau'));
        $this->assertSame('Chișinău', Place::canonical('CHISINAU'));
        $this->assertSame('Chișinău', Place::canonical('  Chișinău  '));
    }

    public function test_a_genuinely_new_town_is_kept_as_typed_but_tidied(): void
    {
        $this->route('Chișinău', 'Brăila');
        Place::forget();

        $this->assertSame('Cahul', Place::canonical('Cahul'));
        $this->assertSame('Sfântu Gheorghe', Place::canonical('  Sfântu   Gheorghe '));
        $this->assertSame('', Place::canonical('   '));
        $this->assertSame('', Place::canonical(null));
    }

    /**
     * Two spellings already in the data would otherwise both appear in the
     * suggestions, which is the confusion this is meant to end.
     */
    public function test_the_suggestions_carry_one_spelling_per_town(): void
    {
        $this->route('Chișinău', 'Brăila');
        $this->route('Chisinau', 'Galați');
        Place::forget();

        $known = Place::known();

        $this->assertCount(3, $known);
        $this->assertContains('Brăila', $known);
        $this->assertContains('Galați', $known);
        $this->assertSame(
            1,
            count(array_filter($known, static fn (string $n): bool => str_starts_with(mb_strtolower($n), 'chi'))),
            'Chișinău și Chisinau trebuie să apară ca o singură sugestie.',
        );
    }

    public function test_an_empty_return_point_is_not_offered(): void
    {
        $this->route('Hâncești', 'Brăila', null);
        Place::forget();

        $this->assertSame(['Brăila', 'Hâncești'], Place::known());
    }
}
