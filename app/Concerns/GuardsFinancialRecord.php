<?php

namespace App\Concerns;

use App\Payments\Exceptions\ImmutableRecordException;

/**
 * Make a model's money-bearing attributes write-once and the record undeletable.
 *
 * Enforced on the model rather than left to the admin policies, which only
 * cover the panel: a queued job, an artisan command or a stray tinker session
 * is stopped by the same rule. Policies are the second belt (see
 * AuthServiceProvider::IMMUTABLE_RECORD_ABILITIES).
 *
 * Two lists, because some attributes are unknown when the row is inserted:
 *
 *   - writeOnceAttributes() are fixed at insert (amount, currency, customer).
 *   - setOnceAttributes() may go from null to a value exactly once and never
 *     change after (a gateway id learned when checkout starts).
 *
 * Only model saves are guarded. The atomic claims (UPDATE ... WHERE paid_at IS
 * NULL) go through the query builder deliberately and touch claim columns only.
 */
trait GuardsFinancialRecord
{
    public static function bootGuardsFinancialRecord(): void
    {
        static::updating(function (self $model): void {
            foreach ($model->writeOnceAttributes() as $attribute) {
                if ($model->isDirty($attribute)) {
                    throw ImmutableRecordException::attributeChanged($model, $attribute);
                }
            }

            foreach ($model->setOnceAttributes() as $attribute) {
                if ($model->isDirty($attribute) && $model->getRawOriginal($attribute) !== null) {
                    throw ImmutableRecordException::attributeChanged($model, $attribute);
                }
            }
        });

        static::deleting(function (self $model): never {
            throw ImmutableRecordException::deleted($model);
        });
    }

    /**
     * Attributes fixed when the record is inserted.
     *
     * @return list<string>
     */
    abstract protected function writeOnceAttributes(): array;

    /**
     * Attributes that may be filled in once after insert, then never changed.
     *
     * @return list<string>
     */
    protected function setOnceAttributes(): array
    {
        return [];
    }
}
