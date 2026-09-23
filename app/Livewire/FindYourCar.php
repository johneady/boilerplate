<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Voltiva\Money;
use App\Voltiva\VehicleCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Find your car": four plain questions that narrow the range.
 *
 * Answers the brief's "which car suits them" in the customer's terms --
 * licence, roads, distance, budget -- and explains every recommendation, so
 * the customer learns why an L6e will not do for the Ma-13 rather than just
 * seeing it disappear. Hard requirements (licence, road speed) rule a car
 * out; soft ones (range, budget) move it to "also worth a look".
 */
class FindYourCar extends Component
{
    /**
     * @var array<string, string>
     */
    public const array LICENCES = [
        'am' => 'AM moped licence, or no licence yet',
        'car' => 'B1 or a normal car licence (B)',
    ];

    /**
     * @var array<string, string>
     */
    public const array ROADS = [
        'town' => 'Town and village streets',
        'main' => 'Main roads between towns too',
    ];

    /**
     * Daily distance bands. Word keys rather than the figures themselves,
     * which PHP would turn into integer array keys.
     *
     * @var array<string, string>
     */
    public const array DISTANCES = [
        'short' => 'Up to 20 km',
        'medium' => '20 – 50 km',
        'long' => 'More than 50 km',
    ];

    /**
     * The daily distance each band plans for, in km.
     *
     * @var array<string, int>
     */
    private const array DISTANCE_KM = ['short' => 20, 'medium' => 50, 'long' => 90];

    /**
     * Budget bands ('any' has no ceiling).
     *
     * @var array<string, string>
     */
    public const array BUDGETS = [
        'up_to_10k' => 'Up to €10,000',
        'up_to_15k' => 'Up to €15,000',
        'any' => 'Flexible, or with finance',
    ];

    /**
     * The ceiling of each capped budget band, in euros.
     *
     * @var array<string, int>
     */
    private const array BUDGET_EUROS = ['up_to_10k' => 10000, 'up_to_15k' => 15000];

    #[Url]
    public string $licence = '';

    #[Url]
    public string $roads = '';

    #[Url]
    public string $distance = '';

    #[Url]
    public string $budget = '';

    /**
     * Forget every answer.
     */
    public function startOver(): void
    {
        $this->reset(['licence', 'roads', 'distance', 'budget']);
    }

    /**
     * How many of the four questions have an answer.
     */
    #[Computed]
    public function answered(): int
    {
        return count(array_filter([
            array_key_exists($this->licence, self::LICENCES),
            array_key_exists($this->roads, self::ROADS),
            array_key_exists($this->distance, self::DISTANCES),
            array_key_exists($this->budget, self::BUDGETS),
        ]));
    }

    /**
     * Every published car with its verdict: 'match', 'consider' (misses a
     * soft preference) or 'unsuitable' (misses a hard requirement), and the
     * reasons behind it. Matches first.
     *
     * @return list<array{vehicle: Vehicle, verdict: string, reasons: list<array{ok: bool, text: string}>}>
     */
    #[Computed]
    public function results(): array
    {
        $order = ['match' => 0, 'consider' => 1, 'unsuitable' => 2];

        return array_values($this->vehicles()
            ->map(fn (Vehicle $vehicle): array => $this->assess($vehicle))
            ->sortBy(fn (array $result): int => $order[$result['verdict']])
            ->all());
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function vehicles(): Collection
    {
        return Vehicle::query()->published()->ordered()->get();
    }

    /**
     * Judge one car against the answers given so far.
     *
     * @return array{vehicle: Vehicle, verdict: string, reasons: list<array{ok: bool, text: string}>}
     */
    private function assess(Vehicle $vehicle): array
    {
        $reasons = [];
        $hardMiss = false;
        $softMiss = false;

        if ($this->licence === 'am') {
            $ok = $vehicle->category === VehicleCategory::L6e;
            $hardMiss = ! $ok;
            $reasons[] = ['ok' => $ok, 'text' => $ok
                ? __('You can drive it with an AM licence')
                : __('Needs a B1 or car licence')];
        } elseif ($this->licence === 'car') {
            $reasons[] = ['ok' => true, 'text' => __('Your licence covers it')];
        }

        if ($this->roads === 'main') {
            $ok = $vehicle->top_speed_kmh >= 70;
            $hardMiss = $hardMiss || ! $ok;
            $reasons[] = ['ok' => $ok, 'text' => $ok
                ? __('Keeps up with main-road traffic at up to :speed km/h', ['speed' => $vehicle->top_speed_kmh])
                : __('Limited to :speed km/h, so best kept to town', ['speed' => $vehicle->top_speed_kmh])];
        } elseif ($this->roads === 'town') {
            $reasons[] = ['ok' => true, 'text' => __('Compact and easy to park in town')];
        }

        if (array_key_exists($this->distance, self::DISTANCE_KM)) {
            // Room for a return trip plus a margin, so a customer is not
            // sold a car that only just makes it on a hot day with the AC on.
            $ok = $vehicle->range_km >= self::DISTANCE_KM[$this->distance] * 1.5;
            $softMiss = ! $ok;
            $reasons[] = ['ok' => $ok, 'text' => $ok
                ? __('Up to :range km on a charge covers your day comfortably', ['range' => $vehicle->range_km])
                : __('Up to :range km on a charge – you would charge more often', ['range' => $vehicle->range_km])];
        }

        if (array_key_exists($this->budget, self::BUDGET_EUROS)) {
            $ok = $vehicle->price_cents <= self::BUDGET_EUROS[$this->budget] * 100;
            $softMiss = $softMiss || ! $ok;
            $reasons[] = ['ok' => $ok, 'text' => $ok
                ? __('Within your budget at :price', ['price' => $vehicle->formattedPrice()])
                : __(':price – over budget, but finance can spread the cost', ['price' => $vehicle->formattedPrice()])];
        } elseif ($this->budget === 'any' && $vehicle->monthly_from_cents !== null) {
            $reasons[] = ['ok' => true, 'text' => __('Finance from :amount a month', ['amount' => Money::format($vehicle->monthly_from_cents)])];
        }

        return [
            'vehicle' => $vehicle,
            'verdict' => $hardMiss ? 'unsuitable' : ($softMiss ? 'consider' : 'match'),
            'reasons' => $reasons,
        ];
    }

    public function render(): View
    {
        return view('livewire.find-your-car')
            ->layout('layouts::public', [
                'title' => __('Find your car'),
                'description' => __('Answer four quick questions and see which Voltiva electric car suits the way you drive in Mallorca.'),
            ]);
    }
}
