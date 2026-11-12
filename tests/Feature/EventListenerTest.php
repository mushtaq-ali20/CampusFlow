<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceType;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \DB::table('enrollment_status_types')->insert([
            ['id' => 1, 'name' => json_encode(['fr' => 'Pending'])],
            ['id' => 2, 'name' => json_encode(['fr' => 'Paid'])],
            ['id' => 3, 'name' => json_encode(['fr' => 'Cancelled'])],
        ]);

        \DB::table('attendance_types')->insert([
            ['id' => 1, 'name' => json_encode(['fr' => 'Present'])],
            ['id' => 2, 'name' => json_encode(['fr' => 'Late'])],
            ['id' => 3, 'name' => json_encode(['fr' => 'Absent - Justified'])],
            ['id' => 4, 'name' => json_encode(['fr' => 'Absent - Unjustified'])],
        ]);
    }

    public function test_student_deleting_cascades_to_attendance_and_enrollments(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status_id' => 1,
        ]);

        $event = Event::factory()->create([
            'course_id' => $course->id,
            'start' => now()->addDays(10)->toDateTimeString(),
            'end' => now()->addDays(10)->addHour()->toDateTimeString(),
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'event_id' => $event->id,
            'attendance_type_id' => 1,
        ]);

        $student->delete();

        $this->assertDatabaseMissing('attendances', ['student_id' => $student->id]);
        $this->assertDatabaseMissing('enrollments', ['student_id' => $student->id]);
    }

    public function test_invoice_deleting_removes_invoice_details(): void
    {
        $invoiceType = InvoiceType::factory()->create();
        $invoice = Invoice::factory()->create(['invoice_type_id' => $invoiceType->id]);

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'product_name' => 'Test',
            'product_code' => 'T',
            'product_id' => 1,
            'product_type' => 'App\\Models\\Fee',
            'price' => 100,
        ]);

        $invoiceId = $invoice->id;
        $invoice->delete();

        $this->assertDatabaseMissing('invoice_details', ['invoice_id' => $invoiceId]);
    }

    public function test_enrollment_deleting_removes_related_invoice_details(): void
    {
        $enrollment = Enrollment::factory()->create();
        $invoiceType = InvoiceType::factory()->create();
        $invoice = Invoice::factory()->create(['invoice_type_id' => $invoiceType->id]);

        $invoiceDetail = InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'product_name' => 'Enrollment',
            'product_code' => 'E',
            'product_id' => $enrollment->id,
            'product_type' => Enrollment::class,
            'price' => 100,
        ]);

        $enrollment->delete();

        // InvoiceDetail is soft-deleted by the listener, then the parent invoice
        // (now empty) is hard-deleted, which cascade-deletes the detail row entirely.
        $this->assertDatabaseMissing('invoice_details', ['id' => $invoiceDetail->id]);
    }

    public function test_user_deleting_cascades(): void
    {
        $user = User::factory()->create();
        $teacher = Teacher::factory()->create(['id' => $user->id]);
        $student = Student::factory()->create(['id' => $user->id]);

        $user->delete();

        // Teacher should be soft-deleted
        $this->assertSoftDeleted('teachers', ['id' => $user->id]);

        // Student should be deleted (no soft deletes on Student)
        $this->assertDatabaseMissing('students', ['id' => $user->id]);
    }
}
