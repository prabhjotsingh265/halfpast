@props([
    'code',
    'title',
    // How the clock behaves: sweep, blocked, drained, rush, stuck or filling.
    'mode' => 'sweep',
])

@php
    use App\Support\Daylight;

    // Twenty-four ticks round a day, midnight at the top; the four quarters are longer.
    $ticks = collect(range(0, 23))->map(function (int $hour) {
        $angle = deg2rad($hour * 15 - 90);
        $inner = $hour % 6 === 0 ? 62 : 68;

        return [
            'x1' => round(cos($angle) * $inner, 2), 'y1' => round(sin($angle) * $inner, 2),
            'x2' => round(cos($angle) * 74, 2), 'y2' => round(sin($angle) * 74, 2),
            'major' => $hour % 6 === 0,
        ];
    });
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} - Halfpast</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    {{--
        Self-contained on purpose: no app scripts or built assets, so these pages
        still render when the app itself is what's broken, or it's down for work.
    --}}
    <style>
        @font-face {
            font-family: 'Recursive';
            src: url('/fonts/recursive-latin-full.woff2') format('woff2-variations');
            font-weight: 300 1000;
            font-style: oblique 0deg 15deg;
            font-display: swap;
        }

        :root {
            color-scheme: light dark;
            --paper: #FBFAF7;
            --ink: #15181F;
            --ink-2: #51555E;
            --line: #DBDEE3;
            --sun: #EF912D;
            --sun-deep: #A24E10;
            --dial: {!! Daylight::dialGradient() !!};
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --paper: #0F1217;
                --ink: #F0EEEA;
                --ink-2: #A9ADB5;
                --line: #2A2E36;
                --sun-deep: #F4A65A;
                --dial: {!! Daylight::dialGradient(dark: true) !!};
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 2rem 1.25rem;
            background: var(--paper);
            color: var(--ink);
            font-family: 'Recursive', ui-sans-serif, system-ui, sans-serif;
            font-variation-settings: 'MONO' 0, 'CASL' 0;
            -webkit-font-smoothing: antialiased;
        }

        main { width: 100%; max-width: 31rem; text-align: center; }

        .dial { position: relative; width: min(62vw, 15rem); aspect-ratio: 1; margin: 0 auto 2.25rem; }

        /* The day around the clock, in the same colours as every time slot. */
        .ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: var(--dial);
            -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 0.95rem), #000 calc(100% - 0.9rem));
            mask: radial-gradient(farthest-side, transparent calc(100% - 0.95rem), #000 calc(100% - 0.9rem));
        }

        .face { position: absolute; inset: 0; overflow: visible; }
        .tick { stroke: var(--line); stroke-width: 1.5; stroke-linecap: round; }
        .tick.major { stroke: var(--ink-2); stroke-width: 2.5; }
        .hand, .wedge, .fill, .drain { transform-origin: 0 0; }
        .hand line { stroke: var(--ink); stroke-width: 3.5; stroke-linecap: round; }
        .hand circle { fill: var(--sun); }
        .hub { fill: var(--paper); stroke: var(--line); stroke-width: 1.5; }

        .code {
            font-variation-settings: 'MONO' 1, 'CASL' 0;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.02em;
            fill: var(--ink);
        }

        /* 404: the hand sweeps round, looking, with a soft trail. */
        .sweep .hand, .sweep .wedge { animation: turn 6s linear infinite; }
        .wedge path { fill: var(--sun); opacity: 0.16; }

        /* 403: the hand keeps nudging into a stretch that's blocked out. */
        .blocked-arc { fill: url(#hatch); }
        #hatch line { stroke: var(--ink-2); stroke-width: 1.5; }
        .blocked .hand { animation: nudge 2.6s cubic-bezier(.4, 0, .2, 1) infinite; }

        /* 419: the hold ran out, the ring drains like an expired slot's. */
        .drain { fill: none; stroke: var(--sun); stroke-width: 5; stroke-linecap: round; stroke-dasharray: 289; }
        .drained .drain { animation: drain 2.4s cubic-bezier(.5, 0, .5, 1) .3s forwards; }

        /* 429: ticking far too fast. */
        .rush .hand { animation: turn 2.4s steps(24) infinite; }

        /* 500: stuck at half past three, twitching. */
        .stuck .hand { transform: rotate(232.5deg); animation: twitch 3.2s ease-in-out infinite; }

        /* Maintenance: the face fills to half past, the way the logo is drawn. */
        .fill { fill: none; stroke: var(--sun); stroke-width: 56; stroke-dasharray: 0 176; transform: rotate(-90deg); }
        .filling .fill { animation: fill 4.8s ease-in-out infinite alternate; }
        .filling .hand { animation: half 4.8s ease-in-out infinite alternate; }

        @keyframes turn { to { transform: rotate(360deg); } }
        @keyframes nudge {
            0%, 100% { transform: rotate(196deg); }
            38% { transform: rotate(222deg); }
            46% { transform: rotate(213deg); }
            54% { transform: rotate(220deg); }
        }
        @keyframes drain { to { stroke-dashoffset: 289; } }
        @keyframes twitch {
            0%, 84%, 100% { transform: rotate(232.5deg); }
            87% { transform: rotate(239deg); }
            90% { transform: rotate(228deg); }
            93% { transform: rotate(235deg); }
        }
        @keyframes fill { from { stroke-dasharray: 0 176; } to { stroke-dasharray: 88 176; } }
        @keyframes half { from { transform: rotate(0deg); } to { transform: rotate(180deg); } }

        h1 {
            margin: 0;
            font-size: clamp(1.75rem, 6vw, 2.5rem);
            line-height: 1.08;
            font-weight: 820;
            letter-spacing: -0.02em;
            font-variation-settings: 'MONO' 0, 'CASL' 1;
            text-wrap: balance;
        }

        p.message {
            margin: 1rem auto 0;
            max-width: 27rem;
            color: var(--ink-2);
            font-size: 1.0625rem;
            line-height: 1.55;
            text-wrap: pretty;
        }

        p.message code { font-variation-settings: 'MONO' 1; color: var(--ink); }

        .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.625rem; margin-top: 2rem; }

        .actions a {
            display: inline-flex;
            align-items: center;
            min-height: 2.75rem;
            padding: 0 1.125rem;
            border-radius: 10px;
            border: 1px solid var(--line);
            color: var(--ink);
            font-weight: 620;
            text-decoration: none;
            transition: border-color 150ms ease-out, transform 150ms ease-out;
        }

        .actions a:first-child { background: var(--sun); border-color: var(--sun); color: #15181F; }
        .actions a:hover { border-color: var(--ink-2); }
        .actions a:active { transform: translateY(1px); }
        .actions a:focus-visible { outline: 3px solid var(--sun-deep); outline-offset: 2px; }

        footer { margin-top: 3.5rem; }

        footer a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--ink-2);
            font-size: 0.875rem;
            font-weight: 650;
            text-decoration: none;
        }

        @media (prefers-reduced-motion: reduce) {
            .hand, .wedge, .drain, .fill { animation: none !important; }
            .drained .drain { stroke-dashoffset: 289; }
            .filling .fill { stroke-dasharray: 88 176; }
            .filling .hand { transform: rotate(180deg); }
            .blocked .hand { transform: rotate(213deg); }
        }
    </style>
