<svg viewBox="0 0 640 300" class="block h-auto w-full" role="img" aria-label="{{ __('The school with students walking in and the school bus passing') }}">
    <g class="art-day">
        <g class="art-spin">
            <circle cx="560" cy="62" r="46" fill="#fde68a" opacity=".5" />
            <path d="M560 6v18M560 100v18M504 62h18M598 62h18M520 22l12 12M588 90l12 12M520 102l12-12M588 34l12-12" stroke="#f59e0b" stroke-width="5" stroke-linecap="round" />
        </g>
        <circle cx="560" cy="62" r="26" fill="#fbbf24" />
    </g>
    <g class="art-night">
        <circle cx="560" cy="60" r="26" fill="#fef3c7" />
        <circle cx="572" cy="52" r="22" fill="var(--art-sky)" />
        <circle class="art-twinkle" cx="80" cy="40" r="2.5" fill="#fef3c7" />
        <circle class="art-twinkle" style="animation-delay: .8s" cx="200" cy="24" r="2" fill="#fef3c7" />
        <circle class="art-twinkle" style="animation-delay: 1.4s" cx="470" cy="30" r="2.5" fill="#fef3c7" />
        <circle class="art-twinkle" style="animation-delay: .4s" cx="360" cy="70" r="1.8" fill="#fef3c7" />
    </g>
    <g class="art-drift" fill="var(--art-cloud)">
        <ellipse cx="120" cy="58" rx="40" ry="16" />
        <ellipse cx="146" cy="48" rx="26" ry="18" />
        <ellipse cx="98" cy="50" rx="20" ry="13" />
    </g>
    <g class="art-drift-slow" fill="var(--art-cloud)" opacity=".85">
        <ellipse cx="400" cy="34" rx="30" ry="11" />
        <ellipse cx="418" cy="27" rx="18" ry="12" />
    </g>

    <rect x="0" y="236" width="640" height="64" fill="var(--art-grass)" />
    <rect x="0" y="266" width="640" height="22" fill="var(--art-road)" />
    <path d="M10 277h30M80 277h30M150 277h30M220 277h30M290 277h30M360 277h30M430 277h30M500 277h30M570 277h30" stroke="#fafaf9" stroke-width="2.5" opacity=".7" />

    <rect x="54" y="190" width="8" height="48" fill="#7c4a1e" />
    <circle cx="58" cy="178" r="26" fill="var(--art-tree)" />
    <circle cx="44" cy="190" r="16" fill="var(--art-tree)" />
    <rect x="588" y="196" width="8" height="42" fill="#7c4a1e" />
    <circle cx="592" cy="186" r="22" fill="var(--art-tree)" />

    <rect x="150" y="140" width="80" height="96" rx="3" fill="var(--art-wall-side)" />
    <rect x="430" y="140" width="80" height="96" rx="3" fill="var(--art-wall-side)" />
    <polygon points="142,142 190,112 238,142" fill="var(--art-roof)" />
    <polygon points="422,142 470,112 518,142" fill="var(--art-roof)" />
    <rect x="222" y="112" width="216" height="124" rx="3" fill="var(--art-wall)" />
    <polygon points="210,114 330,52 450,114" fill="var(--art-roof)" />
    <line x1="330" y1="52" x2="330" y2="10" stroke="#57534e" stroke-width="3" />
    <path class="art-wave" d="M331 12h38l-8 9 8 9h-38z" fill="#15803d" />
    <circle cx="330" cy="90" r="13" fill="#fafaf9" stroke="#57534e" stroke-width="2" />
    <path d="M330 90v-8M330 90l6 3" stroke="#1c1917" stroke-width="2" stroke-linecap="round" />
    @php($schoolName = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit(config('app.name'), 40, '')))
    <rect x="236" y="118" width="188" height="22" rx="3" fill="#1c1917" opacity=".85" />
    <text x="330" y="133.5" text-anchor="middle" font-size="{{ min(11, round(300 / max(1, mb_strlen($schoolName)), 1)) }}" font-weight="700" fill="#fde68a" @if (mb_strlen($schoolName) > 16) textLength="176" lengthAdjust="spacingAndGlyphs" @else letter-spacing="2" @endif>{{ $schoolName }}</text>
    <g fill="var(--art-window)" stroke="#78350f" stroke-width="2">
        <rect x="240" y="152" width="30" height="26" rx="2" /><rect x="390" y="152" width="30" height="26" rx="2" />
        <rect x="240" y="192" width="30" height="26" rx="2" /><rect x="390" y="192" width="30" height="26" rx="2" />
        <rect x="166" y="160" width="22" height="20" rx="2" /><rect x="194" y="160" width="22" height="20" rx="2" />
        <rect x="166" y="196" width="22" height="20" rx="2" /><rect x="194" y="196" width="22" height="20" rx="2" />
        <rect x="446" y="160" width="22" height="20" rx="2" /><rect x="474" y="160" width="22" height="20" rx="2" />
        <rect x="446" y="196" width="22" height="20" rx="2" /><rect x="474" y="196" width="22" height="20" rx="2" />
    </g>
    <path d="M306 236v-62a24 24 0 0 1 48 0v62z" fill="#7c2d12" />
    <path d="M330 236v-80" stroke="#fbbf24" stroke-width="2" />
    <rect x="296" y="232" width="68" height="6" fill="#d6d3d1" />

    <g class="art-walk">
        <g class="art-bob">
            <rect x="180" y="226" width="5" height="12" fill="#1c1917" /><rect x="188" y="226" width="5" height="12" fill="#1c1917" />
            <rect x="178" y="206" width="17" height="22" rx="5" fill="#0369a1" />
            <rect x="173" y="208" width="8" height="15" rx="3" fill="#e11d48" />
            <circle cx="186.5" cy="198" r="8" fill="#e0ac69" />
            <path d="M178.5 197a8 8 0 0 1 16 0c-3-3-13-3-16 0z" fill="#2d1b0e" />
        </g>
        <g class="art-bob" style="animation-delay: .35s">
            <rect x="214" y="228" width="4" height="10" fill="#1c1917" /><rect x="220" y="228" width="4" height="10" fill="#1c1917" />
            <rect x="211" y="211" width="16" height="19" rx="5" fill="#15803d" />
            <rect x="223" y="213" width="7" height="13" rx="3" fill="#f59e0b" />
            <circle cx="219" cy="203" r="7.5" fill="#c68642" />
            <path d="M211 205c-1-11 17-11 16 0v8c-2-2-2-6-2-8-4-4-8-4-12 0 0 2 0 6-2 8z" fill="#1f1f1f" />
        </g>
    </g>
    <g class="art-walk" style="animation-delay: -3s">
        <g class="art-bob" style="animation-delay: .6s">
            <rect x="452" y="226" width="5" height="12" fill="#1c1917" /><rect x="460" y="226" width="5" height="12" fill="#1c1917" />
            <rect x="450" y="206" width="17" height="22" rx="5" fill="#6d28d9" />
            <rect x="463" y="208" width="8" height="15" rx="3" fill="#0ea5e9" />
            <circle cx="458.5" cy="198" r="8" fill="#f1c27d" />
            <path d="M450.5 197a8 8 0 0 1 16 0c-3-3-13-3-16 0z" fill="#5a3825" />
        </g>
    </g>

    <g class="art-bus">
        <rect x="0" y="236" width="128" height="40" rx="8" fill="#facc15" />
        <rect x="0" y="236" width="128" height="10" rx="5" fill="#eab308" />
        <g fill="#bae6fd" stroke="#1c1917" stroke-width="1.5">
            <rect x="10" y="244" width="20" height="14" rx="2" /><rect x="36" y="244" width="20" height="14" rx="2" />
            <rect x="62" y="244" width="20" height="14" rx="2" /><rect x="96" y="244" width="24" height="16" rx="2" />
        </g>
        <rect x="6" y="262" width="88" height="5" fill="#1c1917" opacity=".8" />
        <text x="48" y="266.5" text-anchor="middle" font-size="5" font-weight="700" fill="#facc15">SCHOOL BUS</text>
        <circle cx="28" cy="278" r="8" fill="#1c1917" /><circle cx="28" cy="278" r="3" fill="#d6d3d1" />
        <circle cx="100" cy="278" r="8" fill="#1c1917" /><circle cx="100" cy="278" r="3" fill="#d6d3d1" />
    </g>
</svg>
