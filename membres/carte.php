<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

ini_set('pcre.backtrack_limit', '5000000');

function localImagePath(string $path): ?string
{
    $realPath = realpath($path);
    if ($realPath === false || !is_file($realPath)) {
        return null;
    }

    return str_replace('\\', '/', $realPath);
}

function memberPhotoPath(?string $filename): ?string
{
    if (!$filename) {
        return null;
    }

    return localImagePath(__DIR__ . '/../uploads/photos/' . $filename);
}

$pdo = getPdo();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM membres WHERE id = ?');
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    exit('Membre introuvable.');
}

if ($member['categorie'] !== 'membre') {
    exit('Erreur : la carte membre est reservee aux membres.');
}

$settings = clubSettings();
$tmpDir = __DIR__ . '/../tmp/mpdf';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}

$mpdf = new Mpdf([
    'format' => [85, 54],
    'margin_left' => 0,
    'margin_right' => 0,
    'margin_top' => 0,
    'margin_bottom' => 0,
    'tempDir' => $tmpDir,
    'default_font' => 'dejavusans',
]);

$clubName = mb_strtoupper($settings['nom_club'] ?: 'MON CLUB');
$fullName = mb_strtoupper(trim($member['nom'] . ' ' . $member['prenom']));
$typeLabel = ucfirst((string) $member['type']);
$numberLabel = (string) $member['numero_membre'];
$yearLabel = (string) $member['annee_inscription'];
$photoPath = memberPhotoPath($member['photo']);
$logoPath = localImagePath(__DIR__ . '/../assets/img/logo_club.png');

$ballSvg = <<<'SVG'
<svg width="92" height="92" viewBox="0 0 92 92" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <radialGradient id="g" cx="50%" cy="45%" r="60%">
      <stop offset="0%" stop-color="#ffffff"/>
      <stop offset="100%" stop-color="#dce7ff"/>
    </radialGradient>
  </defs>
  <circle cx="46" cy="46" r="40" fill="url(#g)" stroke="#d6e0f8" stroke-width="4"/>
  <polygon points="46,21 58,29 54,43 38,43 34,29" fill="#0d1b3d"/>
  <polygon points="24,35 35,31 39,44 30,53 18,48" fill="#0d1b3d"/>
  <polygon points="68,35 74,48 62,53 53,44 57,31" fill="#0d1b3d"/>
  <polygon points="32,56 43,48 54,56 49,70 37,70" fill="#0d1b3d"/>
  <path d="M46 21 L34 29 M46 21 L58 29 M18 48 L30 53 M74 48 L62 53 M37 70 L30 53 M49 70 L62 53" stroke="#0d1b3d" stroke-width="3" fill="none" stroke-linecap="round"/>
</svg>
SVG;

$laurelSvg = <<<'SVG'
<svg width="120" height="94" viewBox="0 0 120 94" xmlns="http://www.w3.org/2000/svg">
  <g fill="none" stroke="#d9b44a" stroke-width="3" stroke-linecap="round">
    <path d="M23 81 C7 62, 6 37, 21 15" />
    <path d="M97 81 C113 62, 114 37, 99 15" />
  </g>
  <g fill="#f3ce62">
    <ellipse cx="18" cy="68" rx="8" ry="4" transform="rotate(-56 18 68)"/>
    <ellipse cx="14" cy="56" rx="8" ry="4" transform="rotate(-40 14 56)"/>
    <ellipse cx="13" cy="43" rx="8" ry="4" transform="rotate(-22 13 43)"/>
    <ellipse cx="16" cy="30" rx="8" ry="4" transform="rotate(-8 16 30)"/>
    <ellipse cx="25" cy="19" rx="8" ry="4" transform="rotate(12 25 19)"/>
    <ellipse cx="102" cy="68" rx="8" ry="4" transform="rotate(56 102 68)"/>
    <ellipse cx="106" cy="56" rx="8" ry="4" transform="rotate(40 106 56)"/>
    <ellipse cx="107" cy="43" rx="8" ry="4" transform="rotate(22 107 43)"/>
    <ellipse cx="104" cy="30" rx="8" ry="4" transform="rotate(8 104 30)"/>
    <ellipse cx="95" cy="19" rx="8" ry="4" transform="rotate(-12 95 19)"/>
  </g>
</svg>
SVG;

