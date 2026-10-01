<?php

namespace App\Livewire\Travel;

use App\Models\Destination;
use App\Models\Tour;
use App\Travel\TourStyle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The tours listing with filters that live in the URL, so a filtered list can
 * be bookmarked or shared and the home page's search lands on it pre-filled.
 */
class TourFinder extends Component
{
    /**
     * The duration bands offered, keyed by their URL value: [min, max] days.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    public const array DURATIONS = [
        'short' => [1, 6],
        'week' => [7, 10],
        'long' => [11, 99],
    ];

    /**
     * @var array<string, string>
     */
    public const array SORTS = [
        'recommended' => 'Recommended',
        'price-asc' => 'Price: low to high',
        'price-desc' => 'Price: high to low',
        'duration' => 'Shortest first',
    ];

    #[Url(except: '')]
    public string $destination = '';

    #[Url(except: '')]
    public string $style = '';

    #[Url(except: '')]
    public string $duration = '';

    #[Url(except: 'recommended')]
    public string $sort = 'recommended';

    public function clearFilters(): void
    {
        $this->reset(['destination', 'style', 'duration', 'sort']);
    }

    /**
     * @return Collection<int, Tour>
     */
    #[Computed]
    public function tours(): Collection
    {
        $query = Tour::query()
            ->published()
            ->with(['destination', 'bookableDepartures'])
            ->when($this->destination !== '', fn (Builder $query) => $query->whereRelation('destination', 'slug', $this->destination))
            ->when(TourStyle::tryFrom($this->style), fn (Builder $query, TourStyle $style) => $query->where('style', $style))
            ->when(self::DURATIONS[$this->duration] ?? null, fn (Builder $query, array $band) => $query->whereBetween('duration_days', $band));

        match ($this->sort) {
            'price-asc' => $query->orderBy('price_per_person_cents'),
            'price-desc' => $query->orderByDesc('price_per_person_cents'),
            'duration' => $query->orderBy('duration_days'),
            default => $query->orderByDesc('is_featured')->ordered(),
        };

        return $query->get();
    }

    /**
     * @return Collection<int, Destination>
     */
    #[Computed]
    public function destinations(): Collection
    {
        return Destination::query()->ordered()->get(['id', 'slug', 'name']);
    }

    public function hasFilters(): bool
    {
        return $this->destination !== '' || $this->style !== '' || $this->duration !== '';
    }

    public function render(): View
    {
        return view('livewire.travel.tour-finder')
            ->layout('layouts::public', [
                'title' => __('Tours & packages'),
                'description' => __('Small-group tours with local guides: browse by destination, travel style and trip length.'),
            ]);
    }
}
