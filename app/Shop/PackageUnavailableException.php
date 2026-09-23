<?php

namespace App\Shop;

use App\Models\Package;
use RuntimeException;

/**
 * Thrown when checkout finds a package that can no longer be sold: it sold out
 * or was withdrawn after the customer put it in their basket.
 */
class PackageUnavailableException extends RuntimeException
{
    public function __construct(public readonly Package $package)
    {
        parent::__construct("Package [{$package->slug}] is no longer available.");
    }
}
