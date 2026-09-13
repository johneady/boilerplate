<?php

use App\Audit\AuditEvent;

test('every event has a label', function (AuditEvent $event) {
    expect($event->label())->toBeString()->not->toBeEmpty();
})->with(AuditEvent::cases());

test('every event has a colour', function (AuditEvent $event) {
    expect($event->color())->toBeString()->not->toBeEmpty();
})->with(AuditEvent::cases());

test('only the model events report themselves as model events', function () {
    $modelEvents = array_filter(
        AuditEvent::cases(),
        fn (AuditEvent $event): bool => $event->isModelEvent(),
    );

    expect($modelEvents)->toEqualCanonicalizing([
        AuditEvent::Created,
        AuditEvent::Updated,
        AuditEvent::Deleted,
    ]);
});

/*
 * Guards .ai/rules/i18n.md: this enum is reached from a unit test, which has no
 * container, so a __() call in label() would die with "Target class
 * [translator] does not exist" rather than failing visibly where it was added.
 */
test('the enum does not translate its own copy', function () {
    // A literal path rather than app_path(): a unit test has no container, so
    // the helper itself is unavailable here -- which is the same absence that
    // makes __() fatal in the enum.
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Audit/AuditEvent.php');

    // Comments are stripped first: the class docblock explains why __() must
    // not appear here, and a naive search would match that explanation.
    $code = implode(' ', array_map(
        fn (array|string $token): string => is_array($token) ? $token[1] : $token,
        array_filter(
            token_get_all($source),
            fn (array|string $token): bool => ! is_array($token)
                || ! in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true),
        ),
    ));

    expect($code)->not->toContain('__(');
});
