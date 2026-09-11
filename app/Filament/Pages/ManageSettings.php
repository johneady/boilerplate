<?php

namespace App\Filament\Pages;

use App\Mail\TestEmail;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsTab;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Edit the application's settings.
 *
 * The form is built from App\Settings\SettingKey rather than a hand-written
 * field list, so adding a setting is a single enum case -- no migration, and
 * no edit here -- as long as its type has a field mapped in formComponent().
 * Settings are grouped onto the page's tabs by SettingKey::tab().
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static ?string $slug = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static ?int $navigationSort = 90;

    /**
     * The form's state, keyed by SettingKey value.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->settings()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Tabs::make('settings')
                        ->tabs(array_map(
                            fn (SettingsTab $tab): Tab => $this->tabComponent($tab),
                            SettingsTab::cases(),
                        )),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save changes')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->settings()->setMany($this->form->getState());

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }

    /**
     * Build the tab a group of settings is edited on.
     *
     * Tabs are rendered per SettingsTab case, each collecting the keys that
     * claim it, so a setting lands on a tab by its enum declaration alone.
     */
    protected function tabComponent(SettingsTab $settingsTab): Tab
    {
        $keys = array_filter(
            SettingKey::cases(),
            fn (SettingKey $key): bool => $key->tab() === $settingsTab,
        );

        return Tab::make($settingsTab->label())
            ->icon($settingsTab->icon())
            ->schema(array_map(
                fn (SettingKey $key) => $this->formComponent($key),
                $keys,
            ));
    }

    /**
     * Build the field for a single setting.
     *
     * Every key's default is declared on the enum, so a setting that has never
     * been saved still renders with the value the application actually uses.
     */
    protected function formComponent(SettingKey $key): Field
    {
        return match ($key) {
            SettingKey::BusinessName => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->required()
                ->maxLength(255),
            SettingKey::BusinessAddress => Textarea::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->maxLength(1024)
                ->rows(3),
            SettingKey::BusinessPhone => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->maxLength(255),
            SettingKey::BusinessEmail => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->email()
                ->maxLength(255),
            SettingKey::AllowRegistration => Toggle::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default()),
            SettingKey::MailMailer => Select::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->options([
                    'log' => 'Log',
                    'smtp' => 'SMTP',
                ])
                ->required()
                ->selectablePlaceholder(false)
                ->live(),
            SettingKey::MailHost => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->placeholder('smtp.example.com')
                ->maxLength(255)
                ->visible($this->whenMailerIsSmtp()),
            SettingKey::MailPort => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->placeholder('587')
                ->numeric()
                ->minValue(1)
                ->maxValue(65535)
                ->visible($this->whenMailerIsSmtp()),
            SettingKey::MailUsername => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->maxLength(255)
                ->visible($this->whenMailerIsSmtp()),
            SettingKey::MailPassword => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->password()
                ->revealable()
                ->maxLength(255)
                ->visible($this->whenMailerIsSmtp()),
            SettingKey::MailEncryption => Select::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->options([
                    '' => 'Default (TLS)',
                    'tls' => 'TLS',
                    'ssl' => 'SSL',
                    'none' => 'None',
                ])
                ->selectablePlaceholder(false)
                ->visible($this->whenMailerIsSmtp()),
            SettingKey::MailFromAddress => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->placeholder(fn (): string => (string) config('mail.from.address'))
                ->email()
                ->maxLength(255),
            SettingKey::MailFromName => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->placeholder(fn (): string => $this->settings()->businessName())
                ->maxLength(255),
        };
    }

    /**
     * Show a field only while the SMTP connection is being configured.
     *
     * Live off the mailer select, so choosing SMTP reveals the connection
     * fields immediately without saving. The from-address fields stay visible
     * for both mailers -- the from address is applied whichever one sends.
     */
    protected function whenMailerIsSmtp(): Closure
    {
        return fn (Get $get): bool => $get('mail_mailer') === 'smtp';
    }

    /**
     * The page-level actions shown beside the title.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testEmail')
                ->label('Send test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->modalDescription('Sends a short message through the saved mail settings, so delivery can be confirmed end to end.')
                ->form([
                    TextInput::make('recipient')
                        ->label('Deliver to')
                        ->email()
                        ->required()
                        ->default(function (): string {
                            $user = auth()->user();

                            return $user instanceof User ? $user->email : '';
                        }),
                ])
                ->action(function (array $data): void {
                    $this->sendTestEmail($data['recipient']);
                }),
        ];
    }

    /**
     * Deliver the test message and report what actually happened.
     */
    protected function sendTestEmail(string $recipient): void
    {
        try {
            Mail::to($recipient)->send(new TestEmail);

            // mail.default now reflects the settings applied when the mailer
            // resolved, including the incomplete-SMTP fallback to log.
            if (config('mail.default') === 'log') {
                Notification::make()
                    ->warning()
                    ->title('Test email written to the log')
                    ->body('The mailer is set to log, so the message was written to the application log rather than delivered.')
                    ->send();

                return;
            }

            Notification::make()
                ->success()
                ->title('Test email sent')
                ->body("Delivered to {$recipient}. Check the inbox, and the spam folder if it does not arrive.")
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title('Test email failed')
                ->body($exception->getMessage())
                ->send();
        }
    }

    protected function settings(): Settings
    {
        return app(Settings::class);
    }
}
