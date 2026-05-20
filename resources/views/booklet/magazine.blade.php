@php
    /** @var \Platform\Locations\Models\Location $location */
    /** @var ?string $hero */
    /** @var array<int,string> $spread */
    /** @var ?string $floorPlan */
    /** @var \Illuminate\Support\Collection $seatings */
    /** @var array<int,string> $anlaesse */

    $hasSpread     = !empty($spread);
    $hasSeating    = $seatings->isNotEmpty();
    $hasAnlaesse   = !empty($anlaesse);
    $hasAddress    = !empty($location->adresse) || ($location->latitude && $location->longitude);
    $hasDescription = !empty($location->beschreibung);
    $hasFloorPlan  = !empty($floorPlan);

    // Eckdaten zusammenstellen
    $eckdaten = collect([
        ['label' => 'PAX max.',         'value' => $location->pax_max ? number_format($location->pax_max, 0, ',', '.') : null, 'note' => $location->pax_min ? 'ab ' . $location->pax_min : null],
        ['label' => 'Fläche',           'value' => $location->groesse_qm ? rtrim(rtrim(number_format($location->groesse_qm, 2, ',', '.'), '0'), ',') . ' m²' : null],
        ['label' => 'Halle',            'value' => $location->hallennummer],
        ['label' => 'Mehrfachbelegung', 'value' => $location->mehrfachbelegung ? 'Ja' : 'Nein'],
        ['label' => 'Barrierefrei',     'value' => $location->barrierefrei ? 'Ja' : 'Nein'],
        ['label' => 'Gruppe',           'value' => $location->gruppe],
    ])->filter(fn ($e) => !empty($e['value']))->values();
