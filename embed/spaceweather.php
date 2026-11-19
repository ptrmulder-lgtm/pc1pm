<?php
/**
 * PC1PM – ruimteweer als afbeelding (SVG), voor plekken waar geen JavaScript mag (QRZ.com, forums).
 * Gebruik:  <img src="https://pc1pm.nl/embed/spaceweather.php?lang=en" width="870">
 * Data: NOAA SWPC (services.swpc.noaa.gov). Resultaat wordt 10 minuten gecachet.
 * Zelfde logica als js/spaceweather.js (bandschatting, teksten) – wijzig ze samen.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

const CACHE_TTL = 600;
const API = 'https://services.swpc.noaa.gov/';
const HOME_LAT = 52.1;
const HOME_LON = 5.3;
const W = 870;

$lang = (($_GET['lang'] ?? 'en') === 'nl') ? 'nl' : 'en';

/* ---------- cache ---------- */
$cacheDir = __DIR__ . '/../.cache';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0750, true); }
if (!is_dir($cacheDir) || !is_writable($cacheDir)) { $cacheDir = sys_get_temp_dir(); }
$cacheFile = $cacheDir . '/pc1pm-spaceweather-' . $lang . '.svg';
$fresh = isset($_GET['fresh']);

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=' . CACHE_TTL);
header('X-Content-Type-Options: nosniff');

if (!$fresh && is_file($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_TTL) {
    readfile($cacheFile);
    exit;
}

/* ---------- data ophalen ---------- */
function fetch(string $path): ?string {
    $url = API . $path;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'pc1pm.nl space weather widget',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code === 200) ? (string) $body : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: pc1pm.nl space weather widget\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}
function fetchJson(string $path) {
    $b = fetch($path);
    if ($b === null) return null;
    $d = json_decode($b, true);
    return is_array($d) ? $d : null;
}
function lastOf(?array $a) { return $a ? $a[count($a) - 1] : null; }

$sfi30  = fetchJson('products/10cm-flux-30-day.json');
$dsd    = fetch('text/daily-solar-indices.txt');
$kp3h   = fetchJson('products/noaa-planetary-k-index.json');
$kp1m   = fetchJson('json/planetary_k_index_1m.json');
$xr     = fetchJson('json/goes/primary/xrays-6-hour.json');
$wind   = fetchJson('products/summary/solar-wind-speed.json');
$mag    = fetchJson('products/summary/solar-wind-mag-field.json');
$scales = fetchJson('products/noaa-scales.json');

/* ---------- waarden ---------- */
$sfi = $sfi30 ? (float) lastOf($sfi30)['flux'] : null;
$sfiSeries = $sfi30 ? array_map(fn($r) => (float) $r['flux'], $sfi30) : [];

$ssn = null; $ssnSeries = [];
if ($dsd) {
    foreach (preg_split('/\r?\n/', $dsd) as $line) {
        if (preg_match('/^\d{4} \d\d \d\d/', $line)) {
            $p = preg_split('/\s+/', trim($line));
            if (isset($p[4]) && (float) $p[4] >= 0) { $ssnSeries[] = (float) $p[4]; }
        }
    }
    $ssn = $ssnSeries ? end($ssnSeries) : null;
}

$kp = null;
if ($kp1m) { $k = lastOf($kp1m); $kp = (float) ($k['estimated_kp'] ?? $k['kp_index']); }
$kpRows = [];
if ($kp3h) {
    foreach ($kp3h as $r) {
        if (is_array($r) && isset($r['Kp']) && is_numeric($r['Kp'])) {
            $kpRows[] = ['t' => (string) $r['time_tag'], 'kp' => (float) $r['Kp'], 'a' => (float) ($r['a_running'] ?? 0)];
        }
    }
    $kpRows = array_slice($kpRows, -56);
}
$aIdx = $kpRows ? end($kpRows)['a'] : null;
if ($kp === null && $kpRows) { $kp = end($kpRows)['kp']; }

