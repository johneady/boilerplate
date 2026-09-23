<?php

namespace App\Voltiva;

/**
 * What a News & Advice article is about, and so where it links back to.
 *
 * Each topic maps onto one part of the site -- the car range or one of the
 * "Why Voltiva" pages -- which is how an article written without a designer
 * still ends with a link into the right section, and how those pages list
 * the articles written about them.
 */
enum ArticleTopic: string
{
    case Cars = 'cars';

    case Batteries = 'batteries';

    case Charging = 'charging';

    case Registration = 'registration';

    case Servicing = 'servicing';

    case Finance = 'finance';

    /**
     * The label shown on filters and article cards.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cars => 'Cars',
            self::Batteries => 'Batteries & Range',
            self::Charging => 'Charging',
            self::Registration => 'Registration',
            self::Servicing => 'Servicing',
            self::Finance => 'Finance',
        };
    }

    /**
     * The slug of the content page this topic belongs to, or null for Cars,
     * which links to the car range instead.
     */
    public function pageSlug(): ?string
    {
        return match ($this) {
            self::Cars => null,
            self::Batteries => 'batteries-and-range',
            self::Charging => 'charging',
            self::Registration => 'registration',
            self::Servicing => 'servicing-and-support',
            self::Finance => 'finance',
        };
    }

    /**
     * The topic a content page belongs to, if any.
     */
    public static function forPageSlug(string $slug): ?self
    {
        foreach (self::cases() as $topic) {
            if ($topic->pageSlug() === $slug) {
                return $topic;
            }
        }

        return null;
    }

    /**
     * Where the topic lives on the site.
     */
    public function url(): string
    {
        $slug = $this->pageSlug();

        return $slug === null ? route('cars.index') : route('pages.show', $slug);
    }
}
