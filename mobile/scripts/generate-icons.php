<?php

/**
 * Genera los íconos de la PWA y de Android y las pantallas de inicio a partir del diseño
 * provisional del logo (PNG puro, sin GD ni dependencias).
 *
 * Este script NO toca src/assets/logo.png ni web/public/img/logo.png (ahí va tu logo real).
 * Para íconos con el logo real, reemplaza los PNG de public/icons y de android/app/src/main/res/mipmap-*
 * (por ejemplo con https://icon.kitchen) en lugar de volver a correr este script.
 */
const SLATE = "\x2E\x3B\x44\xFF";
const YELLOW = "\xE8\xC5\x31\xFF";
const WHITE = "\xFF\xFF\xFF\xFF";
const CLEAR = "\x00\x00\x00\x00";

/**
 * Color del logo en un punto de un lienzo lógico de 512×512.
 * $bleed: fondo pizarra en todo el cuadro (íconos "maskable" y adaptables).
 */
function logoPixel(float $px, float $py, bool $bleed): ?string
{
    $c = 256;
    $d = sqrt(($px - $c) ** 2 + ($py - $c) ** 2);
    if ($d > 250) {
        return $bleed ? SLATE : null;
    }
    if ($d > 226) {
        return YELLOW;
    }
    $eye = sqrt(($px - ($c - 40)) ** 2 + ($py - ($c - 70)) ** 2) < 16 || sqrt(($px - ($c + 40)) ** 2 + ($py - ($c - 70)) ** 2) < 16;
    $beak = abs($px - $c) < 26 - ($py - ($c - 30)) * 0.9 && $py > $c - 40 && $py < $c - 2;
    if ($eye) {
        return SLATE;
    }
    if ($beak) {
        return YELLOW;
    }
    $bx = ($px - $c) / 120;
    $by = ($py - $c - 40) / 150;

    return $bx * $bx + $by * $by < 1 ? WHITE : SLATE;
}

/**
 * PNG de $w×$h con el logo centrado ocupando $scale del lado menor.
 * $background: color de fondo (null = transparente).
 */
function render(int $w, int $h, float $scale, ?string $background, bool $bleed = false): string
{
    $logo = min($w, $h) * $scale;
    $ox = ($w - $logo) / 2;
    $oy = ($h - $logo) / 2;
    $rows = '';
    for ($y = 0; $y < $h; $y++) {
        $rows .= "\0";
        for ($x = 0; $x < $w; $x++) {
            $lx = ($x + 0.5 - $ox) * 512 / $logo;
            $ly = ($y + 0.5 - $oy) * 512 / $logo;
            $inside = $lx >= 0 && $lx < 512 && $ly >= 0 && $ly < 512;
            $color = $inside ? logoPixel($lx, $ly, $bleed) : null;
            $rows .= $color ?? $background ?? CLEAR;
        }
    }
    $chunk = fn ($t, $d) => pack('N', strlen($d)).$t.$d.pack('N', crc32($t.$d));

    return "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 6, 0, 0, 0))
        .$chunk('IDAT', gzcompress($rows, 9))
        .$chunk('IEND', '');
}

function pngSize(string $file): array
{
    $data = unpack('Nw/Nh', substr(file_get_contents($file), 16, 8));

    return [$data['w'], $data['h']];
}

$root = dirname(__DIR__);

// PWA
@mkdir("$root/public/icons", 0777, true);
foreach ([72, 96, 128, 144, 152, 192, 384, 512] as $s) {
    file_put_contents("$root/public/icons/icon-{$s}x{$s}.png", render($s, $s, 1.0, null));
}
file_put_contents("$root/public/icons/maskable-192x192.png", render(192, 192, 1.0, SLATE, true));
file_put_contents("$root/public/icons/maskable-512x512.png", render(512, 512, 1.0, SLATE, true));
echo "PWA: public/icons\n";

// Android (solo si ya existe el proyecto nativo: npx cap add android)
$res = "$root/android/app/src/main/res";
if (is_dir($res)) {
    $densities = ['mdpi' => 1, 'hdpi' => 1.5, 'xhdpi' => 2, 'xxhdpi' => 3, 'xxxhdpi' => 4];
    foreach ($densities as $name => $f) {
        $dir = "$res/mipmap-$name";
        @mkdir($dir, 0777, true);
        $legacy = (int) round(48 * $f);
        $adaptive = (int) round(108 * $f);
        file_put_contents("$dir/ic_launcher.png", render($legacy, $legacy, 1.0, null));
        file_put_contents("$dir/ic_launcher_round.png", render($legacy, $legacy, 1.0, null));
        // Capa frontal del ícono adaptable: el logo dentro de la zona segura (66 de 108 dp).
        file_put_contents("$dir/ic_launcher_foreground.png", render($adaptive, $adaptive, 0.6, null));
    }
    file_put_contents(
        "$res/values/ic_launcher_background.xml",
        "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<resources>\n    <color name=\"ic_launcher_background\">#2E3B44</color>\n</resources>\n",
    );

    // Pantallas de inicio: se respeta el tamaño de cada splash.png que trae la plantilla.
    foreach (glob("$res/drawable*/splash.png") as $splash) {
        [$w, $h] = pngSize($splash);
        file_put_contents($splash, render($w, $h, 0.35, SLATE));
    }
    echo "Android: íconos (mipmap-*) y pantallas de inicio (drawable*/splash.png)\n";
}