$styles = '
<style>
body { margin: 0; padding: 0; }
.card {
    width: 85mm;
    height: 54mm;
    position: relative;
    overflow: hidden;
    background: linear-gradient(140deg, #001c55 0%, #003087 45%, #0e5ad4 100%);
    color: #ffffff;
    font-family: dejavusans, sans-serif;
}
.card:before {
    content: "";
    position: absolute;
    inset: -8mm auto auto -12mm;
    width: 54mm;
    height: 54mm;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
}
.card:after {
    content: "";
    position: absolute;
    right: -10mm;
    bottom: -12mm;
    width: 42mm;
    height: 42mm;
    border-radius: 50%;
    background: rgba(40,167,69,0.16);
}
.header {
    position: absolute;
    top: 4mm;
    left: 5mm;
    right: 5mm;
    text-align: center;
    z-index: 3;
}
.club {
    font-size: 10.5pt;
    font-weight: bold;
    letter-spacing: 1px;
}
.sub {
    margin-top: 0.6mm;
    font-size: 6.6pt;
    color: rgba(255,255,255,0.86);
}
.year-band {
    position: absolute;
    top: 15mm;
    left: 0;
    right: 0;
    height: 8.5mm;
    background: linear-gradient(90deg, #b50f22, #db1f32, #b50f22);
    text-align: center;
    line-height: 8.5mm;
    font-size: 12pt;
    font-weight: bold;
    letter-spacing: 2px;
    box-shadow: 0 1mm 3mm rgba(0,0,0,0.24);
    z-index: 3;
}
.photo-shell {
    position: absolute;
    top: 25.5mm;
    left: 5mm;
    width: 18mm;
    height: 18mm;
    border-radius: 4mm;
    background: rgba(255,255,255,0.12);
    border: 0.8mm solid rgba(255,255,255,0.92);
    overflow: hidden;
    z-index: 3;
}
.photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.photo-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(255,255,255,0.18), rgba(255,255,255,0.04));
    color: #ffffff;
    font-size: 16pt;
    font-weight: bold;
    text-align: center;
    line-height: 18mm;
}
.crest {
    position: absolute;
    top: 23.5mm;
    right: 4mm;
    width: 29mm;
    height: 24mm;
    z-index: 2;
}
.ball {
    position: absolute;
    top: 28.5mm;
    right: 11mm;
    width: 14mm;
    height: 14mm;
    z-index: 3;
}
.panel {
    position: absolute;
    left: 25mm;
    right: 4mm;
    bottom: 4mm;
    min-height: 17mm;
    border-radius: 3mm;
    background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(239,244,251,0.98));
    color: #13203b;
    padding: 2.2mm 3mm 2mm;
    box-shadow: 0 1mm 3mm rgba(0,0,0,0.14);
    z-index: 4;
}
.name {
    font-size: 8.8pt;
    font-weight: bold;
    line-height: 1.15;
    color: #0d1b3d;
}
.line {
    margin-top: 1.1mm;
    font-size: 6.8pt;
    color: #40506f;
}
.value {
    color: #0d1b3d;
    font-weight: bold;
}
.type-badge {
    position: absolute;
    left: 5mm;
    bottom: 4.5mm;
    background: rgba(255,255,255,0.16);
    border: 0.4mm solid rgba(255,255,255,0.22);
    border-radius: 999px;
    padding: 1.2mm 3.2mm;
    font-size: 6.8pt;
    font-weight: bold;
    letter-spacing: 0.4px;
    z-index: 4;
}
.member-chip {
    position: absolute;
    left: 5mm;
    top: 11mm;
    background: rgba(255,255,255,0.14);
    border: 0.35mm solid rgba(255,255,255,0.22);
    border-radius: 999px;
    padding: 1mm 3mm;
    font-size: 6.2pt;
    letter-spacing: 0.9px;
    z-index: 4;
}
.logo {
    position: absolute;
    top: 4.2mm;
    right: 5.2mm;
    width: 8mm;
    height: 8mm;
    z-index: 3;
}
</style>
';

$mpdf->WriteHTML($styles, \Mpdf\HTMLParserMode::HEADER_CSS);
$chunks = [
    '<div class="card">',
    $logoPath ? '<img class="logo" src="' . h($logoPath) . '" alt="Logo">' : '',
    '<div class="member-chip">CARTE OFFICIELLE</div>',
    '<div class="header"><div class="club">' . h($clubName) . '</div><div class="sub">Identite membre du club</div></div>',
    '<div class="year-band">' . h($yearLabel) . '</div>',
    '<div class="crest">' . $laurelSvg . '</div>',
    '<div class="ball">' . $ballSvg . '</div>',
    '<div class="photo-shell">' .
        ($photoPath
            ? '<img class="photo" src="' . h($photoPath) . '" alt="Photo membre">'
            : '<div class="photo-placeholder">' . h(mb_substr($member['prenom'], 0, 1) . mb_substr($member['nom'], 0, 1)) . '</div>') .
    '</div>',
    '<div class="panel"><div class="name">' . h($fullName) . '</div><div class="line">Numero : <span class="value">' . h($numberLabel) . '</span></div><div class="line">Statut : <span class="value">Membre ' . h($typeLabel) . '</span></div></div>',
    '<div class="type-badge">TYPE ' . h(mb_strtoupper($typeLabel)) . '</div>',
    '</div>',
];

foreach ($chunks as $chunk) {
    if ($chunk !== '') {
        $mpdf->WriteHTML($chunk, \Mpdf\HTMLParserMode::HTML_BODY);
    }
}

$filename = 'carte-membre-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', strtolower($member['numero_membre'])) . '.pdf';
$mpdf->Output($filename, 'I');
