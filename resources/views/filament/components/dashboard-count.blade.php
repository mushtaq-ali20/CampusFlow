{{--
    A number that counts up from zero when the dashboard opens. The final number is in the markup, so it reads the
    same without JavaScript and for screen readers; under reduced motion it shows the final number at once.
    Needs: $value (number), $decimals, $prefix, $suffix.
--}}
@php
    $final = $prefix.number_format((float) $value, $decimals).$suffix;
@endphp
<span
    x-data="{
        shown: @js($final),
        start() {
            const target = {{ (float) $value }};
            if (! target || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const format = (n) => @js($prefix) + n.toLocaleString('en-US', { minimumFractionDigits: {{ $decimals }}, maximumFractionDigits: {{ $decimals }} }) + @js($suffix);
            const began = performance.now();
            const step = (now) => {
                const p = Math.min(1, (now - began) / 900);
                this.shown = format(target * (1 - Math.pow(1 - p, 3)));
                if (p < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
            setTimeout(() => this.shown = @js($final), 1200);
        },
    }"
    x-init="start()"
    x-text="shown"
>{{ $final }}</span>
