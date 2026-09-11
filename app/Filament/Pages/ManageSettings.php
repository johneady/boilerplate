<?php

namespace App\Filament\Pages;

use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsTab;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
        };
    }

    protected function settings(): Settings
    {
        return app(Settings::class);
    }
}
