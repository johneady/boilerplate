<?php

namespace App\Settings;

/**
 * The outcome of a single production configuration check.
 *
 * Readonly because a result describes the configuration at the moment it was
 * read; a caller that wants a fresh view runs the checks again rather than
 * mutating one.
 */
class DiagnosticResult
{
    public function __construct(
        public readonly string $name,
        public readonly bool $passed,
        public readonly DiagnosticSeverity $severity,
        public readonly string $detail,
    ) {}
}
