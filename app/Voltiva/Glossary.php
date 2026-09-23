<?php

namespace App\Voltiva;

/**
 * Plain-language explanations of the technical terms used across the site.
 *
 * The brief asks for benefits first and jargon explained, so every term a
 * customer might not know is rendered through <x-voltiva.term>, which opens
 * the explanation from here in a small pop-up. One list, so "kWh" means the
 * same thing on every page it appears.
 *
 * Plain English, translated where it is displayed (lang/*.json), like the
 * other enums in this namespace.
 */
class Glossary
{
    /**
     * @var array<string, array{term: string, title: string, explanation: string}>
     */
    private const array TERMS = [
        'l6e' => [
            'term' => 'L6e',
            'title' => 'L6e light quadricycle',
            'explanation' => 'An EU class for small, light electric cars limited to 45 km/h. In Spain you can drive one from age 15 with an AM moped licence, which makes them ideal for town driving.',
        ],
        'l7e' => [
            'term' => 'L7e',
            'title' => 'L7e heavy quadricycle',
            'explanation' => 'An EU class for compact electric cars that can reach up to 90 km/h. You need a B1 licence (from 16) or a normal car licence, and they can use main roads around the island.',
        ],
        'ah' => [
            'term' => 'Ah',
            'title' => 'Ah (amp-hours)',
            'explanation' => 'How much charge a battery can hold. At the same voltage, a higher Ah number means a bigger battery and more range between charges.',
        ],
        'kwh' => [
            'term' => 'kWh',
            'title' => 'kWh (kilowatt-hours)',
            'explanation' => 'The amount of energy a battery stores – the best single number for comparing batteries. It is also how your electricity is billed, so it tells you roughly what a full charge costs.',
        ],
        'coc' => [
            'term' => 'CoC',
            'title' => 'CoC (Certificate of Conformity)',
            'explanation' => 'A document from the manufacturer confirming the car matches its EU-approved design. It is needed to register the car in Spain, and we supply it with every car.',
        ],
        'vin' => [
            'term' => 'VIN',
            'title' => 'VIN (Vehicle Identification Number)',
            'explanation' => 'A unique 17-character code stamped on the car – like a fingerprint. It appears on the registration papers and identifies the car for insurance and servicing.',
        ],
        'lifepo4' => [
            'term' => 'LiFePO4',
            'title' => 'LiFePO4 (lithium iron phosphate)',
            'explanation' => 'A type of lithium battery known for safety and long life. It handles heat well, can be charged thousands of times and does not need topping up or maintenance.',
        ],
        'eps' => [
            'term' => 'EPS',
            'title' => 'EPS (Electric Power Steering)',
            'explanation' => 'An electric motor that helps you turn the steering wheel, so parking and tight streets feel light and easy.',
        ],
        'type-approval' => [
            'term' => 'EU type approval',
            'title' => 'EU type approval',
            'explanation' => 'Official confirmation that a vehicle model meets EU safety and environmental rules. Only type-approved cars can be registered and driven legally on Spanish roads.',
        ],
    ];

    /**
     * Every term, keyed by its glossary key.
     *
     * @return array<string, array{term: string, title: string, explanation: string}>
     */
    public static function all(): array
    {
        return self::TERMS;
    }

    /**
     * One term, or null for a key nobody defined.
     *
     * @return array{term: string, title: string, explanation: string}|null
     */
    public static function find(string $key): ?array
    {
        return self::TERMS[$key] ?? null;
    }
}
