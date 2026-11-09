#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Combined Fixture Generator
 *
 * 1) Generates 1000×1000 24-bit BMP, JPG, and PNG files in 4 grouping modes:
 *      '1x1', '2x1', '1x2', '2x2'.
 *    Also creates "hacky" variants that differ in 10 pixel positions,
 *    placed at specific byte offsets (outside sample-based hasher ranges).
 *
 * 2) Creates a 1MB text file ("text_original.txt"), plus a hacked variant
 *    ("text_hacked.txt") with 10 single-byte changes.
 *
 * 3) Creates a 1MB WAV file ("wave_original.wav"), plus "wave_hacked.wav".
 *    The WAV is a superposition of three sinusoids (A3=220Hz, C#4~277.18Hz,
 *    E4~329.63Hz) at 44100Hz, 16-bit mono. Again, 10 single-byte changes in
 *    the hacked version at offsets outside typical sample-slice hashing.
 *
 * Usage:
 *   php create_fixtures.php
 *
 * Output:
 *   All generated files are saved to the "Fixtures" directory.
 *
 * Requirements:
 *   - PHP GD extension with imagebmp() support (for the images).
 *   - Enough randomness (random_int) for the hacked offsets.
 */

////////////////////////////////////////////////////////////////////////////////
//                          CONSTANTS & DIRECTORY                             //
////////////////////////////////////////////////////////////////////////////////

define('DS', DIRECTORY_SEPARATOR);
define('TD', rtrim(__DIR__, DS) . DS);
define('FIXTURES_DIR', TD . 'Fixtures' . DS);

// Ensure "Fixtures" directory exists
if (! is_dir(FIXTURES_DIR)) {
    mkdir(FIXTURES_DIR, 0755, true);
}

// For the 1MB text/WAV
define('SIZE_1MB', 1048576);
define('WAVE_HEADER_SIZE', 44); // Standard PCM WAV header (RIFF) is 44 bytes

////////////////////////////////////////////////////////////////////////////////
//                     SHARED "HACK" OFFSET LOGIC (12 OFFSETS)                //
////////////////////////////////////////////////////////////////////////////////

/**
 * We define 12 "raw" offsets (like markers), then skip index 0 and 11,
 * resulting in exactly 10 offsets. Each final offset = rawOffsets[i] + 1024 + 1
 * for i in [1..10].
 *
 * The reason: we mimic the idea of "skipping first and last slices" + "various slices in between".
 */
function getBaseOffsetsFor1MB(): array
{
    // For the 1MB text or WAV. For the 3,000,054-byte BMP, see below.
    return [
        0,                // exclude
        1024,            // + 1025 => ~2,049
        120000,          // ...
        240000,
        360000,
        480000,
        600000,
        720000,
        840000,
        960000,
        1040000,
        (SIZE_1MB - 1),   // exclude
    ];
}

/**
 * Applies the 10 hacks to any binary $data of at least 1,048,576 bytes,
 * using the offsets from getBaseOffsetsFor1MB().
 */
function applyHacks1MB(string $data): string
{
    $baseOffsets = getBaseOffsetsFor1MB();
    for ($i = 1; $i <= 10; $i++) {
        $offset = $baseOffsets[$i] + 1024 + 1;
        if ($offset >= 0 && $offset < strlen($data)) {
            // Overwrite with a random byte
            $data[$offset] = chr(random_int(0, 255));
        }
    }

    return $data;
}

////////////////////////////////////////////////////////////////////////////////
//                         PART (A) IMAGE GENERATION                          //
////////////////////////////////////////////////////////////////////////////////

/**
 * Offsets for the 3,000,054-byte 24-bit BMP (1000×1000).
 * We'll skip index [0] and [11], so we get 10 offsets for the hack.
 */
function getRawOffsetsForBMP(): array
{
    return [
        0,          // exclude
        1024,
        300032,
        599040,
        898048,
        1197056,
        1496064,
        1795072,
        2094080,
        2393088,
        2692096,
        2999030,    // exclude
    ];
}

/**
 * Converts a BMP file offset (0..3,000,053) into (x,y) for a 1000×1000 24-bit BMP
 * with no padding. The pixel data starts at offset 54, each row is 3000 bytes.
 *
 * offset_in_pixel_data = offset - 54
 * pixel_index = floor(offset_in_pixel_data / 3)
 * x = pixel_index % 1000
 * y = floor(pixel_index / 1000)
 *
 * Return null if offset is outside the valid pixel range.
 */