</head>
<body>
    <main>
        <div class="dial {{ $mode }}" aria-hidden="true">
            <div class="ring"></div>
            <svg class="face" viewBox="-100 -100 200 200">
                <defs>
                    <pattern id="hatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                        <line x1="0" y1="0" x2="0" y2="6" />
                    </pattern>
                </defs>

                @foreach ($ticks as $tick)
                    <line @class(['tick', 'major' => $tick['major']]) x1="{{ $tick['x1'] }}" y1="{{ $tick['y1'] }}" x2="{{ $tick['x2'] }}" y2="{{ $tick['y2'] }}" />
                @endforeach

                @if ($mode === 'sweep')
                    {{-- A 50-degree trail behind the hand. --}}
                    <g class="wedge"><path d="M0 0 L0 -60 A60 60 0 0 0 -45.96 -38.57 Z" /></g>
                @elseif ($mode === 'blocked')
                    {{-- 15:00 to 18:00, hatched the way breaks are on the calendar. --}}
                    <path class="blocked-arc" d="M-53.03 53.03 A75 75 0 0 1 -75 0 L-58 0 A58 58 0 0 0 -41.01 41.01 Z" />
                @elseif ($mode === 'drained')
                    <circle class="drain" r="46" transform="rotate(-90)" />
                @elseif ($mode === 'filling')
                    <circle class="fill" r="28" />
                @endif

                <g class="hand">
                    <line x1="0" y1="0" x2="0" y2="-60" />
                    <circle cy="-60" r="5.5" />
                </g>

                <circle class="hub" r="24" />
                <text class="code" text-anchor="middle" dominant-baseline="central">{{ $code }}</text>
            </svg>
        </div>

        <h1>{{ $title }}</h1>
        <p class="message">{{ $slot }}</p>

        <div class="actions">
            @isset($actions)
                {{ $actions }}
            @else
                <a href="{{ url('/') }}">Go to Halfpast</a>
            @endisset
        </div>

        <footer>
            <a href="{{ url('/') }}">
                <svg width="20" height="20" viewBox="0 0 32 32" aria-hidden="true">
                    <circle cx="16" cy="16" r="14" fill="none" stroke="currentColor" stroke-width="3" />
                    <path d="M16 3.5a12.5 12.5 0 0 1 0 25z" fill="#EF912D" />
                    <path d="M16 3.5v25" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                Halfpast
            </a>
        </footer>
    </main>
</body>
</html>