@endphp
<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $location->name }} — Booklet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,600;9..144,700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink:        #0e0e0d;
            --paper:      #f6f1e8;
            --paper-warm: #efe6d5;
            --line:       #1a1a18;
            --mute:       #6b6256;
            --accent:     #b88a3e;
        }

        @page {
            size: A4;
            margin: 0;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--ink);
            background: #d6cdb9;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ============== SCREEN-LAYOUT: Magazin zentriert mit Außenrand ============== */
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10mm;
            padding: 12mm 0;
        }

        .page {
            box-shadow: 0 8px 40px -8px rgba(20, 16, 10, 0.22),
                        0 2px 10px -2px rgba(20, 16, 10, 0.10);
            border-radius: 1mm;
        }

        /* PRINT/PDF: Zentrier-Container, Schatten und Radius weg, damit das
           PDF wieder seitenrein A4 ist. */
        @media print {
            html, body {
                background: var(--paper);
            }
            body {
                display: block;
                padding: 0;
                gap: 0;
            }
            .page {
                box-shadow: none;
                border-radius: 0;
            }
        }

        .display { font-family: 'Fraunces', Georgia, serif; font-weight: 400; letter-spacing: -0.01em; }
        .mono    { font-family: ui-monospace, SFMono-Regular, 'Menlo', monospace; }

        .page {
            width: 210mm;
            height: 297mm;
            page-break-after: always;
            position: relative;
            overflow: hidden;
        }
        .page:last-of-type { page-break-after: auto; }

        /* ============== COVER ============== */
        .cover {
            background: var(--ink);
            color: var(--paper);
            display: flex;
            flex-direction: column;
        }
        .cover__hero {
            flex: 1 1 auto;
            background-color: #1a1a18;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .cover__hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0) 40%, rgba(0,0,0,0.65) 100%);
        }
        .cover__nohero {
            flex: 1 1 auto;
            background:
                radial-gradient(circle at 30% 30%, rgba(184,138,62,0.35), transparent 60%),
                radial-gradient(circle at 80% 70%, rgba(184,138,62,0.18), transparent 70%),
                #14140f;
        }
        .cover__meta {
            padding: 18mm 18mm 22mm 18mm;
            display: flex;
            flex-direction: column;
            gap: 6mm;
            background: var(--ink);
        }
        .cover__eyebrow {
            font-size: 10pt;
            letter-spacing: 0.4em;
            text-transform: uppercase;
            color: var(--accent);
            font-weight: 500;
        }
        .cover__name {
            font-size: 56pt;
            line-height: 0.95;
            font-family: 'Fraunces', Georgia, serif;
            font-weight: 400;
            color: var(--paper);
            margin: 0;
            letter-spacing: -0.02em;
        }
        .cover__row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 4mm;
            border-top: 1px solid rgba(246,241,232,0.2);
            padding-top: 4mm;
        }
        .cover__row-left { font-size: 10pt; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(246,241,232,0.6); }
        .cover__kuerzel {
            font-family: 'Fraunces', Georgia, serif;
            font-size: 22pt;
            color: var(--paper);
            font-weight: 500;
        }

        /* ============== CONTENT-PAGES ============== */
        .content {
            padding: 22mm 18mm;
            background: var(--paper);
            color: var(--ink);
        }
        .content__header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            border-bottom: 1px solid var(--line);
            padding-bottom: 4mm;
            margin-bottom: 12mm;
        }
        .content__title {
            font-family: 'Fraunces', Georgia, serif;
            font-weight: 400;
            font-size: 32pt;
            line-height: 1;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .content__caption {
            font-size: 9pt;
            letter-spacing: 0.35em;
            text-transform: uppercase;
            color: var(--mute);
        }

        /* ============== ECKDATEN-GRID ============== */
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10mm 12mm;
        }
        .stat {
            border-top: 1px solid var(--line);
            padding-top: 5mm;
        }
        .stat__label {
            font-size: 8.5pt;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: var(--mute);
            margin-bottom: 2mm;
        }
        .stat__value {
            font-family: 'Fraunces', Georgia, serif;
            font-size: 36pt;
            line-height: 1;
            font-weight: 400;
            letter-spacing: -0.02em;
        }
        .stat__note {
            font-size: 9pt;
            color: var(--mute);
            margin-top: 1.5mm;
        }

        .description {
            margin-top: 18mm;
            font-size: 11pt;
            line-height: 1.7;
            color: #2a2722;
            max-width: 150mm;
            white-space: pre-wrap;
        }

        /* ============== PHOTO-SPREAD ============== */
        .spread {
            padding: 0;
            background: var(--paper);
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            grid-template-rows: repeat(8, 1fr);
            width: 210mm;
            height: 297mm;
            gap: 3mm;
            padding: 12mm;
        }
        .spread__img {
            background-size: cover;
            background-position: center;
            background-color: #d6cdb9;
        }
        /* 1-4 Bilder: dynamische Slot-Klassen */
        .spread--n1 .s1 { grid-column: 1 / -1; grid-row: 1 / -1; }

        .spread--n2 .s1 { grid-column: 1 / 4;  grid-row: 1 / -1; }
        .spread--n2 .s2 { grid-column: 4 / -1; grid-row: 1 / -1; }

        .spread--n3 .s1 { grid-column: 1 / -1; grid-row: 1 / 6; }
        .spread--n3 .s2 { grid-column: 1 / 4;  grid-row: 6 / -1; }
        .spread--n3 .s3 { grid-column: 4 / -1; grid-row: 6 / -1; }

        .spread--n4 .s1 { grid-column: 1 / 4;  grid-row: 1 / 6; }
        .spread--n4 .s2 { grid-column: 4 / -1; grid-row: 1 / 4; }
        .spread--n4 .s3 { grid-column: 4 / -1; grid-row: 4 / 6; }
        .spread--n4 .s4 { grid-column: 1 / -1; grid-row: 6 / -1; }

        .spread--n5 .s1 { grid-column: 1 / 5;  grid-row: 1 / 5; }
        .spread--n5 .s2 { grid-column: 5 / -1; grid-row: 1 / 3; }
        .spread--n5 .s3 { grid-column: 5 / -1; grid-row: 3 / 5; }
        .spread--n5 .s4 { grid-column: 1 / 4;  grid-row: 5 / -1; }
        .spread--n5 .s5 { grid-column: 4 / -1; grid-row: 5 / -1; }

        .spread--n6 .s1 { grid-column: 1 / 4;  grid-row: 1 / 4; }
        .spread--n6 .s2 { grid-column: 4 / -1; grid-row: 1 / 4; }
        .spread--n6 .s3 { grid-column: 1 / 3;  grid-row: 4 / 7; }
        .spread--n6 .s4 { grid-column: 3 / 5;  grid-row: 4 / 7; }
        .spread--n6 .s5 { grid-column: 5 / -1; grid-row: 4 / 7; }
        .spread--n6 .s6 { grid-column: 1 / -1; grid-row: 7 / -1; }

        .spread--n7 .s1 { grid-column: 1 / 5;  grid-row: 1 / 4; }
        .spread--n7 .s2 { grid-column: 5 / -1; grid-row: 1 / 3; }
        .spread--n7 .s3 { grid-column: 5 / -1; grid-row: 3 / 5; }
        .spread--n7 .s4 { grid-column: 1 / 3;  grid-row: 4 / 7; }
        .spread--n7 .s5 { grid-column: 3 / 5;  grid-row: 4 / 7; }
        .spread--n7 .s6 { grid-column: 5 / -1; grid-row: 5 / 7; }
        .spread--n7 .s7 { grid-column: 1 / -1; grid-row: 7 / -1; }

        .spread--n8 .s1 { grid-column: 1 / 4;  grid-row: 1 / 4; }
        .spread--n8 .s2 { grid-column: 4 / -1; grid-row: 1 / 4; }
        .spread--n8 .s3 { grid-column: 1 / 3;  grid-row: 4 / 6; }
        .spread--n8 .s4 { grid-column: 3 / 5;  grid-row: 4 / 6; }
        .spread--n8 .s5 { grid-column: 5 / -1; grid-row: 4 / 6; }
        .spread--n8 .s6 { grid-column: 1 / 3;  grid-row: 6 / -1; }
        .spread--n8 .s7 { grid-column: 3 / 5;  grid-row: 6 / -1; }
        .spread--n8 .s8 { grid-column: 5 / -1; grid-row: 6 / -1; }

        /* ============== BESTUHLUNG-TABELLE ============== */
        .seatings {
            margin-top: 4mm;
        }
        .seating-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 5mm 0;
            border-bottom: 1px solid #d6cdb9;
        }
        .seating-row:last-child { border-bottom: 1px solid var(--line); }
        .seating-row__label {
            font-family: 'Fraunces', Georgia, serif;
            font-size: 20pt;
            font-weight: 400;
            letter-spacing: -0.01em;
        }
        .seating-row__pax {
            font-family: 'Fraunces', Georgia, serif;
            font-size: 24pt;
            color: var(--accent);
            font-weight: 500;
        }
        .seating-row__suffix {
            font-size: 9pt;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: var(--mute);
            margin-left: 1mm;
        }

        /* ============== ANLAESSE — PILL-TAGS ============== */
        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 3mm;
            margin-top: 4mm;
        }
        .tag {
            border: 1px solid var(--line);
            padding: 2mm 5mm;
            border-radius: 999px;
            font-size: 11pt;
            letter-spacing: 0.02em;
        }

        /* ============== ADRESSE ============== */
        .address {
            margin-top: 12mm;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 10mm;
            align-items: start;
            border-top: 1px solid var(--line);
            padding-top: 6mm;
        }
        .address__line {
            font-family: 'Fraunces', Georgia, serif;
            font-size: 18pt;
            line-height: 1.3;
        }
        .address__coords {
            font-family: ui-monospace, monospace;
            font-size: 9pt;
            color: var(--mute);
            text-align: right;
            line-height: 1.7;
        }

        /* ============== GRUNDRISS-SEITE ============== */
        .floorplan {
            margin-top: 4mm;
            padding: 6mm;
            background: #ffffff;
            border: 1px solid #e8dfcb;
            border-radius: 1mm;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200mm;
        }
        .floorplan img {
            max-width: 100%;
            max-height: 200mm;
            object-fit: contain;
            display: block;
        }
        .floorplan__caption {
            font-size: 9pt;
            color: var(--mute);
            letter-spacing: 0.2em;
            text-transform: uppercase;
            text-align: center;
            margin-top: 4mm;
        }

        /* ============== FOOTER auf jeder Content-Page ============== */
        .footer {
            position: absolute;
            bottom: 12mm;
            left: 18mm;
            right: 18mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8pt;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: var(--mute);
            border-top: 1px solid #d6cdb9;
            padding-top: 3mm;
        }
        .footer__brand {
            font-family: 'Fraunces', Georgia, serif;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: none;
            color: var(--ink);
            font-size: 11pt;
        }
    </style>
