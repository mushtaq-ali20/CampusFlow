<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The kinds of class assessment recorded besides the exams.
 */
enum AssessmentType: string implements HasLabel
{
    case ClassTest = 'class_test';
    case Quiz = 'quiz';
    case Assignment = 'assignment';
    case Project = 'project';
    case Observation = 'observation';

    public function getLabel(): string
    {
        return match ($this) {
            self::ClassTest => __('Class test'),
            self::Quiz => __('Quiz'),
            self::Assignment => __('Assignment'),
            self::Project => __('Project'),
            self::Observation => __('Observation'),
        };
    }
}