function offsetToXY_BMP(int $offset): ?array
{
    $pixelDataOffset = $offset - 54;
    if ($pixelDataOffset < 0) {
        return null;
    }
    // total pixel bytes = 3,000,000 for a 1000×1000, 24-bit
    if ($pixelDataOffset >= 3000000) {
        return null;
    }
    $pixelIndex = (int)($pixelDataOffset / 3);
    $x = $pixelIndex % 1000;
    $y = (int)($pixelIndex / 1000);

    if ($x < 0 || $x >= 1000 || $y < 0 || $y >= 1000) {
        return null;
    }

    return compact('x', 'y');
}

/**
 * Creates a 1000×1000 TrueColor image for a given block mode, or applies the "hack."
 *
 * @param string    $mode  '1x1','2x1','1x2','2x2'.
 * @param ?\GdImage $img   If provided, we apply the hack instead of creating new.
 */
function createImageByMode(string $mode, ?\GdImage $img = null): \GdImage
{
    // If $img is passed, we do the hack (alter 10 pixels at specific offsets).
    if ($img) {
        if (imagesx($img) !== 1000 || imagesy($img) !== 1000) {
            throw new RuntimeException('Image must be 1000×1000.');
        }

        $rawOffsets = getRawOffsetsForBMP();
        $hackColor = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));

        // For i in 1..10 => offset = rawOffsets[i] + 1024 + 1
        for ($i = 1; $i <= 10; $i++) {
            $offset = $rawOffsets[$i] + 1024 + 1;
            $xy = offsetToXY_BMP($offset);
            if ($xy !== null) {
                imagesetpixel($img, $xy['x'], $xy['y'], $hackColor);
            }
        }

        return $img;
    }

    // Otherwise, create a new 1000×1000 image in the chosen grouping mode
    $img = imagecreatetruecolor(1000, 1000);
    if (! $img) {
        throw new RuntimeException('Could not create GD image.');
    }

    // Decide group size
    switch ($mode) {
        case '1x1':
            $gW = 1;
            $gH = 1;

            break;
        case '2x1':
            $gW = 2;
            $gH = 1;

            break;
        case '1x2':
            $gW = 1;
            $gH = 2;

            break;
        case '2x2':
            $gW = 2;
            $gH = 2;

            break;
        default:
            throw new RuntimeException("Unknown mode: $mode");
    }

    // Fill the image in block patterns
    for ($y = 0; $y < 1000; $y += $gH) {
        for ($x = 0; $x < 1000; $x += $gW) {
            $color = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle($img, $x, $y, $x + $gW - 1, $y + $gH - 1, $color);
        }
    }

    return $img;
}

/**
 * Saves a GD image to .bmp, .jpg, or .png files with standard compression.
 */
function saveImage(\GdImage $img, string $ext, string $basePath): void
{
    $fileName = $basePath . '.' . $ext;
    switch ($ext) {
        case 'bmp':
            imagebmp($img, $fileName);

            break;
        case 'jpg':
            imagejpeg($img, $fileName, 85);

            break;
        case 'png':
            imagepng($img, $fileName, 5);

            break;
        default:
            throw new RuntimeException("Unsupported extension: $ext");
    }
}

/**
 * Generates "normal" + "hacky" images for each of 4 modes ('1x1','2x1','1x2','2x2')
 * in 3 formats: BMP/JPG/PNG. Saves them in FIXTURES_DIR.
 */
function generateImages(): void
{
    if (! extension_loaded('gd')) {
        throw new RuntimeException('GD extension is required for image generation.');
    }
    if (! function_exists('imagebmp')) {
        throw new RuntimeException('imagebmp() support is required for BMP output.');
    }

    $modes = ['1x1','2x1','1x2','2x2'];
    $types = ['bmp','jpg','png'];

    foreach ($modes as $mode) {
        echo "Generating images for mode: {$mode}...\n";
        // Normal
        $imgNormal = createImageByMode($mode, null);
        $basePath = FIXTURES_DIR . "image_{$mode}";

        foreach ($types as $ext) {
            saveImage($imgNormal, $ext, $basePath);
        }

        // Hacky
        echo "Generating hacky images for mode: {$mode}...\n";
        $imgHack = createImageByMode($mode, $imgNormal);
        $basePathHack = FIXTURES_DIR . "image_{$mode}_hacky";

        foreach ($types as $ext) {
            saveImage($imgHack, $ext, $basePathHack);
        }

        imagedestroy($imgNormal);
        imagedestroy($imgHack);
    }
}

////////////////////////////////////////////////////////////////////////////////
//                       PART (B) 1MB TEXT FILE CREATION                      //
////////////////////////////////////////////////////////////////////////////////

function createOneMBText(): void
{
    $originalPath = FIXTURES_DIR . 'text_original.txt';
    $hackedPath = FIXTURES_DIR . 'text_hacked.txt';

    // Build a 1MB ASCII string
    $letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 ";
    $lenLetters = strlen($letters);
    $data = '';
    for ($i = 0; $i < SIZE_1MB; $i++) {
        $data .= $letters[random_int(0, $lenLetters - 1)];
    }

    file_put_contents($originalPath, $data);

    $hacked = applyHacks1MB($data);
    file_put_contents($hackedPath, $hacked);
}

