<?php

use App\Auth\Role;
use App\Payments\Enums\DisputeStatus;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\ManualPaymentMethod;
use App\Payments\Enums\PaymentLinkAmountType;
use App\Payments\Enums\PaymentLinkUsage;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Enums\TransactionType;
use App\Payments\Enums\WebhookEventStatus;
use Illuminate\Filesystem\Filesystem;

/**
 * Every dotted translation key referenced under app/.
 *
 * Matched on the literal first argument of __() only: a key built from a
 * variable cannot be checked statically, and the server report's __($label)
 * is deliberately that shape (see .ai/rules/i18n.md).
 *
 * @return list<array{key: string, file: string}>
 */
function dottedTranslationKeys(): array
{
    $keys = [];

    foreach ((new Filesystem)->allFiles(app_path()) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all(
            "/__\(\s*'([a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+)'/i",
            $file->getContents(),
            $matches,
        );

        foreach ($matches[1] as $key) {
            $keys[] = ['key' => $key, 'file' => $file->getRelativePathname()];
        }
    }

    return $keys;
}

test('every dotted translation key resolves to real copy', function () {
    $keys = dottedTranslationKeys();

    // A guard that silently matches nothing would keep passing if the pattern
    // above ever stopped matching the codebase.
    expect($keys)->not->toBeEmpty();

    $unresolved = collect($keys)
        ->filter(fn (array $found): bool => __($found['key']) === $found['key'])
        ->map(fn (array $found): string => "{$found['file']}: {$found['key']}")
        ->unique()
        ->values()
        ->all();

    // A missing dotted key renders the key itself to the user -- "users.fields.role"
    // in a table header -- and nothing else in the suite notices, because the
    // page still returns 200. This is the only thing that catches it.
    expect($unresolved)->toBe([]);
});

test('translated strings are not concatenated', function () {
    $offenders = [];

    foreach ((new Filesystem)->allFiles(app_path()) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        // A fragment containing a WORD glued onto a __() call, either side.
        // Word order differs between languages and a translator never sees the
        // whole sentence, so such a fragment must become a :placeholder.
        //
        // Structural punctuation is allowed through: QueueJobFailed builds a
        // Markdown table row ('| '.__('Detail').' | '), where the pipes are
        // syntax rather than copy and there is no sentence to reorder.
        if (preg_match("/(?:'[^']*\w[^']*'\s*\.\s*__\(|__\([^;]*?\)\s*\.\s*'[^']*\w)/", $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

test('the json catalogue keys every string to itself', function () {
    /** @var array<string, string> $catalogue */
    $catalogue = json_decode((string) file_get_contents(lang_path('en.json')), true);

    // English-as-key means the key IS the fallback copy, so an entry whose
    // value has drifted from its key would render the old wording in English
    // while every other locale looks up the new one.
    $drifted = collect($catalogue)
        ->reject(fn (string $value, string $key): bool => $value === $key)
        ->keys()
        ->all();

    expect($drifted)->toBe([]);
});

test('every enum label translated at display has a catalogue entry', function (string $enum) {
    /** @var array<string, string> $catalogue */
    $catalogue = json_decode((string) file_get_contents(lang_path('en.json')), true);

    // The enums return plain English and the admin panel wraps it in __() at
    // display (.ai/rules/i18n.md). A case added without a catalogue entry still
    // renders in English, so only this notices it never reaches a translator.
    $missing = collect($enum::cases())
        ->map(fn (UnitEnum $case): string => $case->label())
        ->reject(fn (string $label): bool => array_key_exists($label, $catalogue))
        ->values()
        ->all();

    expect($missing)->toBe([]);
})->with([
    DisputeStatus::class,
    Gateway::class,
    GatewayMode::class,
    ManualPaymentMethod::class,
    PaymentLinkAmountType::class,
    PaymentLinkUsage::class,
    PaymentStatus::class,
    RefundStatus::class,
    Role::class,
    SubscriptionStatus::class,
    TransactionSource::class,
    TransactionType::class,
    WebhookEventStatus::class,
]);
