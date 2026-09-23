<?php

namespace App\Filament\Resources\OrderInquiries\Pages;

use App\Bakery\InquiryStatus;
use App\Bakery\Money;
use App\Filament\Resources\OrderInquiries\OrderInquiryResource;
use App\Models\OrderInquiry;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrderInquiry extends ViewRecord
{
    protected static string $resource = OrderInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label(__('bakery.inquiries.actions.reply'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->url(fn (OrderInquiry $record): string => sprintf(
                    'mailto:%s?subject=%s',
                    rawurlencode($record->email),
                    rawurlencode(__('Your order request :reference', ['reference' => $record->reference])),
                )),
            Action::make('update')
                ->label(__('bakery.inquiries.actions.update'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->authorize(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false)
                ->fillForm(fn (OrderInquiry $record): array => [
                    'status' => $record->status->value,
                    'quoted_total_cents' => $record->quoted_total_cents === null ? null : number_format($record->quoted_total_cents / 100, 2, '.', ''),
                    'baker_notes' => $record->baker_notes,
                ])
                ->schema([
                    Select::make('status')
                        ->label(__('bakery.inquiries.fields.status'))
                        ->options(OrderInquiryResource::statusOptions())
                        ->required(),
                    TextInput::make('quoted_total_cents')
                        ->label(__('bakery.inquiries.fields.quote'))
                        ->helperText(__('bakery.inquiries.fields.quote_help'))
                        ->prefix(Money::currency())
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01),
                    Textarea::make('baker_notes')
                        ->label(__('bakery.inquiries.fields.baker_notes'))
                        ->helperText(__('bakery.inquiries.fields.baker_notes_help'))
                        ->rows(4),
                ])
                ->action(function (OrderInquiry $record, array $data): void {
                    $record->status = InquiryStatus::from($data['status']);
                    // Edited as a decimal, stored as integer cents.
                    $record->quoted_total_cents = blank($data['quoted_total_cents'])
                        ? null
                        : (int) round(((float) $data['quoted_total_cents']) * 100);
                    $record->baker_notes = $data['baker_notes'] ?: null;
                    $record->save();

                    Notification::make()->success()->title(__('bakery.inquiries.updated'))->send();
                }),
            DeleteAction::make(),
        ];
    }
}
