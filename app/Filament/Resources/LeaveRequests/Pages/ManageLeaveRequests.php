<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveRequestService;
use DomainException;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ManageLeaveRequests extends ManageRecords
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Ask for leave'))
                ->modalHeading(__('Ask for leave'))
                ->using(function (array $data, CreateAction $action): Model {
                    $employee = User::query()->findOrFail($data['user_id']);

                    if (! array_key_exists($employee->id, LeaveRequestResource::employeeOptions())) {
                        throw ValidationException::withMessages(['data.user_id' => __('You cannot ask for leave for this person.')]);
                    }

                    try {
                        return app(LeaveRequestService::class)->request(
                            $employee,
                            LeaveType::query()->findOrFail($data['leave_type_id']),
                            Carbon::parse($data['from_date']),
                            Carbon::parse($data['to_date']),
                            $data['reason'] ?? null,
                        );
                    } catch (DomainException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        $action->halt();
                    }
                })
                ->successNotificationTitle(__('Leave requested')),
        ];
    }
}
