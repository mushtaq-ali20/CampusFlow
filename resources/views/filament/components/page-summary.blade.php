{{--
    The line under a screen's name: how many records the list shows, and which branch. A money screen that has a
    getHeaderStats() method shows the line as pills instead, each with a coloured dot (danger, warning, success).
--}}
@php
    use App\Services\ActiveBranch;
    use App\Services\PageTrail;

    $count = PageTrail::recordCount($page);
    $followsBranch = PageTrail::followsBranch($page);
    $branch = $followsBranch ? app(ActiveBranch::class)->campus() : null;
    $branchText = $branch ? __('Showing :branch only', ['branch' => $branch->name]) : ($followsBranch ? __('All branches') : null);

    /** @var list<array{label: string, tone?: string}> $stats */
    $stats = method_exists($page, 'getHeaderStats') ? $page->getHeaderStats() : [];
@endphp

@if ($stats)
    <div class="cf-header-stats">
        @if ($count)
            <span class="cf-stat">{{ $count }}</span>
        @endif

        @foreach ($stats as $stat)
            <span class="cf-stat">
                @if (filled($stat['tone'] ?? null))
                    <span class="cf-stat-dot cf-stat-dot-{{ $stat['tone'] }}" aria-hidden="true"></span>
                @endif
                {{ $stat['label'] }}
            </span>
        @endforeach

        @if ($branchText)
            <span @class(['cf-stat', 'cf-stat-branch' => $branch])>{{ $branchText }}</span>
        @endif
    </div>
@elseif ($count || $branchText)
    <p class="cf-header-summary">
        @if ($count)
            <span>{{ $count }}</span>
        @endif

        @if ($branchText)
            <span @class(['cf-header-summary-branch' => $branch])>{{ $branchText }}</span>
        @endif
    </p>
@endif