////////////////////////////////////////////////////////////////////////////////
//          PART (C) 1MB WAV FILE WITH A3 CHORD (A3, C#4, E4)                 //
////////////////////////////////////////////////////////////////////////////////

/**
 * Creates a 1MB WAV file at 44100Hz, 16-bit mono, with a chord of
 *   A3 (220Hz), C#4 (~277.18Hz), E4 (~329.63Hz).
 *
 * We'll combine these frequencies by summing their sines at each sample,
 * ensuring we keep total amplitude in a safe range. Then saves "original" + "hacked."
 */
function createOneMBWav(): void
{
    $originalPath = FIXTURES_DIR . 'wave_original.wav';
    $hackedPath = FIXTURES_DIR . 'wave_hacked.wav';

    // total file size = 1MB => 1,048,576 bytes
    // header = 44 bytes => data = 1,048,532 => numSamples = 524,266
    $numSamples = (SIZE_1MB - WAVE_HEADER_SIZE) / 2;
    $sampleRate = 44100;

    // Frequencies for an A major chord (A3~220, C#4~277.18, E4~329.63).
    // We'll sum them with a factor to avoid clipping. Example: each wave at ~1/3 amplitude.
    $frequencies = [220.0, 277.18, 329.63];
    $amplitude = 32767 / 3; // 1/3 to reduce risk of saturation

    // Build WAV header
    $subchunk2Size = $numSamples * 2; // each sample is 2 bytes, mono
    $chunkSize = 36 + $subchunk2Size;

    // "RIFF" + chunkSize + "WAVE" + "fmt " + 16 + PCM=1 + numCh=1 + sampleRate + byteRate + blockAlign + bitsPerSample + "data" + subchunk2Size
    $header = pack('N', 0x52494646);    // "RIFF" (big-endian in memory; we'll store as LE below)
    $header .= pack('V', $chunkSize);    // chunkSize in little-endian
    $header .= pack('N', 0x57415645);    // "WAVE"
    $header .= pack('N', 0x666d7420);    // "fmt "
    $header .= pack('V', 16);           // Subchunk1Size=16 (PCM)
    $header .= pack('v', 1);            // AudioFormat=1
    $header .= pack('v', 1);            // NumChannels=1 (mono)
    $header .= pack('V', $sampleRate);  // SampleRate
    $byteRate = $sampleRate * 1 * 2;    // 44100 * mono * 16-bit => 88200
    $header .= pack('V', $byteRate);
    $blockAlign = 2;
    $header .= pack('v', $blockAlign);
    $header .= pack('v', 16);           // BitsPerSample=16
    $header .= pack('N', 0x64617461);   // "data"
    $header .= pack('V', $subchunk2Size);

    // Generate the chord data
    $samplesBin = '';
    for ($n = 0; $n < $numSamples; $n++) {
        $t = $n / $sampleRate; // time in seconds
        // sum of sin waves
        $sum = 0.0;
        foreach ($frequencies as $freq) {
            $sum += sin(2.0 * M_PI * $freq * $t);
        }
        // scale by amplitude and clamp if needed
        $val = (int)($sum * $amplitude);

        // simple clamp
        if ($val > 32767) {
            $val = 32767;
        } elseif ($val < -32768) {
            $val = -32768;
        }
        $samplesBin .= pack('v', $val); // 16-bit LE
    }

    $wavData = $header . $samplesBin;
    // ensure total length is exactly 1MB
    if (strlen($wavData) < SIZE_1MB) {
        $wavData .= str_repeat("\0", SIZE_1MB - strlen($wavData));
    } elseif (strlen($wavData) > SIZE_1MB) {
        $wavData = substr($wavData, 0, SIZE_1MB);
    }

    // save original
    file_put_contents($originalPath, $wavData);

    // save hacked
    $hacked = applyHacks1MB($wavData);
    file_put_contents($hackedPath, $hacked);
}

////////////////////////////////////////////////////////////////////////////////
//                                   MAIN                                     //
////////////////////////////////////////////////////////////////////////////////

function main(): void
{
    // PART A: Image Generation
    generateImages();

    // PART B: 1MB Text File
    echo "\nGenerating 1MB text file (original + hacked)...\n";
    createOneMBText();

    // PART C: 1MB WAV File with A3 chord
    echo "Generating 1MB WAV file (original + hacked)...\n";
    createOneMBWav();

    echo "\nAll fixture files generated in the 'Fixtures' folder.\n";
}

main();