$xrFlux = null; $xrSeries = [];
if ($xr) {
    $i = 0;
    foreach ($xr as $r) {
        if (($r['energy'] ?? '') === '0.1-0.8nm' && isset($r['flux'])) {
            $xrFlux = (float) $r['flux'];
            if ($i++ % 3 === 0) { $xrSeries[] = max(1e-9, (float) $r['flux']); }
        }
    }
}
$windV = $wind ? (int) lastOf($wind)['proton_speed'] : null;
$bz = $mag ? (float) lastOf($mag)['bz_gsm'] : null;
$bt = $mag ? (float) lastOf($mag)['bt'] : null;
$sc = ($scales && isset($scales['0'])) ? $scales['0'] : null;

/* ---------- teksten ---------- */
$T = [
  'nl' => [
    'title' => 'RUIMTEWEER & PROPAGATIE', 'map' => 'DAG, NACHT EN GRIJZE LIJN', 'bands' => 'HF-BANDEN (SCHATTING)',
    'band' => 'BAND', 'day' => 'DAG', 'night' => 'NACHT', 'lv' => ['Slecht', 'Matig', 'Goed'],
    'sfi' => 'ZONNEFLUX', 'ssn' => 'ZONNEVLEKKEN', 'kp' => 'KP-INDEX', 'a' => 'A-INDEX', 'xray' => 'RÖNTGEN',
    'wind' => 'ZONNEWIND', 'bz' => 'BZ / BT', 'scales' => 'NOAA-SCHALEN', 'kp7' => 'KP AFGELOPEN 7 DAGEN',
    'updated' => 'Bijgewerkt', 'src' => 'Data: NOAA SWPC · uitleg op pc1pm.nl', 'dayHere' => 'Nederland: dag', 'nightHere' => 'Nederland: nacht',
    'kpText' => ['rustig', 'rustig', 'rustig', 'licht onrustig', 'onrustig', 'storm (G1)', 'storm (G2)', 'sterke storm (G3)', 'zware storm (G4)', 'extreme storm (G5)'],
    'sfiText' => fn($v) => $v < 80 ? 'laag' : ($v < 110 ? 'gemiddeld' : ($v < 150 ? 'goed' : ($v < 200 ? 'zeer goed' : 'uitstekend'))),
    'summary' => function ($sfi, $kp) {
        $hi = $sfi >= 150 ? 'Hoge banden (15–10 m) staan wijd open' : ($sfi >= 110 ? 'Hoge banden gaan overdag open' : ($sfi >= 85 ? 'Vooral 40–20 m is goed bruikbaar' : 'Lage zonneactiviteit: houd het bij 80–20 m'));
        $geo = $kp >= 5 ? ', maar een geomagnetische storm verstoort de verbindingen.' : ($kp >= 4 ? ', al is het aardmagnetisch veld wat onrustig.' : ' en het aardmagnetisch veld is rustig.');
        return $hi . $geo;
    },
    'normal' => 'normaal', 'elevated' => 'verhoogd', 'fast' => 'snel', 'south' => 'zuidwaarts', 'fav' => 'gunstig',
    'bg' => 'achtergrond', 'flare' => 'zonnevlam!', 'nodata' => 'Geen data beschikbaar',
  ],
  'en' => [
    'title' => 'SPACE WEATHER & PROPAGATION', 'map' => 'DAY, NIGHT AND GREY LINE', 'bands' => 'HF BANDS (ESTIMATE)',
    'band' => 'BAND', 'day' => 'DAY', 'night' => 'NIGHT', 'lv' => ['Poor', 'Fair', 'Good'],
    'sfi' => 'SOLAR FLUX', 'ssn' => 'SUNSPOTS', 'kp' => 'KP INDEX', 'a' => 'A INDEX', 'xray' => 'X-RAY',
    'wind' => 'SOLAR WIND', 'bz' => 'BZ / BT', 'scales' => 'NOAA SCALES', 'kp7' => 'KP LAST 7 DAYS',
    'updated' => 'Updated', 'src' => 'Data: NOAA SWPC · explained at pc1pm.nl', 'dayHere' => 'Netherlands: day', 'nightHere' => 'Netherlands: night',
    'kpText' => ['quiet', 'quiet', 'quiet', 'unsettled', 'active', 'storm (G1)', 'storm (G2)', 'strong storm (G3)', 'severe storm (G4)', 'extreme storm (G5)'],
    'sfiText' => fn($v) => $v < 80 ? 'low' : ($v < 110 ? 'moderate' : ($v < 150 ? 'good' : ($v < 200 ? 'very good' : 'excellent'))),
    'summary' => function ($sfi, $kp) {
        $hi = $sfi >= 150 ? 'The high bands (15–10 m) are wide open' : ($sfi >= 110 ? 'The high bands open up during the day' : ($sfi >= 85 ? '40–20 m is the place to be' : 'Low solar activity: stick to 80–20 m'));
        $geo = $kp >= 5 ? ', but a geomagnetic storm is disturbing propagation.' : ($kp >= 4 ? ', although the geomagnetic field is somewhat unsettled.' : ' and the geomagnetic field is quiet.');
        return $hi . $geo;
    },
    'normal' => 'normal', 'elevated' => 'elevated', 'fast' => 'fast', 'south' => 'southward', 'fav' => 'favourable',
    'bg' => 'background', 'flare' => 'solar flare!', 'nodata' => 'No data available',
  ],
][$lang];

