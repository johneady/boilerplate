<?php

namespace App\Ordering;

use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;

/**
 * The times a customer may choose to collect or receive their order.
 *
 * Every half hour the café is open, today and tomorrow, starting no sooner
 * than the kitchen's preparation time from now. Worked out in the café's own
 * timezone (the Timezone setting) and handed back in it, so "9:30 am" means
 * 9:30 at the café wherever the server is.
 */
class ReadyTimes
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return list<CarbonImmutable>
     */
    public function available(?CarbonImmutable $now = null): array
    {
        $timezone = $this->settings->string(SettingKey::Timezone);
        $now = ($now ?? CarbonImmutable::now())->setTimezone($timezone);
        $earliest = $now->addMinutes((int) config('ordering.preparation_minutes'));

        $times = [];

        foreach ([$now, $now->addDay()] as $day) {
            $slot = $day->setTimeFromTimeString((string) config('ordering.opens_at'));
            $closes = $day->setTimeFromTimeString((string) config('ordering.closes_at'));

            while ($slot->lessThanOrEqualTo($closes)) {
                if ($slot->greaterThanOrEqualTo($earliest)) {
                    $times[] = $slot;
                }

                $slot = $slot->addMinutes(30);
            }
        }

        return $times;
    }

    /**
     * Whether the given time is one of the times on offer.
     */
    public function isAvailable(CarbonImmutable $time, ?CarbonImmutable $now = null): bool
    {
        foreach ($this->available($now) as $slot) {
            if ($slot->equalTo($time)) {
                return true;
            }
        }

        return false;
    }
}
