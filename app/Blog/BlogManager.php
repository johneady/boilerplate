<?php

namespace App\Blog;

use App\Settings\SettingKey;
use App\Settings\Settings;

/**
 * The blog module's switchboard: whether it is on.
 *
 * The single reader of SettingKey::BlogEnabled, the role PaymentManager plays
 * for payments -- callers ask the manager rather than the settings service so
 * the answer has one home, and a second blog-wide question has somewhere to
 * go without a scattering of direct reads.
 */
class BlogManager
{
    public function __construct(private readonly Settings $settings) {}

    public function enabled(): bool
    {
        return $this->settings->boolean(SettingKey::BlogEnabled);
    }
}
