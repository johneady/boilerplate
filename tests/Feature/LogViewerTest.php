<?php

use AchyutN\FilamentLogViewer\FilamentLogViewer;
use AchyutN\FilamentLogViewer\LogTable;
use App\Auth\Permission;
use App\Models\User;

/**
 * Serve the log viewer from an empty storage directory holding only the given
 * log contents, and return the fixture's absolute path.
 *
 * The log provider scans whatever storage_path('logs') resolves to at request
 * time, so without this the page would list this machine's real development
 * logs -- making which row lands on page 1, and so the assertion, dependent on
 * the machine the suite runs on. Redirecting the storage path after boot is
 * enough because config-loaded paths (compiled views, channel handlers) were
 * all resolved before the switch and tests run on array drivers anyway.
 */
function isolateLogViewerWithFixture(string $contents): string
{
    $storage = sys_get_temp_dir().'/log-viewer-test-'.str()->random(10);
    mkdir($storage.'/logs', recursive: true);

    $path = $storage.'/logs/laravel-2026-09-12.log';
    file_put_contents($path, $contents);

    app()->useStoragePath($storage);

    return $path;
}

afterEach(function () {
    // Remove only directories this file created. The glob must stay narrower
    // than "*.log": storage/logs also holds the machine's real logs, which a
    // wider pattern would delete.
    foreach (glob(sys_get_temp_dir().'/log-viewer-test-*') ?: [] as $dir) {
        foreach (glob($dir.'/logs/*.log') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir.'/logs');
        @rmdir($dir);
    }
});

test('admins may open the log viewer', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/logs')
        ->assertSuccessful();
});

test('users without panel access are forbidden from the log viewer', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/logs')
        ->assertForbidden();
});

test('the log viewer page is authorized by the logs.view permission', function () {
    // Panel access alone must not be enough: the closure registered on the
    // plugin decides whether the page answers, so a future role granted
    // admin-panel.access without logs.view is still denied.
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();

    expect($user->hasPermission(Permission::ViewLogs))->toBeFalse()
        ->and($admin->hasPermission(Permission::ViewLogs))->toBeTrue();

    $this->actingAs($user);
    expect(LogTable::canAccess())->toBeFalse();

    $this->actingAs($admin);
    expect(LogTable::canAccess())->toBeTrue();
});

test('the log viewer lists entries from the application logs', function () {
    isolateLogViewerWithFixture(
        "[2026-09-12 14:00:00] testing.ERROR: Synthesized fixture boom [[\"k\",\"v\"]]\n".
        "#0 /app/Boilerplate.php(1): boom()\n"
    );

    $html = $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/logs')
        ->assertSuccessful()
        ->getContent();

    expect($html)->toContain('Synthesized fixture boom');
});

test('the log viewer plugin is registered on the admin panel', function () {
    $plugin = Filament\Facades\Filament::getPanel('admin')->getPlugin('filament-log-viewer');

    expect($plugin)->toBeInstanceOf(FilamentLogViewer::class)
        ->and($plugin->getPageClass())->toBe(LogTable::class);
});
