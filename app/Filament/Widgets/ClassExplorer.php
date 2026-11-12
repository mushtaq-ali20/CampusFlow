<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\SchoolClass;
use App\Services\SchoolOverviewService;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * The classes from Pre Nursery to Class 10 as buttons; the chosen class shows its sections with their students
 * drawn as avatars, coloured by the attendance of the last day it was marked.
 */
class ClassExplorer extends Widget
{
    public const STUDENTS_PER_SECTION = 18;

    public const SKIN_TONES = ['#f1c27d', '#e0ac69', '#c68642', '#a96b3c', '#ffdbac'];

    public const HAIR_COLOURS = ['#2d1b0e', '#1f1f1f', '#3b2314', '#5a3825'];

    protected static bool $isLazy = false;

    protected static ?int $sort = -1;

    protected string $view = 'filament.widgets.class-explorer';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    public ?int $classId = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('school.view') ?? false;
    }

    public function mount(): void
    {
        $this->classId = session('dashboard.class_id') ?? SchoolClass::query()->ordered()->value('id');
    }

    public function selectClass(int $classId): void
    {
        if (SchoolClass::query()->whereKey($classId)->exists()) {
            $this->classId = $classId;
            session(['dashboard.class_id' => $classId]);
        }
    }

    /**
     * The drawing of a student: skin and hair are picked from the admission so that they stay the same.
     *
     * @return array{skin: string, hair: string, girl: bool}
     */
    public static function avatar(int $seed, ?int $genderId): array
    {
        return [
            'skin' => self::SKIN_TONES[$seed % count(self::SKIN_TONES)],
            'hair' => self::HAIR_COLOURS[intdiv($seed, 3) % count(self::HAIR_COLOURS)],
            'girl' => $genderId === 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        /** @var Collection<int, SchoolClass> $classes */
        $classes = SchoolClass::query()->ordered()->get();
        $class = $classes->firstWhere('id', $this->classId) ?? $classes->first();

        return [
            'classes' => $classes,
            'class' => $class,
            'snapshot' => $class === null ? null : app(SchoolOverviewService::class)->classSnapshot($user, $class),
            'canOpenAdmissions' => AdmissionResource::canAccess(),
            'canOpenSections' => SectionResource::canAccess(),
            'perSection' => self::STUDENTS_PER_SECTION,
        ];
    }
}
