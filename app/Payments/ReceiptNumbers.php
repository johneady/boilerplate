<?php

namespace App\Payments;

use App\Models\Payment;
use App\Payments\Enums\GatewayMode;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Sequential, gap-free receipt numbers, one series per payments mode.
 *
 * Accountants expect receipts numbered 1, 2, 3 with nothing missing, so the
 * number is taken from a locked counter row (the `sequences` table) inside the
 * transaction that marks the payment paid: a payment whose transaction rolls
 * back hands its number back, and two payments paid at the same moment queue
 * on the row lock rather than sharing a number. Sandbox and live count
 * separately, so test payments never punch holes in the live series. The
 * payment's UUID stays the internal reference; this is the number a customer
 * and a bookkeeper see.
 */
class ReceiptNumbers
{
    /**
     * Take the next receipt number in the given mode's series.
     *
     * Must run inside a transaction: the lock is what makes the number
     * unique, and it is only held until that transaction ends.
     */
    public function next(GatewayMode $mode): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Receipt numbers are taken inside the transaction that marks the payment paid.');
        }

        $sequence = 'receipt:'.$mode->value;

        $current = $this->lockedValue($sequence);

        // The migration creates both rows. Should one be lost, it is rebuilt
        // from the highest number already issued -- starting again from 0
        // would collide with every receipt on file and fail each payment
        // that followed.
        if ($current === null) {
            DB::table('sequences')->insertOrIgnore([
                'name' => $sequence,
                'value' => (int) Payment::query()->where('mode', $mode)->max('receipt_number'),
            ]);

            $current = (int) $this->lockedValue($sequence);
        }

        DB::table('sequences')
            ->where('name', $sequence)
            ->update(['value' => $current + 1]);

        return $current + 1;
    }

    /**
     * Format a receipt number for display, e.g. "R-000123".
     */
    public static function format(int $number): string
    {
        return config('payments.receipt_prefix').str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Read a receipt number back from how a person might type it.
     *
     * "R-000123", "r-000123", "000123" and "123" are all receipt 123; anything
     * else -- an email address or a description that merely contains digits
     * -- is null, so a search for it does not match a receipt by accident.
     */
    public static function parse(string $input): ?int
    {
        $input = trim($input);
        $prefix = (string) config('payments.receipt_prefix');

        if ($prefix !== '' && str_starts_with(mb_strtolower($input), mb_strtolower($prefix))) {
            $input = substr($input, strlen($prefix));
        }

        return $input !== '' && ctype_digit($input) ? (int) $input : null;
    }

    /**
     * Read a counter under an exclusive row lock, or null if it has no row.
     */
    private function lockedValue(string $sequence): ?int
    {
        $value = DB::table('sequences')
            ->where('name', $sequence)
            ->lockForUpdate()
            ->value('value');

        return $value === null ? null : (int) $value;
    }
}