/* ---------- helpers ---------- */
function e($s): string { return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function f1($v): string { return number_format((float) $v, 1, '.', ''); }
function kpLevel(float $kp): string { return $kp >= 5 ? 'bad' : ($kp >= 4 ? 'warn' : 'ok'); }
function xrayClass(?float $flux): string {
    if (!$flux || $flux <= 0) return '–';
    foreach ([['X', 1e-4], ['M', 1e-5], ['C', 1e-6], ['B', 1e-7], ['A', 1e-8]] as [$c, $v]) {
        if ($flux >= $v) return $c . number_format($flux / $v, 1, '.', '');
    }
    return 'A' . number_format($flux / 1e-8, 1, '.', '');
}
function spark(array $vals, float $x, float $y, float $w, float $h, bool $log = false): string {
    $vals = array_values(array_filter($vals, fn($v) => is_finite($v)));
    if (count($vals) < 2) return '';
    if ($log) { $vals = array_map(fn($v) => log10($v), $vals); }
    $min = min($vals); $max = max($vals);
    if ($max == $min) { $max += 1; $min -= 1; }
    $n = count($vals); $pts = [];
    foreach ($vals as $i => $v) {
        $pts[] = [$x + $i * $w / ($n - 1), $y + $h - ($v - $min) * $h / ($max - $min)];
    }
    $line = ''; foreach ($pts as $i => [$px, $py]) { $line .= ($i ? 'L' : 'M') . f1($px) . ' ' . f1($py); }
    $area = $line . 'L' . f1($x + $w) . ' ' . f1($y + $h) . 'L' . f1($x) . ' ' . f1($y + $h) . 'Z';
    [$lx, $ly] = end($pts);
    return '<path d="' . $area . '" fill="#f5a524" fill-opacity=".12"/><path d="' . $line . '" fill="none" stroke="#f5a524" stroke-width="1.5"/>'
         . '<circle cx="' . f1($lx) . '" cy="' . f1($ly) . '" r="2.4" fill="#f5a524"/>';
}

/* ---------- zon & kaart ---------- */
function sunPosition(float $ts): array {
    $rad = M_PI / 180;
    $d = $ts / 86400 - 10957.5;
    $g = (357.529 + 0.98560028 * $d) * $rad;
    $q = 280.459 + 0.98564736 * $d;
    $L = ($q + 1.915 * sin($g) + 0.02 * sin(2 * $g)) * $rad;
    $ecl = (23.439 - 0.00000036 * $d) * $rad;
    $ra = atan2(cos($ecl) * sin($L), cos($L)) / $rad;
    $dec = asin(sin($ecl) * sin($L)) / $rad;
    $gmst = fmod(18.697374558 + 24.06570982441908 * $d, 24);
    $lon = fmod($ra - $gmst * 15 + 540, 360) - 180;
    return [$dec, $lon];
}
function sunAlt(float $lat, float $lon, float $sLat, float $sLon): float {
    $rad = M_PI / 180;
    return asin(sin($lat * $rad) * sin($sLat * $rad) + cos($lat * $rad) * cos($sLat * $rad) * cos(($lon - $sLon) * $rad)) / $rad;
}
/** Nachtgebied (zon lager dan $h graden) als polygon, in kaartcoördinaten. */
function nightPath(float $h, float $sLat, float $sLon, float $mx, float $my, float $mw, float $mh): string {
    $pole = $sLat >= 0 ? -90.0 : 90.0;
    $dir = $pole < 0 ? 0.5 : -0.5;
    $px = fn($lon) => $mx + ($lon + 180) / 360 * $mw;
    $py = fn($lat) => $my + (90 - $lat) / 180 * $mh;
    $d = 'M' . f1($px(-180)) . ' ' . f1($py($pole));
    for ($lon = -180; $lon <= 180; $lon += 2) {
        $lat = $pole;
        while (abs($lat) <= 90 && sunAlt($lat, $lon, $sLat, $sLon) < $h) { $lat += $dir; }
        $lat = max(-90, min(90, $lat));
        $d .= 'L' . f1($px($lon)) . ' ' . f1($py($lat));
    }
    return $d . 'L' . f1($px(180)) . ' ' . f1($py($pole)) . 'Z';
}
function terminatorLine(float $sLat, float $sLon, float $mx, float $my, float $mw, float $mh): string {
    $pole = $sLat >= 0 ? -90.0 : 90.0;
    $dir = $pole < 0 ? 0.5 : -0.5;
    $d = '';
    for ($lon = -180; $lon <= 180; $lon += 2) {
        $lat = $pole;
        while (abs($lat) <= 90 && sunAlt($lat, $lon, $sLat, $sLon) < 0) { $lat += $dir; }
        $lat = max(-90, min(90, $lat));
        $d .= ($d === '' ? 'M' : 'L') . f1($mx + ($lon + 180) / 360 * $mw) . ' ' . f1($my + (90 - $lat) / 180 * $mh);
    }
    return $d;
}

/* ---------- bandschatting (zelfde als JS) ---------- */
function bandConditions(float $sfi, float $kp): array {
    $lvl = fn($n) => max(0, min(2, $n));
    $storm = $kp >= 7 ? 2 : ($kp >= 5 ? 1 : 0);
    $uns = $kp >= 4 ? 1 : 0;
    $rows = [
        ['160 m', 0, $kp <= 2 ? 2 : ($kp <= 4 ? 1 : 0)],
        ['80 m', 1, 2], ['40 m', 2, 2], ['30 m', 2, $sfi >= 100 ? 2 : 1],
        ['20 m', $sfi >= 85 ? 2 : 1, $sfi >= 130 ? 2 : ($sfi >= 95 ? 1 : 0)],
        ['17 m', $sfi >= 110 ? 2 : ($sfi >= 85 ? 1 : 0), $sfi >= 170 ? 1 : 0],
        ['15 m', $sfi >= 125 ? 2 : ($sfi >= 95 ? 1 : 0), $sfi >= 190 ? 1 : 0],
        ['12 m', $sfi >= 145 ? 2 : ($sfi >= 110 ? 1 : 0), 0],
        ['10 m', $sfi >= 160 ? 2 : ($sfi >= 120 ? 1 : 0), 0],
    ];
    return array_map(fn($r) => [$r[0], $lvl($r[1] - $storm), $lvl($r[2] - $storm - $uns)], $rows);
}

/* ---------- opbouw SVG ---------- */
$now = time();
[$sLat, $sLon] = sunPosition((float) $now);
$isDay = sunAlt(HOME_LAT, HOME_LON, $sLat, $sLon) > -0.8;

$MONO = "Consolas, 'DejaVu Sans Mono', Menlo, monospace";
$SANS = "'Segoe UI', 'Helvetica Neue', Arial, sans-serif";
$C = ['bg' => '#151c21', 'tile' => '#0f1418', 'line' => '#2a363e', 'text' => '#e7ecef', 'muted' => '#9aa8b1',
      'accent' => '#f5a524', 'good' => '#3fb950', 'fair' => '#f5a524', 'poor' => '#e5534b'];
$lvlColor = [$C['poor'], $C['fair'], $C['good']];

$out = [];
$H = 640;
$out[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . W . '" height="' . $H . '" viewBox="0 0 ' . W . ' ' . $H . '" role="img" aria-label="' . e($T['title']) . '">';
$out[] = '<rect x=".5" y=".5" width="' . (W - 1) . '" height="' . ($H - 1) . '" rx="14" fill="' . $C['bg'] . '" stroke="' . $C['line'] . '"/>';

// kop
$out[] = '<text x="24" y="34" font-family="' . e($MONO) . '" font-size="13" font-weight="700" letter-spacing="1.5" fill="' . $C['accent'] . '">' . e($T['title']) . '</text>';
$summary = ($sfi !== null && $kp !== null) ? $T['summary']($sfi, $kp) : $T['nodata'];
$out[] = '<text x="24" y="58" font-family="' . e($SANS) . '" font-size="15" fill="' . $C['text'] . '">' . e($summary) . '</text>';
$updTxt = $T['updated'] . ' ' . gmdate('H:i', $now) . ' UTC';
$out[] = '<circle cx="' . (int) (W - 24 - strlen($updTxt) * 6.1 - 10) . '" cy="30" r="4" fill="' . $C['good'] . '"/>';
$out[] = '<text x="' . (W - 24) . '" y="34" text-anchor="end" font-family="' . e($MONO) . '" font-size="11" fill="' . $C['muted'] . '">' . e($T['updated']) . ' ' . gmdate('H:i', $now) . ' UTC</text>';

// kaart
$mx = 24; $my = 92; $mw = 470; $mh = 235;
$land = '';
$svgFile = __DIR__ . '/../img/web/world-land.svg';
if (is_file($svgFile) && preg_match('/ d="([^"]+)"/', (string) file_get_contents($svgFile), $m)) { $land = $m[1]; }
$out[] = '<text x="' . $mx . '" y="' . ($my - 10) . '" font-family="' . e($MONO) . '" font-size="10.5" letter-spacing="1" fill="' . $C['muted'] . '">' . e($T['map']) . '</text>';
$out[] = '<text x="' . ($mx + $mw) . '" y="' . ($my - 10) . '" text-anchor="end" font-family="' . e($SANS) . '" font-size="11.5" font-weight="600" fill="' . $C['accent'] . '">' . e($isDay ? $T['dayHere'] : $T['nightHere']) . '</text>';
$out[] = '<clipPath id="mapclip"><rect x="' . $mx . '" y="' . $my . '" width="' . $mw . '" height="' . $mh . '" rx="8"/></clipPath>';
$out[] = '<g clip-path="url(#mapclip)">';
$out[] = '<rect x="' . $mx . '" y="' . $my . '" width="' . $mw . '" height="' . $mh . '" fill="#18404e"/>';
if ($land) {
    $out[] = '<path transform="translate(' . $mx . ' ' . $my . ') scale(' . ($mw / 360) . ' ' . ($mh / 180) . ')" d="' . $land . '" fill="#2c3b43" stroke="#3b4e58" stroke-width=".3" fill-rule="evenodd"/>';
}
foreach ([[0, .34], [-6, .2], [-12, .17], [-18, .17]] as [$hh, $op]) {
    $out[] = '<path d="' . nightPath((float) $hh, $sLat, $sLon, $mx, $my, $mw, $mh) . '" fill="#060a14" fill-opacity="' . $op . '"/>';
}
$term = terminatorLine($sLat, $sLon, $mx, $my, $mw, $mh);
$out[] = '<path d="' . $term . '" fill="none" stroke="' . $C['accent'] . '" stroke-opacity=".18" stroke-width="12" stroke-linejoin="round"/>';
$out[] = '<path d="' . $term . '" fill="none" stroke="' . $C['accent'] . '" stroke-opacity=".75" stroke-width="2" stroke-linejoin="round"/>';
$sx = $mx + ($sLon + 180) / 360 * $mw; $sy = $my + (90 - $sLat) / 180 * $mh;
$out[] = '<circle cx="' . f1($sx) . '" cy="' . f1($sy) . '" r="9" fill="#ffc83c" fill-opacity=".35"/><circle cx="' . f1($sx) . '" cy="' . f1($sy) . '" r="5" fill="#ffe08a"/>';
$hx = $mx + (HOME_LON + 180) / 360 * $mw; $hy = $my + (90 - HOME_LAT) / 180 * $mh;
$out[] = '<circle cx="' . f1($hx) . '" cy="' . f1($hy) . '" r="7" fill="' . $C['accent'] . '" fill-opacity=".3"/><circle cx="' . f1($hx) . '" cy="' . f1($hy) . '" r="4" fill="' . $C['accent'] . '"/>';
$out[] = '<text x="' . f1($hx + 9) . '" y="' . f1($hy - 5) . '" font-family="' . e($MONO) . '" font-size="10.5" font-weight="700" fill="#fff">PC1PM</text>';
$out[] = '</g>';
$out[] = '<rect x="' . $mx . '" y="' . $my . '" width="' . $mw . '" height="' . $mh . '" rx="8" fill="none" stroke="' . $C['line'] . '"/>';

// bandentabel
$bx = $mx + $mw + 26; $bw = W - 24 - $bx; $by = $my;
$out[] = '<text x="' . $bx . '" y="' . ($by - 10) . '" font-family="' . e($MONO) . '" font-size="10.5" letter-spacing="1" fill="' . $C['muted'] . '">' . e($T['bands']) . '</text>';
$c1 = $bx + 6; $c2 = $bx + $bw * .36; $c3 = $bx + $bw * .70; $pw = $bw * .28;
$nowCol = $isDay ? $c2 : $c3;
$rowH = 23.5;
$out[] = '<rect x="' . f1($nowCol - 6) . '" y="' . $by . '" width="' . f1($pw + 4) . '" height="' . f1($rowH * 10 + 1) . '" rx="6" fill="' . $C['accent'] . '" fill-opacity=".07"/>';
$hy0 = $by + 15;
$out[] = '<text x="' . $c1 . '" y="' . $hy0 . '" font-family="' . e($MONO) . '" font-size="10" letter-spacing="1" fill="' . $C['muted'] . '">' . e($T['band']) . '</text>';
$out[] = '<text x="' . f1($c2 + $pw / 2 - 4) . '" y="' . $hy0 . '" text-anchor="middle" font-family="' . e($MONO) . '" font-size="10" letter-spacing="1" fill="' . ($isDay ? $C['accent'] : $C['muted']) . '">' . e($T['day']) . '</text>';
$out[] = '<text x="' . f1($c3 + $pw / 2 - 4) . '" y="' . $hy0 . '" text-anchor="middle" font-family="' . e($MONO) . '" font-size="10" letter-spacing="1" fill="' . (!$isDay ? $C['accent'] : $C['muted']) . '">' . e($T['night']) . '</text>';
if ($sfi !== null && $kp !== null) {
    foreach (bandConditions($sfi, $kp) as $i => [$band, $dayL, $nightL]) {
        $ry = $by + $rowH * ($i + 1);
        $out[] = '<line x1="' . $bx . '" x2="' . ($bx + $bw) . '" y1="' . f1($ry) . '" y2="' . f1($ry) . '" stroke="' . $C['line'] . '"/>';
        $ty = $ry + 16;
        $out[] = '<text x="' . $c1 . '" y="' . f1($ty) . '" font-family="' . e($MONO) . '" font-size="12.5" font-weight="700" fill="' . $C['text'] . '">' . e($band) . '</text>';
        foreach ([[$c2, $dayL], [$c3, $nightL]] as [$cx, $l]) {
            $out[] = '<rect x="' . f1($cx - 4) . '" y="' . f1($ry + 4) . '" width="' . f1($pw - 4) . '" height="16" rx="8" fill="' . $lvlColor[$l] . '" fill-opacity=".16"/>';
            $out[] = '<text x="' . f1($cx - 4 + ($pw - 4) / 2) . '" y="' . f1($ty) . '" text-anchor="middle" font-family="' . e($SANS) . '" font-size="11" font-weight="700" fill="' . $lvlColor[$l] . '">' . e($T['lv'][$l]) . '</text>';
        }
    }
}

// tegels
$tilesY = $my + $mh + 22; $tw = (W - 48 - 3 * 10) / 4; $th = 74;
$tiles = [];
$tiles[] = [$T['sfi'], $sfi !== null ? (string) (int) round($sfi) : '–', $sfi !== null ? $T['sfiText']($sfi) : '', $C['text'], ''];
$tiles[] = [$T['ssn'], $ssn !== null ? (string) (int) $ssn : '–', '', $C['text'], ''];
$tiles[] = [$T['kp'], $kp !== null ? f1($kp) : '–', $kp !== null ? $T['kpText'][min(9, (int) round($kp))] : '', $kp === null ? $C['text'] : ['ok' => $C['good'], 'warn' => $C['fair'], 'bad' => $C['poor']][kpLevel($kp)], ''];
$tiles[] = [$T['a'], $aIdx !== null ? (string) (int) $aIdx : '–', $aIdx === null ? '' : ($aIdx < 8 ? $T['kpText'][0] : ($aIdx < 16 ? $T['kpText'][3] : ($aIdx < 30 ? $T['kpText'][4] : $T['kpText'][5]))), $C['text'], ''];
$xc = xrayClass($xrFlux);
$tiles[] = [$T['xray'], $xc, ($xc[0] === 'M' || $xc[0] === 'X') ? $T['flare'] : $T['bg'], ($xc[0] === 'M') ? $C['fair'] : (($xc[0] === 'X') ? $C['poor'] : (($xc[0] === 'A' || $xc[0] === 'B') ? $C['good'] : $C['text'])), ''];
$tiles[] = [$T['wind'], $windV !== null ? $windV . ' km/s' : '–', $windV === null ? '' : ($windV > 600 ? $T['fast'] : ($windV > 450 ? $T['elevated'] : $T['normal'])), $C['text'], ''];
$tiles[] = [$T['bz'], $bz !== null ? (($bz > 0 ? '+' : '') . (0 + $bz) . ' / ' . (0 + $bt) . ' nT') : '–', $bz === null ? '' : ($bz < -5 ? $T['south'] : $T['fav']), ($bz !== null && $bz <= -10) ? $C['poor'] : (($bz !== null && $bz < 0) ? $C['fair'] : $C['text']), ''];
$scaleTxt = $sc ? implode('  ', array_map(fn($k) => $k . ((int) ($sc[$k]['Scale'] ?? 0)), ['R', 'S', 'G'])) : '–';
$scaleCol = ($sc && max((int) ($sc['R']['Scale'] ?? 0), (int) ($sc['S']['Scale'] ?? 0), (int) ($sc['G']['Scale'] ?? 0)) > 0) ? $C['poor'] : $C['good'];
$tiles[] = [$T['scales'], $scaleTxt, $lang === 'nl' ? 'radio · straling · geomag.' : 'radio · radiation · geomag.', $scaleCol, ''];

$sparks = [0 => [$sfiSeries, false], 1 => [$ssnSeries, false], 4 => [$xrSeries, true]];
foreach ($tiles as $i => [$label, $val, $sub, $col]) {
    $tx = 24 + ($i % 4) * ($tw + 10); $ty = $tilesY + intdiv($i, 4) * ($th + 10);
    $out[] = '<rect x="' . f1($tx) . '" y="' . f1($ty) . '" width="' . f1($tw) . '" height="' . $th . '" rx="9" fill="' . $C['tile'] . '" stroke="' . $C['line'] . '"/>';
    $out[] = '<text x="' . f1($tx + 12) . '" y="' . f1($ty + 18) . '" font-family="' . e($MONO) . '" font-size="10" letter-spacing="1" fill="' . $C['muted'] . '">' . e($label) . '</text>';
    $out[] = '<text x="' . f1($tx + 12) . '" y="' . f1($ty + 44) . '" font-family="' . e($MONO) . '" font-size="21" font-weight="700" fill="' . $col . '">' . e($val) . '</text>';
    $out[] = '<text x="' . f1($tx + 12) . '" y="' . f1($ty + 63) . '" font-family="' . e($SANS) . '" font-size="11.5" fill="' . $C['muted'] . '">' . e($sub) . '</text>';
    if (isset($sparks[$i])) {
        $out[] = spark($sparks[$i][0], $tx + $tw * .52, $ty + 30, $tw * .42, 30, $sparks[$i][1]);
    }
}

// Kp-grafiek
$ky = $tilesY + 2 * ($th + 10) + 14; $kh = 62; $kx = 24; $kw = W - 48;
$out[] = '<text x="' . $kx . '" y="' . $ky . '" font-family="' . e($MONO) . '" font-size="10.5" letter-spacing="1" fill="' . $C['muted'] . '">' . e($T['kp7']) . '</text>';
$gy = $ky + 8;
foreach ([3, 5, 7] as $k) {
    $yy = $gy + $kh - $k / 9 * $kh;
    $out[] = '<line x1="' . $kx . '" x2="' . ($kx + $kw) . '" y1="' . f1($yy) . '" y2="' . f1($yy) . '" stroke="' . $C['line'] . '" stroke-dasharray="3 4"/>';
}
$n = count($kpRows);
if ($n) {
    $gap = 1.5; $bwid = ($kw - $gap * ($n - 1)) / $n; $lastDay = null;
    foreach ($kpRows as $i => $r) {
        $bh = max(0.15, $r['kp']) / 9 * $kh; $bxx = $kx + $i * ($bwid + $gap);
        $col = ['ok' => $C['good'], 'warn' => $C['fair'], 'bad' => $C['poor']][kpLevel($r['kp'])];
        $out[] = '<rect x="' . f1($bxx) . '" y="' . f1($gy + $kh - $bh) . '" width="' . f1($bwid) . '" height="' . f1($bh) . '" fill="' . $col . '"/>';
        $day = substr($r['t'], 8, 2);
        if ($day !== $lastDay && substr($r['t'], 11, 2) === '00') {
            $out[] = '<text x="' . f1($bxx + 1) . '" y="' . f1($gy + $kh + 12) . '" font-family="' . e($MONO) . '" font-size="9.5" fill="' . $C['muted'] . '">' . (int) $day . '</text>';
        }
        $lastDay = $day;
    }
}

// voet
$out[] = '<text x="24" y="' . ($H - 14) . '" font-family="' . e($SANS) . '" font-size="11" fill="' . $C['muted'] . '">' . e($T['src']) . '</text>';
$out[] = '<text x="' . (W - 24) . '" y="' . ($H - 14) . '" text-anchor="end" font-family="' . e($MONO) . '" font-size="12" font-weight="700" fill="' . $C['accent'] . '">pc1pm.nl</text>';
$out[] = '</svg>';

$svg = implode("\n", $out) . "\n";
@file_put_contents($cacheFile, $svg, LOCK_EX);
echo $svg;
