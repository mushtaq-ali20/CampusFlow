<?php

namespace App\Filament\Resources\EvaluationCriteria\Pages;

use App\Filament\Resources\EvaluationCriteria\EvaluationCriterionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEvaluationCriteria extends ManageRecords
{
    protected static string $resource = EvaluationCriterionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
