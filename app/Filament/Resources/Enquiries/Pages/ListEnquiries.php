<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Models\Enquiry;
use App\Voltiva\DrivingNeed;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListEnquiries extends ListRecords
{
    protected static string $resource = EnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('voltiva.enquiries.actions.export'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    /**
     * Download the enquiries matching the table's current filters and search
     * as CSV -- the hand-off to an external CRM or a spreadsheet. Streamed
     * straight from the query rather than queued, which is plenty for a
     * dealership's volume and needs no export tables or notifications.
     */
    protected function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery();

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['Received', 'Name', 'Email', 'Phone', 'Car', 'Location', 'Driving needs', 'Finance', 'Registration', 'Source', 'Status', 'Emails sent', 'Message', 'Staff notes']);

            $query?->chunk(200, function ($enquiries) use ($out): void {
                /** @var Enquiry $enquiry */
                foreach ($enquiries as $enquiry) {
                    fputcsv($out, array_map(static::safeCell(...), [
                        $enquiry->created_at?->toDateTimeString() ?? '',
                        $enquiry->name,
                        $enquiry->email,
                        (string) $enquiry->phone,
                        (string) $enquiry->vehicle_name,
                        (string) $enquiry->location,
                        implode(', ', array_map(fn (DrivingNeed $need): string => $need->label(), $enquiry->drivingNeeds())),
                        $enquiry->finance_interest ? 'Yes' : 'No',
                        $enquiry->registration_interest ? 'Yes' : 'No',
                        $enquiry->sourceLabel(),
                        $enquiry->status->label(),
                        (string) $enquiry->follow_up_step,
                        (string) $enquiry->message,
                        (string) $enquiry->staff_notes,
                    ]));
                }
            });

            fclose($out);
        }, 'enquiries-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Neutralise spreadsheet formula injection: a customer-typed value that
     * starts with =, +, - or @ would otherwise run as a formula when the
     * sales team opens the file in Excel.
     *
     * Protected, not public: every public method on a Livewire component can
     * be called from the browser.
     */
    protected static function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
