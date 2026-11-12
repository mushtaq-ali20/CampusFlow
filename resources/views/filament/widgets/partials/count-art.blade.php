<svg viewBox="0 0 48 48" class="art-bob-slow size-11" style="animation-delay: {{ $delay ?? 0 }}ms" aria-hidden="true">
    @switch($art)
        @case('branch')
            <rect x="4" y="22" width="12" height="20" rx="1.5" fill="#f7c66a" />
            <polygon points="2,23 10,15 18,23" fill="#e2553f" />
            <rect x="16" y="14" width="16" height="28" rx="1.5" fill="#fcd88a" />
            <polygon points="14,15 24,6 34,15" fill="#e2553f" />
            <rect x="32" y="22" width="12" height="20" rx="1.5" fill="#f7c66a" />
            <polygon points="30,23 38,15 46,23" fill="#e2553f" />
            <rect x="21" y="32" width="6" height="10" fill="#7c2d12" />
            <rect x="19" y="19" width="10" height="7" fill="#e0f2fe" stroke="#78350f" />
            @break
        @case('class')
            <rect x="4" y="8" width="40" height="28" rx="2" fill="#1f5a33" stroke="#7c4a1e" stroke-width="3" />
            <text x="24" y="27" text-anchor="middle" font-size="11" font-weight="700" fill="#fafaf9">A B C</text>
            <path d="M14 36l-4 8M34 36l4 8" stroke="#7c4a1e" stroke-width="3" stroke-linecap="round" />
            <rect x="30" y="31" width="9" height="3" rx="1" fill="#fafaf9" />
            @break
        @case('student')
            <rect x="9" y="12" width="30" height="32" rx="9" fill="#0ea5e9" />
            <path d="M17 12v-3a7 7 0 0 1 14 0v3" stroke="#0369a1" stroke-width="3" fill="none" />
            <rect x="14" y="26" width="20" height="12" rx="3" fill="#0369a1" />
            <rect x="22" y="24" width="4" height="5" rx="1" fill="#facc15" />
            @break
        @case('teacher')
            <rect x="5" y="22" width="26" height="20" rx="2" fill="#6d28d9" />
            <rect x="8" y="22" width="20" height="17" fill="#ede9fe" />
            <path d="M18 22v17" stroke="#6d28d9" stroke-width="1.5" />
            <circle cx="36" cy="30" r="9" fill="#e11d48" />
            <path d="M36 21c0-4 3-6 5-6" stroke="#7c4a1e" stroke-width="2" fill="none" />
            <ellipse cx="40" cy="18" rx="4" ry="2.2" fill="#22c55e" />
            @break
    @endswitch
</svg>
