<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

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

function allocateColor(GdImage $image, string $hex): int
{
    $hex = ltrim($hex, '#');
    return imagecolorallocate(
        $image,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    );
}

function fillVerticalGradient(GdImage $image, int $x, int $y, int $width, int $height, string $startHex, string $endHex): void
{
    $startHex = ltrim($startHex, '#');
    $endHex = ltrim($endHex, '#');
    $sr = hexdec(substr($startHex, 0, 2));
    $sg = hexdec(substr($startHex, 2, 2));
    $sb = hexdec(substr($startHex, 4, 2));
    $er = hexdec(substr($endHex, 0, 2));
    $eg = hexdec(substr($endHex, 2, 2));
    $eb = hexdec(substr($endHex, 4, 2));

    for ($i = 0; $i < $height; $i++) {
        $ratio = $height > 1 ? $i / ($height - 1) : 0;
        $r = (int) round($sr + ($er - $sr) * $ratio);
        $g = (int) round($sg + ($eg - $sg) * $ratio);
        $b = (int) round($sb + ($eb - $sb) * $ratio);
        $color = imagecolorallocate($image, $r, $g, $b);
        imageline($image, $x, $y + $i, $x + $width, $y + $i, $color);
    }
}

function drawCenteredText(GdImage $image, string $text, string $font, int $size, int $centerX, int $baselineY, int $color, float $angle = 0): void
{
    $box = imagettfbbox($size, $angle, $font, $text);
    $textWidth = abs($box[2] - $box[0]);
    $x = (int) round($centerX - ($textWidth / 2));
    imagettftext($image, $size, $angle, $x, $baselineY, $color, $font, $text);
}

function fitTextToWidth(GdImage $image, string $text, string $font, int $maxSize, int $minSize, int $maxWidth): int
{
    for ($size = $maxSize; $size >= $minSize; $size--) {
        $box = imagettfbbox($size, 0, $font, $text);
        $width = abs($box[2] - $box[0]);
        if ($width <= $maxWidth) {
            return $size;
        }
    }

    return $minSize;
}

function imageFromFile(string $path): ?GdImage
{
    $info = @getimagesize($path);
    if ($info === false) {
        return null;
    }

    return match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path) ?: null,
        IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
        default => null,
    };
}

function circularCropPhoto(?string $filename, int $size): ?GdImage
{
    if (!$filename) {
        return null;
    }

    $path = realpath(__DIR__ . '/../uploads/photos/' . $filename);
    if ($path === false || !is_file($path)) {
        return null;
    }

    $src = imageFromFile($path);
    if (!$src) {
        return null;
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    $cropSize = min($srcW, $srcH);
    $srcX = (int) floor(($srcW - $cropSize) / 2);
    $srcY = (int) floor(($srcH - $cropSize) / 2);

    $dest = imagecreatetruecolor($size, $size);
    imagealphablending($dest, false);
    imagesavealpha($dest, true);
    $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
    imagefill($dest, 0, 0, $transparent);

    imagecopyresampled($dest, $src, 0, 0, $srcX, $srcY, $size, $size, $cropSize, $cropSize);
    imagedestroy($src);

    for ($x = 0; $x < $size; $x++) {
        for ($y = 0; $y < $size; $y++) {
            $dx = $x - ($size / 2);
            $dy = $y - ($size / 2);
            if (($dx * $dx) + ($dy * $dy) > pow($size / 2, 2)) {
                imagesetpixel($dest, $x, $y, $transparent);
            }
        }
    }

    return $dest;
}

function placePngWithAlpha(GdImage $canvas, GdImage $overlay, int $x, int $y, int $width, int $height): void
{
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $overlay, $x, $y, 0, 0, $width, $height, imagesx($overlay), imagesy($overlay));
}

