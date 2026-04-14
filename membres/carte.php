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

function createCardThumbnail(?string $filename): ?string
{
    if (!$filename) {
        return null;
    }

    $source = realpath(__DIR__ . '/../uploads/photos/' . $filename);
    if ($source === false || !is_file($source)) {
        return null;
    }

    $info = @getimagesize($source);
    if ($info === false) {
        return null;
    }

    $targetDir = __DIR__ . '/../tmp/card-cache';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $target = $targetDir . '/' . md5($source . 'card-thumb') . '.jpg';
    if (is_file($target)) {
        return str_replace('\\', '/', realpath($target) ?: $target);
    }

    [$width, $height, $type] = $info;
    if ($width <= 0 || $height <= 0) {
        return null;
    }

    $createMap = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];

    if (!isset($createMap[$type]) || !function_exists($createMap[$type])) {
        return str_replace('\\', '/', $source);
    }

    $src = @$createMap[$type]($source);
    if (!$src) {
        return null;
    }

    $size = 280;
    $dst = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    $ratio = min($size / $width, $size / $height);
    $newWidth = (int) round($width * $ratio);
    $newHeight = (int) round($height * $ratio);
    $dstX = (int) floor(($size - $newWidth) / 2);
    $dstY = (int) floor(($size - $newHeight) / 2);

    imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $newWidth, $newHeight, $width, $height);
    imagejpeg($dst, $target, 82);
    imagedestroy($src);
    imagedestroy($dst);

    return str_replace('\\', '/', realpath($target) ?: $target);
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
$mpdf->SetAutoPageBreak(false, 0);

$clubName = mb_strtoupper($settings['nom_club'] ?: 'MON CLUB');
$fullName = mb_strtoupper(trim($member['nom'] . ' ' . $member['prenom']));
$typeLabel = ucfirst((string) $member['type']);
$numberLabel = (string) $member['numero_membre'];
$yearLabel = (string) $member['annee_inscription'];
$photoPath = createCardThumbnail($member['photo']);
$logoPath = localImagePath(__DIR__ . '/../assets/img/logo_club.png');
$ballPath = localImagePath(__DIR__ . '/../assets/img/ballon.png');
$initials = mb_substr($member['prenom'], 0, 1) . mb_substr($member['nom'], 0, 1);

