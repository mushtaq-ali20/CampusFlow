<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Pages\ClassAttendanceEntry;
use App\Filament\Pages\ClassAttendanceReports;
use App\Filament\Pages\FeeAging;
use App\Filament\Pages\MarksEntry;
use App\Filament\Pages\PayablesAging;
use App\Filament\Pages\ResultsMasterSheet;
use App\Filament\Pages\SchoolDashboard;
use App\Filament\Pages\StudentLedger;
use App\Filament\Pages\TimetableManager;
use App\Filament\Resources\AdmissionEnquiries\AdmissionEnquiryResource;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Appraisals\AppraisalResource;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Complaints\ComplaintResource;
use App\Filament\Resources\DisciplineRecords\DisciplineRecordResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\EmployeeProfiles\EmployeeProfileResource;
use App\Filament\Resources\FeeAdjustments\FeeAdjustmentResource;
use App\Filament\Resources\FeePayments\FeePaymentResource;
use App\Filament\Resources\FeeRefunds\FeeRefundResource;
use App\Filament\Resources\FeeStructures\FeeStructureResource;
use App\Filament\Resources\FeeVouchers\FeeVoucherResource;
use App\Filament\Resources\JobApplicants\JobApplicantResource;
use App\Filament\Resources\JobOpenings\JobOpeningResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\Leaves\LeaveResource;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Filament\Resources\PtmEvents\PtmEventResource;
use App\Filament\Resources\Sections\SectionResource;
use App\Filament\Resources\Staff\StaffResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\VendorBills\VendorBillResource;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\NeedsTenant;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Multitenancy\Http\Middleware\EnsureValidTenantSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * The screens whose data follows the branch switcher, which say under their title which branch they show.
     *
     * @var list<class-string>
     */
    public const BRANCH_FILTERED_SCREENS = [
        AdmissionResource::class,
        StudentResource::class,
        SectionResource::class,
        TeacherResource::class,
        StaffResource::class,
        LeaveResource::class,
        FeeVoucherResource::class,
        FeePaymentResource::class,
        FeeStructureResource::class,
        TimetableManager::class,
        ClassAttendanceEntry::class,
        MarksEntry::class,
        ClassAttendanceReports::class,
        DisciplineRecordResource::class,
        AdmissionEnquiryResource::class,
        ComplaintResource::class,
        PtmEventResource::class,
        DocumentResource::class,
        AssessmentResource::class,
        ResultsMasterSheet::class,
        FeeAdjustmentResource::class,
        FeeRefundResource::class,
        FeeAging::class,
        StudentLedger::class,
        EmployeeProfileResource::class,
        LeaveRequestResource::class,
        PayrollRunResource::class,
        JobOpeningResource::class,
        JobApplicantResource::class,
        AppraisalResource::class,
        VendorBillResource::class,
        PayablesAging::class,
    ];

    /**
     * The URL of a CampusFlow logo file, versioned by its date so that browsers load a replaced logo.
     */
    public static function brandAsset(string $file): string
    {
        $path = 'images/campusflow/'.$file;

        return asset($path).'?v='.(@filemtime(public_path($path)) ?: '1');
    }

    /**
     * Tailwind's emerald moved one shade darker from 400 up, so that shade 600 (solid buttons, the open screen in the
     * menu) is #047857: Filament gives a button white text only when its 600 shade passes the 4.5:1 contrast check,
     * which emerald-600 does not. The 50 and 100 tints stay as they are.
     *
     * @return array<int, string>
     */
    public static function accentPalette(): array
    {
        $emerald = Color::Emerald;

        return [
            50 => $emerald[50],
            100 => $emerald[100],
            200 => $emerald[200],
            300 => $emerald[300],
            400 => $emerald[500],
            500 => 'oklch(0.54 0.128 164.4)',
            600 => $emerald[700],
            700 => $emerald[800],
            800 => $emerald[900],
            900 => $emerald[950],
            950 => 'oklch(0.2 0.04 173)',
        ];
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset()
            ->colors([
                'primary' => self::accentPalette(),
                'gray' => Color::Gray,
            ])
            ->brandLogo(fn (): string => config('campusflow.logo') ? Storage::disk('public')->url(config('campusflow.logo')) : self::brandAsset('campusflow-logo.svg'))
            ->darkModeBrandLogo(fn (): string => config('campusflow.logo') ? Storage::disk('public')->url(config('campusflow.logo')) : self::brandAsset('campusflow-logo-dark.svg'))
            ->brandLogoHeight('2rem')
            ->favicon(self::brandAsset('campusflow-icon.svg'))
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->collapsedSidebarWidth('4.5rem')
            ->navigationGroups([
                NavigationGroup::make(__('School'))->icon(Heroicon::OutlinedAcademicCap)->collapsed(),
                NavigationGroup::make(__('Fees'))->icon(Heroicon::OutlinedWallet)->collapsed(),
                NavigationGroup::make(__('Front desk'))->icon(Heroicon::OutlinedBellAlert)->collapsed(),
                NavigationGroup::make(__('Guidance'))->icon(Heroicon::OutlinedShieldExclamation)->collapsed(),
                NavigationGroup::make(__('Organization'))->icon(Heroicon::OutlinedBuildingOffice2)->collapsed(),
                NavigationGroup::make(__('Accounts'))->icon(Heroicon::OutlinedBuildingLibrary)->collapsed(),
                NavigationGroup::make(__('Administration'))->icon(Heroicon::OutlinedShieldCheck)->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                SchoolDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->databaseNotifications()
            ->navigationItems([])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('@vite(\'resources/css/app.css\')').'<link rel="apple-touch-icon" href="'.e(self::brandAsset('apple-touch-icon.png')).'">',
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => '<div id="cf-topbar-trail" wire:ignore></div>',
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): string => view('filament.components.sidebar-open-active-group')->render(),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => auth()->check() ? Blade::render('@livewire(\App\Livewire\BranchSwitcher::class)') : '',
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.auth.demo-accounts')->render(),
                scopes: Login::class,
            )
            ->middleware([
                NeedsTenant::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                EnsureValidTenantSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordIsChanged::class,
            ]);
    }
}
