<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

ini_set('pcre.backtrack_limit', '5000000');

function localFileUri(string $path): ?string
{
    $realPath = realpath($path);
    if ($realPath === false || !is_file($realPath)) {
        return null;
    }

    $normalized = str_replace('\\', '/', $realPath);
    if (!str_starts_with($normalized, '/')) {
        $normalized = '/' . $normalized;
    }

    return 'file://' . $normalized;
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

    $target = $targetDir . '/' . md5($source . 'card-thumb-v2') . '.jpg';
    if (is_file($target)) {
        return localFileUri($target);
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
        return localFileUri($source);
    }

    $src = @$createMap[$type]($source);
    if (!$src) {
        return null;
    }

    $size = 220;
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

    return localFileUri($target);
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
$photoUri = createCardThumbnail($member['photo']);
$logoUri = localFileUri(__DIR__ . '/../assets/img/logo_club.png');
$ballUri = localFileUri(__DIR__ . '/../assets/img/ballon.png');
$initials = mb_strtoupper(mb_substr($member['prenom'], 0, 1) . mb_substr($member['nom'], 0, 1));

$html = '
<style>
@page { margin: 0; }
body { margin: 0; padding: 0; }
table { border-collapse: collapse; border-spacing: 0; }
.card {
    width: 85mm;
    height: 54mm;
    table-layout: fixed;
    overflow: hidden;
    background: #003087;
    color: #ffffff;
    font-family: dejavusans, sans-serif;
}
.top {
    height: 14mm;
    background: #0b3d96;
}
.year {
    height: 7mm;
    background: #dc1c24;
    color: #ffffff;
    font-weight: bold;
    font-size: 11pt;
    letter-spacing: 2px;
    text-align: center;
}
.bottom {
    height: 33mm;
    background: #103f98;
}
.pad-x { padding-left: 4mm; padding-right: 4mm; }
.club {
    font-size: 10pt;
    font-weight: bold;
    letter-spacing: 0.7px;
    color: #ffffff;
}
.subtitle {
    font-size: 6pt;
    color: #d7e4ff;
}
.logo {
    width: 7mm;
    height: 7mm;
}
.photo-box {
    width: 17mm;
    height: 19mm;
    border: 0.5mm solid #ffffff;
    border-radius: 2mm;
    background: #2957ac;
    text-align: center;
}
.photo {
    width: 17mm;
    height: 19mm;
}
.photo-placeholder {
    width: 17mm;
    height: 19mm;
    line-height: 19mm;
    color: #ffffff;
    font-size: 13pt;
    font-weight: bold;
}
.status {
    margin-top: 1.5mm;
    display: inline-block;
    padding: 0.8mm 1.8mm;
    background: #ffffff;
    color: #0d2f74;
    font-size: 5.8pt;
    font-weight: bold;
    border-radius: 8mm;
}
.name {
    font-size: 9.2pt;
    font-weight: bold;
    color: #ffffff;
    line-height: 1.2;
}
.role {
    margin-top: 0.8mm;
    font-size: 6pt;
    color: #d7e4ff;
}
.info-panel {
    margin-top: 2mm;
    background: #ffffff;
    border-radius: 2mm;
    padding: 1.6mm 2mm;
    color: #11295f;
}
.info-line {
    font-size: 6.2pt;
    line-height: 1.45;
}
.label { color: #6a7ea8; }
.value { font-weight: bold; color: #11295f; }
.side {
    text-align: center;
}
.ball {
    width: 11mm;
    height: 11mm;
    background: #ffffff;
    border-radius: 50%;
    padding: 1.3mm;
}
.type-box {
    margin-top: 2mm;
    background: rgba(255,255,255,0.16);
    border: 0.35mm solid rgba(255,255,255,0.24);
    border-radius: 2mm;
    padding: 1mm 0.5mm;
    font-size: 5.8pt;
    font-weight: bold;
    line-height: 1.2;
}
</style>

<table class="card">
    <tr>
        <td class="top pad-x">
            <table width="100%">
                <tr>
                    <td width="88%" valign="top">
                        <div class="club">' . h($clubName) . '</div>
                        <div class="subtitle">Carte officielle de membre du club</div>
                    </td>
                    <td width="12%" align="right" valign="top">
                        ' . ($logoUri ? '<img src="' . h($logoUri) . '" class="logo" alt="Logo">' : '') . '
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="year">' . h($yearLabel) . '</td>
    </tr>
    <tr>
        <td class="bottom pad-x">
            <table width="100%">
                <tr>
                    <td width="26%" valign="top">
                        <div class="photo-box">
                            ' . ($photoUri
                                ? '<img src="' . h($photoUri) . '" class="photo" alt="Photo">'
                                : '<div class="photo-placeholder">' . h($initials) . '</div>') . '
                        </div>
                        <div class="status">MEMBRE ACTIF</div>
                    </td>
                    <td width="52%" valign="top">
                        <div class="name">' . h($fullName) . '</div>
                        <div class="role">Categorie : membre ' . h(strtolower($typeLabel)) . '</div>
                        <div class="info-panel">
                            <div class="info-line"><span class="label">Numero :</span> <span class="value">' . h($numberLabel) . '</span></div>
                            <div class="info-line"><span class="label">Type :</span> <span class="value">' . h($typeLabel) . '</span></div>
                            <div class="info-line"><span class="label">Valide pour :</span> <span class="value">' . h($yearLabel) . '</span></div>
                        </div>
                    </td>
                    <td width="22%" valign="top" class="side">
                        ' . ($ballUri ? '<img src="' . h($ballUri) . '" class="ball" alt="Ballon">' : '') . '
                        <div class="type-box">TYPE<br>' . h(mb_strtoupper($typeLabel)) . '</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>';

$mpdf->WriteHTML($html);
$filename = 'carte-membre-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', strtolower($member['numero_membre'])) . '.pdf';
$mpdf->Output($filename, 'I');
