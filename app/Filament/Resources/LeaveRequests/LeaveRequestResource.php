<?php

namespace App\Filament\Resources\LeaveRequests;

use App\Enums\RequestStatus;
use App\Filament\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveRequestService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The leave requests of the employees. Every employee asks for their own leave and sees their own requests; staff
 * who manage the employees see the requests of the branch, ask for an employee and approve or reject.
 */
class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?int $navigationSort = 525;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('employees.view') || EmployeeProfile::query()->where('user_id', $user->id)->exists());
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = match (true) {
            $action instanceof BackedEnum => $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        return in_array($action, ['viewAny', 'view', 'create'], true) && static::canAccess()
            ? parent::getAuthorizationResponse($action, $record)
            : Response::deny();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organization');
    }

    public static function getModelLabel(): string
    {
        return __('Leave request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Leave requests');
    }

    protected static function managesEmployees(): bool
    {
        return auth()->user()?->can('employees.manage') ?? false;
    }

    /**
     * Approving leave is a right of its own, given on the Roles screen (the Principal has it).
     */
    protected static function approvesLeave(): bool
    {
        return auth()->user()?->can('leaves.approve') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! (auth()->user()?->can('employees.view') ?? false)) {
            return null;
        }

        $count = static::getEloquentQuery()->where('status', RequestStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Staff who see the employees see the requests of the branch; everybody else sees their own.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when(
                $user?->can('employees.view'),
                fn (Builder $query) => $query->visibleTo($user),
                fn (Builder $query) => $query->where('user_id', $user?->id),
            );
    }

    /**
     * The employees a request can be made for: any employee for staff who manage the employees, else the person.
     *
     * @return array<int, string>
     */
    public static function employeeOptions(): array
    {
        $user = auth()->user();

        return User::query()
            ->whereHas('employeeProfile', fn (Builder $profiles) => $profiles->visibleTo($user))
            ->when(! static::managesEmployees(), fn (Builder $query) => $query->whereKey($user?->id))
            ->orderBy('firstname')
            ->get()
            ->pluck('name', 'id')
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label(__('Employee'))
                    ->options(fn (): array => static::employeeOptions())
                    ->default(fn (): ?int => static::managesEmployees() ? null : auth()->id())
                    ->searchable()
                    ->required()
                    ->live(),
                Select::make('leave_type_id')
                    ->label(__('Leave Type'))
                    ->options(fn (): array => LeaveType::query()->get()->pluck('name', 'id')->all())
                    ->required()
                    ->live()
                    ->helperText(function (Get $get): ?string {
                        $employee = User::query()->find($get('user_id'));
                        $type = LeaveType::query()->find($get('leave_type_id'));

                        if ($employee === null || $type === null) {
                            return null;
                        }

                        $balance = app(LeaveRequestService::class)->balance($employee, $type);
                        $note = $balance['paid'] ? '' : ' '.__('This leave is unpaid: the days are deducted from the salary.');

                        return ($balance['remaining'] === null
                            ? __(':used days taken this year, no limit.', ['used' => $balance['used']])
                            : __(':remaining of :allowed days left this year.', ['remaining' => $balance['remaining'], 'allowed' => $balance['allowed']])).$note;
                    }),
                DatePicker::make('from_date')->label(__('From'))->required()->default(now()),
                DatePicker::make('to_date')->label(__('Until'))->required()->default(now())->afterOrEqual('from_date'),
                TextInput::make('reason')->label(__('Reason'))->maxLength(255)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'leaveType', 'decidedBy']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('Employee'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('user', fn (Builder $users) => $users->where('firstname', 'like', "%{$search}%")->orWhere('lastname', 'like', "%{$search}%"))),
                TextColumn::make('leaveType.name')->label(__('Leave Type')),
                TextColumn::make('from_date')
                    ->label(__('Dates'))
                    ->formatStateUsing(fn (LeaveRequest $record): string => $record->from_date->equalTo($record->to_date)
                        ? $record->from_date->format('d M Y')
                        : $record->from_date->format('d M').' – '.$record->to_date->format('d M Y'))
                    ->description(fn (LeaveRequest $record): string => trans_choice('{1} :count day|[2,*] :count days', $record->days, ['count' => $record->days]))
                    ->sortable(),
                TextColumn::make('reason')->label(__('Reason'))->wrap()->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->description(fn (LeaveRequest $record): ?string => $record->decidedBy
                        ? __('by :name', ['name' => $record->decidedBy->name]).($record->decision_note ? ': '.$record->decision_note : '')
                        : null),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(RequestStatus::class),
                SelectFilter::make('leave_type_id')->label(__('Leave Type'))->options(fn (): array => LeaveType::query()->get()->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (LeaveRequest $record): bool => $record->status === RequestStatus::Pending && static::approvesLeave())
                    ->requiresConfirmation()
                    ->action(function (LeaveRequest $record): void {
                        try {
                            app(LeaveRequestService::class)->approve($record, auth()->user());
                        } catch (DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('Leave approved'))->success()->send();
                    })
                    ->button(),
                ActionGroup::make([
                    Action::make('reject')
                        ->label(__('Reject'))
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->visible(fn (LeaveRequest $record): bool => $record->status === RequestStatus::Pending && static::approvesLeave())
                        ->schema([TextInput::make('note')->label(__('Reason for rejecting'))->maxLength(255)])
                        ->action(function (LeaveRequest $record, array $data): void {
                            try {
                                app(LeaveRequestService::class)->reject($record, auth()->user(), $data['note'] ?? null);
                            } catch (DomainException $exception) {
                                Notification::make()->title($exception->getMessage())->danger()->send();
                            }
                        }),
                    Action::make('cancel')
                        ->label(__('Withdraw'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->visible(fn (LeaveRequest $record): bool => $record->status === RequestStatus::Pending && ($record->user_id === auth()->id() || static::managesEmployees()))
                        ->requiresConfirmation()
                        ->action(function (LeaveRequest $record): void {
                            try {
                                app(LeaveRequestService::class)->cancel($record);
                            } catch (DomainException $exception) {
                                Notification::make()->title($exception->getMessage())->danger()->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLeaveRequests::route('/'),
        ];
    }
}