$html = '
<style>
@page { margin: 0; }
body { margin: 0; padding: 0; }
.card {
    width: 85mm;
    height: 54mm;
    overflow: hidden;
    page-break-inside: avoid;
    background: #003087;
    color: #ffffff;
    font-family: dejavusans, sans-serif;
}
.top {
    height: 13mm;
    padding: 3.2mm 4mm 1.5mm;
    background: linear-gradient(135deg, #00235f, #003087 60%, #0d59cf);
}
.top-table {
    width: 100%;
    border-collapse: collapse;
}
.top-left {
    width: 67mm;
    vertical-align: top;
}
.top-right {
    width: 10mm;
    text-align: right;
    vertical-align: top;
}
.club {
    font-size: 10pt;
    font-weight: bold;
    letter-spacing: 0.8px;
    line-height: 1.2;
}
.subtitle {
    margin-top: 1mm;
    font-size: 6pt;
    color: #d9e4ff;
}
.logo {
    width: 8mm;
    height: 8mm;
}
.year {
    height: 7mm;
    line-height: 7mm;
    text-align: center;
    background: #d71920;
    color: #ffffff;
    font-size: 11pt;
    font-weight: bold;
    letter-spacing: 2px;
}
.bottom {
    height: 34mm;
    padding: 3mm 4mm 4mm;
    background:
        radial-gradient(circle at right bottom, rgba(40,167,69,0.18), transparent 18mm),
        linear-gradient(180deg, #0a3b94, #002661);
}
.bottom-table {
    width: 100%;
    border-collapse: collapse;
}
.photo-col {
    width: 20mm;
    vertical-align: top;
}
.info-col {
    width: 39mm;
    vertical-align: top;
    padding-left: 2.5mm;
}
.badge-col {
    width: 14mm;
    vertical-align: top;
    text-align: center;
}
.photo-box {
    width: 17mm;
    height: 19mm;
    border: 0.6mm solid rgba(255,255,255,0.92);
    border-radius: 2.4mm;
    background: rgba(255,255,255,0.12);
    overflow: hidden;
}
.photo {
    width: 17mm;
    height: 19mm;
}
.photo-placeholder {
    width: 17mm;
    height: 19mm;
    line-height: 19mm;
    text-align: center;
    font-size: 13pt;
    font-weight: bold;
    color: #ffffff;
}
.pill {
    margin-top: 2mm;
    padding: 1mm 2mm;
    border-radius: 10mm;
    background: rgba(255,255,255,0.14);
    font-size: 5.8pt;
    font-weight: bold;
    letter-spacing: 0.4px;
    text-align: center;
}
.name {
    font-size: 9pt;
    font-weight: bold;
    line-height: 1.25;
    color: #ffffff;
}
.role {
    margin-top: 1mm;
    font-size: 6.2pt;
    color: #d9e4ff;
}
.info-panel {
    margin-top: 2mm;
    background: rgba(255,255,255,0.96);
    border-radius: 2.2mm;
    padding: 1.8mm 2.2mm;
    color: #16284e;
}
.info-row {
    font-size: 6.2pt;
    line-height: 1.45;
}
.label {
    color: #617397;
}
.value {
    font-weight: bold;
    color: #0e1e40;
}
.crest {
    margin-top: 1mm;
    width: 12mm;
    height: 12mm;
    border-radius: 50%;
    background: rgba(255,255,255,0.92);
    padding: 1.4mm;
}
.type-box {
    margin-top: 2mm;
    background: rgba(255,255,255,0.14);
    border-radius: 2mm;
    padding: 1.2mm 1mm;
    font-size: 5.8pt;
    font-weight: bold;
    letter-spacing: 0.5px;
}
</style>
<div class="card">
    <div class="top">
        <table class="top-table">
            <tr>
                <td class="top-left">
                    <div class="club">' . h($clubName) . '</div>
                    <div class="subtitle">Carte officielle de membre du club</div>
                </td>
                <td class="top-right">' . ($logoPath ? '<img class="logo" src="' . h($logoPath) . '" alt="Logo">' : '') . '</td>
            </tr>
        </table>
    </div>
    <div class="year">' . h($yearLabel) . '</div>
    <div class="bottom">
        <table class="bottom-table">
            <tr>
                <td class="photo-col">
                    <div class="photo-box">' .
                        ($photoPath
                            ? '<img class="photo" src="' . h($photoPath) . '" alt="Photo membre">'
                            : '<div class="photo-placeholder">' . h($initials) . '</div>') . '
                    </div>
                    <div class="pill">MEMBRE ACTIF</div>
                </td>
                <td class="info-col">
                    <div class="name">' . h($fullName) . '</div>
                    <div class="role">Categorie : membre ' . h(strtolower($typeLabel)) . '</div>
                    <div class="info-panel">
                        <div class="info-row"><span class="label">Numero :</span> <span class="value">' . h($numberLabel) . '</span></div>
                        <div class="info-row"><span class="label">Type :</span> <span class="value">' . h($typeLabel) . '</span></div>
                        <div class="info-row"><span class="label">Valide pour :</span> <span class="value">' . h($yearLabel) . '</span></div>
                    </div>
                </td>
                <td class="badge-col">
                    ' . ($ballPath ? '<img class="crest" src="' . h($ballPath) . '" alt="Ballon">' : '') . '
                    <div class="type-box">TYPE<br>' . h(mb_strtoupper($typeLabel)) . '</div>
                </td>
            </tr>
        </table>
    </div>
</div>';

$mpdf->WriteHTML($html);
$filename = 'carte-membre-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', strtolower($member['numero_membre'])) . '.pdf';
$mpdf->Output($filename, 'I');
