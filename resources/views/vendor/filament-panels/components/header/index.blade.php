@props([
    'actions' => [],
    'actionsAlignment' => null,
    'breadcrumbs' => [],
    'heading' => null,
    'subheading' => null,
])

@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    use function Filament\Support\is_slot_empty;

    // CampusFlow: the breadcrumb is shown in the top bar, and the menu icon of the screen sits beside its name.
    $isPanelPage = $this instanceof \Filament\Pages\Page;
    $trail = $isPanelPage ? \App\Services\PageTrail::crumbs($this, $breadcrumbs) : [];
    $trailIcon = $isPanelPage ? \App\Services\PageTrail::icon($this) : null;
@endphp

@if ($trail)
    <template x-teleport="#cf-topbar-trail">
        <x-filament::breadcrumbs :breadcrumbs="$trail" class="cf-topbar-trail" />
    </template>
@endif

<header
    {{
        $attributes->class([
            'fi-header',
            'fi-header-has-breadcrumbs' => $breadcrumbs,
            'fi-header-has-subheading' => filled($subheading),
        ])
    }}
>
    @capture($headingContent)
        @if ($breadcrumbs && (! $trail))
            <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
        @endif

        {{ FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE, scopes: $this->getRenderHookScopes()) }}

        @if (filled($heading))
            <h1 class="fi-header-heading">
                {{ $heading }}
            </h1>
        @endif

        {{ FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_AFTER, scopes: $this->getRenderHookScopes()) }}

        @if (filled($subheading))
            <p class="fi-header-subheading">
                {{ $subheading }}
            </p>
        @endif
    @endcapture

    @php
        $headingContent = $headingContent();
    @endphp

    @if (! is_slot_empty($headingContent))
        <div class="cf-header-title">
            @if ($trailIcon && filled($heading))
                <span class="cf-header-icon" aria-hidden="true">
                    {{ \Filament\Support\generate_icon_html($trailIcon, size: \Filament\Support\Enums\IconSize::Large) }}
                </span>
            @endif

            <div>{{ $headingContent }}</div>
        </div>
    @else
        {{ $headingContent }}
    @endif

    @php
        $beforeActions = FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE, scopes: $this->getRenderHookScopes());
        $afterActions = FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER, scopes: $this->getRenderHookScopes());
    @endphp

    @capture($actionsContent)
        {{ $beforeActions }}

        @if ($actions)
            <x-filament::actions
                :actions="$actions"
                :alignment="$actionsAlignment"
            />
        @endif

        {{ $afterActions }}
    @endcapture

    @php
        $actionsContent = $actionsContent();
    @endphp

    @if (! is_slot_empty($actionsContent))
        <div class="fi-header-actions-ctn">
            {{ $actionsContent }}
        </div>
    @else
        {{ $actionsContent }}
    @endif
</header>
