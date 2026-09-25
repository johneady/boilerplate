{{--
    A payment's receipt as dompdf lays it out (App\Payments\ReceiptPdf).

    Plain HTML with inline CSS and tables, deliberately: dompdf implements
    CSS 2.1 and a little more, with no flexbox, grid or Tailwind, and it never
    fetches remote assets -- the logo arrives as a data URI. DejaVu Sans is
    bundled with dompdf and covers the currency symbols and dashes the
    built-in PDF fonts do not.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <title>{{ __('Receipt :number', ['number' => $payment->receiptNumber()]) }}</title>
        <style>
            @page {
                margin: 48px 56px;
            }
            body {
                font-family: 'DejaVu Sans', sans-serif;
                font-size: 11px;
                color: #18181b;
                line-height: 1.45;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            td {
                vertical-align: top;
                padding: 0;
            }
            .muted {
                color: #71717a;
            }
            .small {
                font-size: 9px;
            }
            .right {
                text-align: right;
            }
            .mono {
                font-family: 'DejaVu Sans Mono', monospace;
            }
            .logo {
                width: 56px;
                height: 56px;
                margin-bottom: 8px;
            }
            .business-name {
                font-size: 16px;
                font-weight: bold;
            }
            .title {
                font-size: 22px;
                font-weight: bold;
                letter-spacing: 1px;
            }
            .section {
                margin-top: 28px;
            }
            .label {
                font-size: 9px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #71717a;
                padding-bottom: 2px;
            }
            .lines td {
                padding: 7px 0;
                border-bottom: 1px solid #e4e4e7;
            }
            .lines .head td {
                font-size: 9px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #71717a;
                border-bottom: 1px solid #a1a1aa;
            }
            .totals {
                margin-top: 12px;
                width: 45%;
                margin-left: 55%;
            }
            .totals td {
                padding: 3px 0;
            }
            .totals .grand td {
                padding-top: 8px;
                border-top: 1px solid #a1a1aa;
                font-size: 13px;
                font-weight: bold;
            }
            .stamp {
                display: inline-block;
                padding: 3px 8px;
                border: 1px solid #b45309;
                color: #b45309;
                font-size: 9px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .stamp.test {
                border-color: #b91c1c;
                color: #b91c1c;
            }
            .footer {
                margin-top: 40px;
                padding-top: 10px;
                border-top: 1px solid #e4e4e7;
            }
        </style>
    </head>
    <body>
        <table>
            <tr>
                <td>
                    @if ($logo !== null)
                        <img src="{{ $logo }}" alt="" class="logo" />
                    @endif

                    <div class="business-name">{{ $businessName }}</div>

                    @if ($businessAddress !== '')
                        <div class="muted">{!! nl2br(e($businessAddress)) !!}</div>
                    @endif

                    @if ($businessPhone !== '')
                        <div class="muted">{{ $businessPhone }}</div>
                    @endif

                    @if ($businessEmail !== '')
                        <div class="muted">{{ $businessEmail }}</div>
                    @endif
                </td>
                <td class="right">
                    <div class="title">{{ mb_strtoupper(__('Receipt')) }}</div>
                    <div class="mono" style="font-size: 13px">{{ $payment->receiptNumber() }}</div>
                    <div class="muted">{{ __('Paid :date', ['date' => $paidOn]) }}</div>

                    @if ($isTest)
                        <div style="margin-top: 8px"><span class="stamp test">{{ __('Test payment — not a valid receipt') }}</span></div>
                    @endif

                    @if ($payment->status === \App\Payments\Enums\PaymentStatus::Refunded)
                        <div style="margin-top: 8px"><span class="stamp">{{ __('Refunded') }}</span></div>
                    @elseif ($payment->status === \App\Payments\Enums\PaymentStatus::PartiallyRefunded)
                        <div style="margin-top: 8px"><span class="stamp">{{ __('Partially refunded') }}</span></div>
                    @endif
                </td>
            </tr>
        </table>

        <table class="section">
            <tr>
                <td style="width: 55%">
                    <div class="label">{{ __('Received from') }}</div>
                    <div>{{ $payment->customer_name }}</div>
                    <div class="muted">{{ $payment->customer_email }}</div>
                </td>
                <td>
                    <div class="label">{{ __('Payment method') }}</div>
                    <div>{{ $method }}</div>

                    @if ($payment->manual_reference !== null)
                        <div class="muted">{{ $payment->manual_reference }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <table class="section lines">
            <tr class="head">
                <td>{{ __('Description') }}</td>
                <td class="right">{{ __('Amount') }}</td>
            </tr>
            <tr>
                <td>{{ $payment->description }}</td>
                <td class="right">{{ $payment->subtotalMoney()->format() }}</td>
            </tr>
        </table>

        <table class="totals">
            @if ($payment->tax_lines !== [])
                <tr>
                    <td>{{ __('Subtotal') }}</td>
                    <td class="right">{{ $payment->subtotalMoney()->format() }}</td>
                </tr>

                @foreach ($payment->taxLines() as $line)
                    <tr>
                        <td>
                            {{ $line->label() }}
                            @if ($line->registrationNumber !== null)
                                <div class="small muted">{{ __('Registration no. :number', ['number' => $line->registrationNumber]) }}</div>
                            @endif
                        </td>
                        <td class="right">{{ $line->amount->format() }}</td>
                    </tr>
                @endforeach
            @endif

            {{-- A hold captured for less than it authorized: the lines above add
                 up to the authorized total, so the part released is shown to make
                 them add up to what was paid. --}}
            @if (! $payment->paidMoney()->equals($payment->total()))
                <tr>
                    <td>{{ __('Total') }}</td>
                    <td class="right">{{ $payment->total()->format() }}</td>
                </tr>
                <tr class="muted">
                    <td>{{ __('Released, not charged') }}</td>
                    <td class="right">-{{ $payment->total()->subtract($payment->paidMoney())->format() }}</td>
                </tr>
            @endif

            <tr class="grand">
                <td>{{ __('Total paid') }}</td>
                <td class="right">{{ $payment->paidMoney()->format() }}</td>
            </tr>

            @foreach ($refunds as $refund)
                <tr class="muted">
                    <td>{{ __('Refunded :date', ['date' => $settings->formatDate($refund->created_at)]) }}</td>
                    <td class="right">-{{ $refund->money()->format() }}</td>
                </tr>
            @endforeach
        </table>

        <div class="footer small muted">
            {{ __('Reference: :reference', ['reference' => $payment->uuid]) }}
            <br />
            {{ __('Thank you for your business.') }}
        </div>
    </body>
</html>
