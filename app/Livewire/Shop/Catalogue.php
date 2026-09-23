<?php

namespace App\Livewire\Shop;

use App\Models\Package;
use App\Shop\Region;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The storefront listing: every package on sale, searchable and filterable by
 * region.
 *
 * The filters live in the query string so a filtered view can be linked to --
 * the home page's region shortcuts are exactly such links.
 */
class Catalogue extends Component
{
    /**
     * The sort orders a visitor may choose, keyed by their query-string value.
     *
     * @var list<string>
     */
    public const array SORTS = ['featured', 'price-asc', 'price-desc', 'newest'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $region = '';

    #[Url(except: 'featured')]
    public string $sort = 'featured';

    /**
     * The packages matching the current search, filter and sort.
     *
     * An unknown region or sort from a hand-edited URL is ignored rather than
     * rejected: the visitor still sees the catalogue.
     *
     * @return Collection<int, Package>
     */
    #[Computed]
    public function packages(): Collection
    {
        $search = trim($this->search);
        $region = Region::tryFrom($this->region);

        $query = Package::query()
            ->active()
            ->when($search !== '', fn ($query) => $query->whereAny(
                ['title', 'location', 'country', 'summary'],
                'like',
                '%'.$search.'%',
            ))
            ->when($region !== null, fn ($query) => $query->where('region', $region));

        match ($this->sort) {
            'price-asc' => $query->orderBy('price_cents'),
            'price-desc' => $query->orderByDesc('price_cents'),
            'newest' => $query->latest(),
            default => $query->orderByDesc('is_featured')->ordered(),
        };

        return $query->get();
    }

    /**
     * The regions that have at least one package on sale, for the filter chips.
     *
     * @return list<Region>
     */
    #[Computed]
    public function regions(): array
    {
        $values = Package::query()->active()->distinct()->pluck('region')->all();

        return array_values(array_filter(
            Region::cases(),
            fn (Region $region): bool => in_array($region, $values, true),
        ));
    }

    /**
     * Show every package again.
     */
    public function clearFilters(): void
    {
        $this->reset(['search', 'region', 'sort']);
    }

    /**
     * Render the catalogue inside the public layout.
     */
    public function render(): View
    {
        return view('livewire.shop.catalogue')
            ->layout('layouts::public', [
                'title' => __('Drone footage packages'),
                'description' => __('Browse licensed 4K and 5.4K drone footage packages from locations around the world.'),
            ]);
    }
}
