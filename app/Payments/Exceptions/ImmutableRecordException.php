<?php

namespace App\Payments\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Thrown when code tries to rewrite or delete a financial record.
 *
 * A LogicException because reaching it is always a bug in the caller, never a
 * condition to recover from: amounts, currencies and gateway ids are fixed
 * once written, and a payment is never deleted. A correction is a new ledger
 * entry, not an edit.
 */
class ImmutableRecordException extends LogicException
{
    public static function attributeChanged(Model $model, string $attribute): self
    {
        return new self(sprintf('[%s] is write-once on %s #%s.', $attribute, class_basename($model), $model->getKey()));
    }

    public static function deleted(Model $model): self
    {
        return new self(sprintf('%s #%s is a financial record and cannot be deleted.', class_basename($model), $model->getKey()));
    }
}
