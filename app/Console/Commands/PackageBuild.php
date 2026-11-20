<?php

namespace App\Console\Commands;

use InstallerToolkit\PackageBuildCommand;

/**
 * Builds the release zip a shared-hosting buyer uploads (install.php, the
 * application archive with its vendor/ and public/build, and readme.html).
 *
 * The toolkit ships the command but does not register it; an app subclasses
 * it to add its own exclusions. Configuration lives in package/package-config.php.
 */
class PackageBuild extends PackageBuildCommand
{
    /**
     * Directories never shipped to a buyer, beyond the toolkit's own list.
     *
     * `.ai` holds the repository's agent rules. `docker` is only useful with
     * the root Dockerfile and compose files, which the build does not copy
     * (only artisan and the composer files leave the root). `.docker-cache`
     * is the local BuildKit layer cache docker/publish-image.sh writes.
     *
     * @var array<int, string>
     */
    protected array $extraExcludeDirs = [
        '.ai',
        'docker',
        '.docker-cache',
    ];

    /**
     * Storage paths never shipped, beyond the toolkit's own list: the test
     * suite's fake disks (framework/testing, left by Storage::fake()) and
     * Pail's runtime files.
     *
     * The toolkit has no extra-paths hook for storage as it does for
     * directories, so these are appended to its list at run time rather than
     * redeclaring the property, which would drop any path a later toolkit
     * release adds.
     */
    private const EXTRA_EXCLUDED_STORAGE_PATHS = [
        'framework/testing',
        'pail',
    ];

    public function handle(): int
    {
        $this->excludeStoragePaths = array_values(array_unique([
            ...$this->excludeStoragePaths,
            ...self::EXTRA_EXCLUDED_STORAGE_PATHS,
        ]));

        return parent::handle();
    }
}