function drawFootball(GdImage $image, int $centerX, int $centerY, int $diameter, int $fill, int $line): void
{
    imagefilledellipse($image, $centerX, $centerY, $diameter, $diameter, $fill);
    imageellipse($image, $centerX, $centerY, $diameter, $diameter, $line);

    $r = $diameter / 2;
    $points = [
        $centerX, $centerY - ($r * 0.45),
        $centerX + ($r * 0.28), $centerY - ($r * 0.18),
        $centerX + ($r * 0.18), $centerY + ($r * 0.16),
        $centerX - ($r * 0.18), $centerY + ($r * 0.16),
        $centerX - ($r * 0.28), $centerY - ($r * 0.18),
    ];
    imagefilledpolygon($image, array_map('intval', $points), 5, $line);

    imageline($image, (int) ($centerX - $r * 0.68), (int) ($centerY - $r * 0.1), (int) ($centerX - $r * 0.28), (int) ($centerY - $r * 0.18), $line);
    imageline($image, (int) ($centerX + $r * 0.68), (int) ($centerY - $r * 0.1), (int) ($centerX + $r * 0.28), (int) ($centerY - $r * 0.18), $line);
    imageline($image, (int) ($centerX - $r * 0.55), (int) ($centerY + $r * 0.32), (int) ($centerX - $r * 0.18), (int) ($centerY + $r * 0.16), $line);
    imageline($image, (int) ($centerX + $r * 0.55), (int) ($centerY + $r * 0.32), (int) ($centerX + $r * 0.18), (int) ($centerY + $r * 0.16), $line);
    imageline($image, (int) ($centerX - $r * 0.18), (int) ($centerY + $r * 0.16), (int) ($centerX - $r * 0.02), (int) ($centerY + $r * 0.62), $line);
    imageline($image, (int) ($centerX + $r * 0.18), (int) ($centerY + $r * 0.16), (int) ($centerX + $r * 0.02), (int) ($centerY + $r * 0.62), $line);
}

function drawLaurelSide(GdImage $image, int $startX, int $startY, int $direction, int $color): void
{
    for ($i = 0; $i < 6; $i++) {
        $x = $startX + ($direction * $i * 17);
        $y = $startY - ($i * 20);
        imagefilledellipse($image, $x, $y, 16, 30, $color);
    }
}

