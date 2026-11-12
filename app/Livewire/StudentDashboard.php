<?php

namespace App\Livewire;

use App\Models\Enrollment;
use Illuminate\View\View;
use Livewire\Component;

class StudentDashboard extends Component
{
    public function render(): View
    {
        $student = auth()->user()->student;

        $enrollments = $student
            ? Enrollment::query()
                ->where('student_id', $student->id)
                ->whereDoesntHave('childrenEnrollments')
                ->whereIn('status_id', ['1', '2'])
                ->with(['course.period', 'result.result_name', 'enrollmentStatus'])
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('livewire.student-dashboard', [
            'enrollments' => $enrollments,
        ])->layout('components.layouts.student', ['title' => __('Dashboard')]);
    }
}
