<?php

namespace App\Filament\Pages;

use App\Services\UserAccessService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rules\Password;

/**
 * Where a user chooses their own password. Someone who received a temporary password from an administrator is
 * sent here before anything else.
 */
class ChangePassword extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'change-password';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Choose your password');
    }

    public function getSubheading(): ?string
    {
        return auth()->user()?->mustChangePassword()
            ? __('Your password was set by an administrator. Choose your own password to continue.')
            : null;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('password')
                    ->label(__('New password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->same('password_confirmation'),
                TextInput::make('password_confirmation')
                    ->label(__('Confirm the new password'))
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('Save password'))->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = app(UserAccessService::class)->changeOwnPassword(auth()->user(), $data['password']);

        // Keeps this session signed in: the session check compares the password it was started with
        session()->put('password_hash_'.Filament::getAuthGuard(), $user->getAuthPassword());

        Notification::make()->title(__('Password changed'))->success()->send();

        $this->redirect(filament()->getUrl());
    }
}
