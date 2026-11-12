<?php

namespace Tests\Unit;

use App\Models\InvoiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_invoice_type(): void
    {
        $type = InvoiceType::factory()->create();

        $this->assertDatabaseHas('invoice_types', ['id' => $type->id]);
    }

    public function test_description_is_translatable(): void
    {
        $type = InvoiceType::factory()->create();

        $this->assertTrue(in_array('description', $type->getTranslatableAttributes()));
    }
}