</head>
<body>

    {{-- ============ COVER ============ --}}
    <section class="page cover">
        @if($hero)
            <div class="cover__hero" style="background-image:url('{{ $hero }}')"></div>
        @else
            <div class="cover__nohero"></div>
        @endif
        <div class="cover__meta">
            <div class="cover__eyebrow">Location · Booklet</div>
            <h1 class="cover__name">{{ $location->name }}</h1>
            <div class="cover__row">
                <div class="cover__row-left">
                    @if($location->gruppe){{ $location->gruppe }}@endif
                    @if($location->pax_max) · {{ number_format($location->pax_max, 0, ',', '.') }} PAX @endif
                </div>
                <div class="cover__kuerzel">{{ $location->kuerzel }}</div>
            </div>
        </div>
    </section>

    {{-- ============ ECKDATEN + BESCHREIBUNG ============ --}}
    @if($eckdaten->isNotEmpty() || $hasDescription)
    <section class="page content">
        <div class="content__header">
            <h2 class="content__title">Eckdaten</h2>
            <div class="content__caption">No. 01</div>
        </div>

        @if($eckdaten->isNotEmpty())
            <div class="stats">
                @foreach($eckdaten as $e)
                    <div class="stat">
                        <div class="stat__label">{{ $e['label'] }}</div>
                        <div class="stat__value">{{ $e['value'] }}</div>
                        @if(!empty($e['note']))
                            <div class="stat__note">{{ $e['note'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if($hasDescription)
            <div class="description">{{ $location->beschreibung }}</div>
        @endif

        <div class="footer">
            <span>{{ $location->name }}</span>
            <span class="footer__brand">{{ $location->kuerzel }}</span>
        </div>
    </section>
    @endif

    {{-- ============ PHOTO-SPREAD ============ --}}
    @if($hasSpread)
        @php $n = min(count($spread), 8); @endphp
        <section class="page spread spread--n{{ $n }}">
            @foreach(array_slice($spread, 0, $n) as $i => $url)
                <div class="spread__img s{{ $i + 1 }}" style="background-image:url('{{ $url }}')"></div>
            @endforeach
        </section>
    @endif

    {{-- ============ BESTUHLUNG ============ --}}
    @if($hasSeating)
    <section class="page content">
        <div class="content__header">
            <h2 class="content__title">Bestuhlung</h2>
            <div class="content__caption">No. 02</div>
        </div>

        <div class="seatings">
            @foreach($seatings as $s)
                <div class="seating-row">
                    <div class="seating-row__label">{{ $s->label }}</div>
                    <div>
                        <span class="seating-row__pax">{{ number_format((int) $s->pax_max_ca, 0, ',', '.') }}</span>
                        <span class="seating-row__suffix">PAX ca.</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="footer">
            <span>{{ $location->name }}</span>
            <span class="footer__brand">{{ $location->kuerzel }}</span>
        </div>
    </section>
    @endif

    {{-- ============ GRUNDRISS ============ --}}
    @if($hasFloorPlan)
    <section class="page content">
        <div class="content__header">
            <h2 class="content__title">Grundriss</h2>
            <div class="content__caption">Plan</div>
        </div>

        <div class="floorplan">
            <img src="{{ $floorPlan }}" alt="Grundriss {{ $location->name }}">
        </div>
        <div class="floorplan__caption">{{ $location->kuerzel }} · Massstab gemaess Plan</div>

        <div class="footer">
            <span>{{ $location->name }}</span>
            <span class="footer__brand">{{ $location->kuerzel }}</span>
        </div>
    </section>
    @endif

    {{-- ============ ANLAESSE + ADRESSE ============ --}}
    @if($hasAnlaesse || $hasAddress)
    @php
        $sectionTitle = match (true) {
            $hasAnlaesse && $hasAddress => 'Anlässe & Adresse',
            $hasAnlaesse                => 'Anlässe',
            default                     => 'Adresse',
        };
    @endphp
    <section class="page content">
        <div class="content__header">
            <h2 class="content__title">{{ $sectionTitle }}</h2>
            <div class="content__caption">No. 03</div>
        </div>

        @if($hasAnlaesse)
            <div class="tags">
                @foreach($anlaesse as $tag)
                    <span class="tag">{{ $tag }}</span>
                @endforeach
            </div>
        @endif

        @if($hasAddress)
            <div class="address">
                <div class="address__line">
                    @if(!empty($location->adresse))
                        {{ $location->adresse }}
                    @else
                        Adresse auf Anfrage
                    @endif
                </div>
                @if($location->latitude && $location->longitude)
                    <div class="address__coords">
                        {{ number_format($location->latitude, 5, '.', '') }}<br>
                        {{ number_format($location->longitude, 5, '.', '') }}
                    </div>
                @endif
            </div>
        @endif

        <div class="footer">
            <span>{{ $location->name }}</span>
            <span class="footer__brand">{{ $location->kuerzel }}</span>
        </div>
    </section>
    @endif

</body>
</html>
