<?php

namespace Tests\Feature;

use App\Events\ExternalCoursesReportEvent;
use App\Events\MonthlyReportEvent;
use App\Mail\PendingAttendanceReminder;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Event as ClassEvent;
use App\Models\Period;
use App\Models\Teacher;
use App\Services\Settings;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduledTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string, class-string}>
     */
    public static function settingGatedTasks(): array
    {
        return [
            'external courses report' => ['campusflow:external-courses-report', 'settings.external_courses_report', ExternalCoursesReportEvent::class],
            'monthly report' => ['campusflow:monthly-report', 'settings.monthly_report', MonthlyReportEvent::class],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function taskSettings(): array
    {
        return array_map(fn (array $task): array => [$task[0], $task[1]], self::settingGatedTasks());
    }

    public function test_tasks_are_started_every_fifteen_minutes_with_their_local_time(): void
    {
        $expected = [
            'campusflow:check-periods --at=00:00',
            'campusflow:monthly-report --day=20 --at=00:00',
            'campusflow:external-courses-report --at=03:15',
            'campusflow:build-report --at=05:15',
            'campusflow:attendance-reminders --at=08:15',
        ];

        foreach ($expected as $command) {
            $this->assertSame('*/15 * * * *', $this->scheduledTask($command)?->expression, "{$command} is not scheduled every 15 minutes.");
        }
    }

    #[DataProvider('taskSettings')]
    public function test_task_is_scheduled_when_its_setting_is_off_at_boot(string $command, string $settingKey): void
    {
        $this->assertFalse(config($settingKey));
        $this->assertNotNull($this->scheduledTask($command));
    }

    #[DataProvider('settingGatedTasks')]
    public function test_task_does_nothing_when_its_setting_is_off(string $command, string $settingKey, string $eventClass): void
    {
        Event::fake([$eventClass]);
        app(Settings::class)->set([$settingKey => false]);

        $this->artisan($command)->assertSuccessful();

        Event::assertNotDispatched($eventClass);
    }

    #[DataProvider('settingGatedTasks')]
    public function test_task_runs_when_its_setting_is_on(string $command, string $settingKey, string $eventClass): void
    {
        Event::fake([$eventClass]);
        app(Settings::class)->set([$settingKey => true]);

        $this->artisan($command)->assertSuccessful();

        Event::assertDispatched($eventClass);
    }

    public function test_task_runs_at_its_time_in_the_institution_timezone(): void
    {
        Event::fake([MonthlyReportEvent::class]);
        app(Settings::class)->set(['settings.monthly_report' => true, 'settings.courses_timezone' => 'Europe/Paris']);

        // 08:15 in Paris (UTC+2 in summer time) is 06:15 UTC.
        Carbon::setTestNow(Carbon::parse('2026-10-20 08:15:00', 'UTC'));
        $this->artisan('campusflow:monthly-report --at=08:15')->assertSuccessful();
        Event::assertNotDispatched(MonthlyReportEvent::class);

        Carbon::setTestNow(Carbon::parse('2026-10-20 06:16:30', 'UTC'));
        $this->artisan('campusflow:monthly-report --at=08:15')->assertSuccessful();
        Event::assertDispatchedTimes(MonthlyReportEvent::class, 1);
    }

    public function test_monthly_task_runs_on_its_day_in_the_institution_timezone(): void
    {
        Event::fake([MonthlyReportEvent::class]);
        app(Settings::class)->set(['settings.monthly_report' => true, 'settings.courses_timezone' => 'Europe/Paris']);

        // Midnight on the 20th in Paris is still the 19th in UTC.
        Carbon::setTestNow(Carbon::parse('2026-10-19 22:00:00', 'UTC'));
        $this->artisan('campusflow:monthly-report --day=20 --at=00:00')->assertSuccessful();
        Event::assertDispatchedTimes(MonthlyReportEvent::class, 1);

        Carbon::setTestNow(Carbon::parse('2026-10-20 22:00:00', 'UTC'));
        $this->artisan('campusflow:monthly-report --day=20 --at=00:00')->assertSuccessful();
        Event::assertDispatchedTimes(MonthlyReportEvent::class, 1);
    }

    public function test_task_runs_once_when_clocks_go_back(): void
    {
        Event::fake([ExternalCoursesReportEvent::class]);
        app(Settings::class)->set(['settings.external_courses_report' => true, 'settings.courses_timezone' => 'Europe/Paris']);

        // On 25 October 2026, 02:15 comes twice in Paris: at 00:15 and 01:15 UTC.
        foreach (['2026-10-25 00:15:00', '2026-10-25 01:15:00'] as $utc) {
            Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
            $this->artisan('campusflow:external-courses-report --at=02:15')->assertSuccessful();
        }

        Event::assertDispatchedTimes(ExternalCoursesReportEvent::class, 1);
    }

    public function test_time_must_be_a_quarter_of_an_hour(): void
    {
        $this->artisan('campusflow:monthly-report --at=08:10')
            ->expectsOutputToContain('--at must be a quarter of an hour')
            ->assertFailed();
    }

    public function test_attendance_reminders_follow_their_own_setting(): void
    {
        Mail::fake();
        $teacher = $this->teacherWithPendingAttendance();

        app(Settings::class)->set(['campusflow.send_emails_for_absences' => true, 'campusflow.send_attendance_reminders' => false]);
        $this->artisan('campusflow:attendance-reminders')->assertSuccessful();
        Mail::assertNothingQueued();

        app(Settings::class)->set(['campusflow.send_emails_for_absences' => false, 'campusflow.send_attendance_reminders' => true]);
        $this->artisan('campusflow:attendance-reminders')->assertSuccessful();
        Mail::assertQueued(PendingAttendanceReminder::class, fn (PendingAttendanceReminder $mail): bool => $mail->teacher->is($teacher));
    }

    public function test_attendance_reminders_start_with_the_value_of_the_absence_emails(): void
    {
        $migration = require database_path('migrations/2026_10_24_090000_add_attendance_reminders_setting.php');

        $migration->up();
        $this->assertNull(DB::table('settings')->where('key', 'campusflow.send_attendance_reminders')->value('value'));

        app(Settings::class)->set(['campusflow.send_emails_for_absences' => true]);
        $migration->up();
        app(Settings::class)->reload();
        $this->assertTrue(config('campusflow.send_attendance_reminders'));
    }

    public function test_ended_current_period_is_no_longer_forced(): void
    {
        $ended = Period::factory()->create(['start' => now()->subMonths(3), 'end' => now()->subDay()]);
        Period::factory()->create(['start' => now()->subDay(), 'end' => now()->addMonths(3)]);
        app(Settings::class)->set(['current_period' => $ended->id]);

        $this->artisan('campusflow:check-periods')->assertSuccessful();

        $this->assertNull(app(Settings::class)->get('current_period'));
    }

    private function teacherWithPendingAttendance(): Teacher
    {
        $period = Period::factory()->create(['start' => now()->subMonth(), 'end' => now()->addMonth()]);
        app(Settings::class)->set(['current_period' => $period->id]);
        $teacher = Teacher::factory()->create();
        $course = Course::factory()->create(['period_id' => $period->id, 'teacher_id' => $teacher->id]);

        // Enrolled before the class exists, so that no attendance is added for it.
        Enrollment::factory()->create(['course_id' => $course->id]);
        ClassEvent::factory()->create([
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'start' => now()->subDays(2)->setTime(10, 0),
            'end' => now()->subDays(2)->setTime(12, 0),
        ]);

        return $teacher;
    }

    private function scheduledTask(string $command): ?ScheduledEvent
    {
        return collect($this->app->make(Schedule::class)->events())
            ->first(fn (ScheduledEvent $event): bool => str_contains($event->command ?? '', ' '.$command));
    }
}
