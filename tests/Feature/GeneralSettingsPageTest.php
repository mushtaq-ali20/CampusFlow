<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\GeneralSettings;
use App\Models\User;
use App\Services\Settings;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GeneralSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_admin_can_open_the_page(): void
    {
        $this->get(GeneralSettings::getUrl())->assertSuccessful();
    }

    public function test_other_users_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(GeneralSettings::getUrl())->assertForbidden();
    }

    public function test_form_shows_the_current_values(): void
    {
        app(Settings::class)->set([
            'campusflow.company_name' => 'Example School',
            'invoicing.accounting_enabled' => true,
        ]);

        Livewire::test(GeneralSettings::class)
            ->assertFormSet([
                'campusflow.company_name' => 'Example School',
                'campusflow.currency_symbol' => 'Rs',
                'invoicing.accounting_enabled' => true,
                'registration.picture.enabled' => true,
            ]);
    }

    public function test_saving_stores_and_applies_the_settings(): void
    {
        Livewire::test(GeneralSettings::class)
            ->fillForm([
                'campusflow.company_name' => 'Example School',
                'campusflow.currency_symbol' => '$',
                'campusflow.currency_position' => 'before',
                'settings.courses_timezone' => 'America/Bogota',
                'settings.members_enabled' => true,
                'registration.picture.mandatory' => true,
                'invoicing.invoice_numbering' => 'manual',
                'invoicing.invoice_footer' => 'Thank you',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('Settings saved'));

        $this->assertSame('Example School', config('campusflow.company_name'));
        $this->assertSame('$', config('campusflow.currency_symbol'));
        $this->assertSame('before', config('campusflow.currency_position'));
        $this->assertSame('America/Bogota', config('settings.courses_timezone'));
        $this->assertTrue(config('settings.members_enabled'));
        $this->assertTrue(config('registration.picture.mandatory'));
        $this->assertSame('manual', config('invoicing.invoice_numbering'));
        $this->assertSame('Thank you', config('invoicing.invoice_footer'));
        $this->assertDatabaseHas('settings', ['key' => 'campusflow.company_name']);
    }

    public function test_the_application_is_english_only_so_there_is_no_language_setting(): void
    {
        Livewire::test(GeneralSettings::class)
            ->assertFormFieldDoesNotExist('campusflow.locale')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('en', config('campusflow.locale'));
    }

    public function test_certificate_settings_are_saved(): void
    {
        Livewire::test(GeneralSettings::class)
            ->fillForm([
                'certificates.enabled' => true,
                'certificates.title' => 'Diploma',
                'certificates.body' => '{student} passed {level}.',
                'certificates.city' => 'Lyon',
                'certificates.signer_name' => 'John Smith',
                'certificates.signer_function' => 'Director',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(config('certificates.enabled'));
        $this->assertSame('Diploma', config('certificates.title'));
        $this->assertSame('{student} passed {level}.', config('certificates.body'));
        $this->assertSame('Lyon', config('certificates.city'));
        $this->assertSame('John Smith', config('certificates.signer_name'));
        $this->assertSame('Director', config('certificates.signer_function'));
    }

    public function test_certificate_images_are_stored_on_the_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        Livewire::test(GeneralSettings::class)
            ->fillForm([
                'certificates.signature' => UploadedFile::fake()->image('signature.png'),
                'certificates.background' => UploadedFile::fake()->image('background.jpg'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        foreach (['certificates.signature', 'certificates.background'] as $key) {
            $this->assertNotNull(config($key));
            Storage::disk('local')->assertExists(config($key));
            Storage::disk('public')->assertMissing(config($key));
        }
    }

    public function test_empty_fields_are_saved_as_null(): void
    {
        Livewire::test(GeneralSettings::class)
            ->fillForm(['settings.manager_email' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(config('settings.manager_email'));
    }

    public function test_institution_name_is_required(): void
    {
        Livewire::test(GeneralSettings::class)
            ->fillForm(['campusflow.company_name' => ''])
            ->call('save')
            ->assertHasFormErrors(['campusflow.company_name' => 'required']);
    }

    public function test_logo_is_stored_on_the_public_disk(): void
    {
        Storage::fake('public');

        Livewire::test(GeneralSettings::class)
            ->fillForm(['campusflow.logo' => UploadedFile::fake()->image('logo.png')])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotNull(config('campusflow.logo'));
        Storage::disk('public')->assertExists(config('campusflow.logo'));
    }

    public function test_panel_shows_the_institution_logo(): void
    {
        app(Settings::class)->set(['campusflow.logo' => 'settings/logo.png']);

        $this->get(GeneralSettings::getUrl())
            ->assertSee(Storage::disk('public')->url('settings/logo.png'));
    }

    public function test_student_pages_show_the_institution_name(): void
    {
        app(Settings::class)->set(['campusflow.company_name' => 'Example School']);
        auth()->logout();

        $this->get(route('register'))
            ->assertSuccessful()
            ->assertSee('Example School');
    }

    public function test_timezone_options_are_searchable_by_pakistani_cities(): void
    {
        $options = GeneralSettings::timezoneOptions();

        $this->assertSame('(UTC+05:00) Asia/Karachi (Pakistan: Karachi, Islamabad, Lahore)', $options['Asia/Karachi']);
        $this->assertSame('(UTC+00:00) UTC', $options['UTC']);
        $this->assertSame(DateTimeZone::listIdentifiers(), array_keys($options));
    }

    public function test_regional_defaults_are_for_pakistan(): void
    {
        $this->assertSame('Asia/Karachi', config('settings.courses_timezone'));
        $this->assertSame('PKR', config('campusflow.currency_code'));
        $this->assertSame('Rs', config('campusflow.currency_symbol'));
        $this->assertSame('before', config('campusflow.currency_position'));
    }
}