function renderMemberCardImage(array $member, array $settings): string
{
    $width = 1000;
    $height = 636;
    $card = imagecreatetruecolor($width, $height);
    imageantialias($card, true);

    fillVerticalGradient($card, 0, 0, $width, $height, '#002766', '#0B4CB3');

    $white = allocateColor($card, '#FFFFFF');
    $dark = allocateColor($card, '#081C3A');
    $red = allocateColor($card, '#D71920');
    $gold = allocateColor($card, '#E6BF54');
    $light = allocateColor($card, '#D9E6FF');
    $greenGlow = allocateColor($card, '#2FA84F');
    $panelBg = allocateColor($card, '#F4F7FB');
    $muted = allocateColor($card, '#7082A7');
    $navy = allocateColor($card, '#0D2F74');

    imagefilledellipse($card, 130, 120, 280, 280, imagecolorallocatealpha($card, 255, 255, 255, 108));
    imagefilledellipse($card, 900, 540, 260, 260, imagecolorallocatealpha($card, 47, 168, 79, 102));

    imagefilledrectangle($card, 0, 140, $width, 225, $red);

    $fontRegular = 'C:/Windows/Fonts/arial.ttf';
    $fontBold = 'C:/Windows/Fonts/arialbd.ttf';

    $clubName = mb_strtoupper($settings['nom_club'] ?: 'MON CLUB');
    $fullName = mb_strtoupper(trim($member['nom'] . ' ' . $member['prenom']));
    $typeLabel = ucfirst((string) $member['type']);
    $numberLabel = (string) $member['numero_membre'];
    $yearLabel = (string) $member['annee_inscription'];
    $initials = mb_strtoupper(mb_substr($member['prenom'], 0, 1) . mb_substr($member['nom'], 0, 1));

    $clubSize = fitTextToWidth($card, $clubName, $fontBold, 34, 24, 620);
    imagettftext($card, $clubSize, 0, 52, 76, $white, $fontBold, $clubName);
    imagettftext($card, 16, 0, 54, 110, $light, $fontRegular, 'Carte officielle de membre du club');

    drawCenteredText($card, $yearLabel, $fontBold, 34, (int) ($width / 2), 198, $white);

    imagefilledrectangle($card, 860, 34, 945, 120, imagecolorallocatealpha($card, 255, 255, 255, 110));
    drawCenteredText($card, 'FC', $fontBold, 30, 902, 86, $white);

    // Decorative laurels
    drawLaurelSide($card, 810, 322, -1, $gold);
    drawLaurelSide($card, 930, 322, 1, $gold);
    drawFootball($card, 870, 330, 108, $white, $dark);

    imagefilledroundedrectangle($card, 55, 465, 255, 518, 25, $white);
    drawCenteredText($card, 'TYPE ' . mb_strtoupper($typeLabel), $fontBold, 18, 155, 501, $navy);
    imagefilledrectangle($card, 55, 528, 255, 576, imagecolorallocatealpha($card, 255, 255, 255, 88));
    drawCenteredText($card, 'MEMBRE ACTIF', $fontBold, 17, 155, 560, $white);

    imagefilledellipse($card, 150, 360, 172, 172, $white);
    imagefilledellipse($card, 150, 360, 158, 158, $greenGlow);
    imagefilledellipse($card, 150, 360, 148, 148, $white);

    $photo = circularCropPhoto($member['photo'], 140);
    if ($photo) {
        placePngWithAlpha($card, $photo, 80, 290, 140, 140);
        imagedestroy($photo);
    } else {
        imagefilledellipse($card, 150, 360, 136, 136, $navy);
        drawCenteredText($card, $initials, $fontBold, 34, 150, 375, $white);
    }

    imagefilledroundedrectangle($card, 265, 270, 760, 500, 24, $panelBg);
    $nameSize = fitTextToWidth($card, $fullName, $fontBold, 28, 18, 445);
    imagettftext($card, $nameSize, 0, 292, 320, $navy, $fontBold, $fullName);
    imagettftext($card, 15, 0, 294, 350, $muted, $fontRegular, 'Membre officiel du club');

    imagettftext($card, 16, 0, 294, 402, $muted, $fontRegular, 'Numero :');
    imagettftext($card, 22, 0, 392, 402, $navy, $fontBold, $numberLabel);

    imagettftext($card, 16, 0, 294, 440, $muted, $fontRegular, 'Type :');
    imagettftext($card, 20, 0, 360, 440, $navy, $fontBold, $typeLabel);

    imagettftext($card, 16, 0, 294, 478, $muted, $fontRegular, 'Valide pour :');
    imagettftext($card, 20, 0, 420, 478, $navy, $fontBold, $yearLabel);

    $targetDir = __DIR__ . '/../tmp/card-cache';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $target = $targetDir . '/member-card-' . $member['id'] . '.png';
    imagepng($card, $target);
    imagedestroy($card);

    return $target;
}

if (!function_exists('imagefilledroundedrectangle')) {
    function imagefilledroundedrectangle(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }
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
$cardImagePath = renderMemberCardImage($member, $settings);
$cardImageUri = localFileUri($cardImagePath);

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
]);
$mpdf->SetAutoPageBreak(false, 0);

$html = '<style>@page { margin: 0; } body { margin: 0; } img { display:block; width:85mm; height:54mm; }</style><img src="' . h($cardImageUri ?? '') . '" alt="Carte membre">';
$mpdf->WriteHTML($html);
$filename = 'carte-membre-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', strtolower($member['numero_membre'])) . '.pdf';
$mpdf->Output($filename, 'I');
