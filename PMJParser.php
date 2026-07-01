<?php
class PMJParser {
    private $filePath;
    private $config;
    private $outputDir;

    public function __construct($filePath, $config, $outputDir) {
        $this->filePath = $filePath;
        $this->config = $config;
        $this->outputDir = $outputDir;
    }

    public function parse() {
        $data = file_get_contents($this->filePath);
        $fileSize = strlen($data);
        $fileName = basename($this->filePath);
        $fileBase = pathinfo($fileName, PATHINFO_FILENAME);
        
        $type = $this->getResourceType($fileName);
        $size = $this->config['sprite_sizes'][$type] ?? ['w' => 16, 'h' => 16];
        
        $fileDir = $this->outputDir . '/' . $fileBase;
        if (!file_exists($fileDir)) mkdir($fileDir, 0777, true);
        
        if (substr($data, 0, 4) === "\x89PNG") {
            $img = @imagecreatefromstring($data);
            if ($img) {
                imagepng($img, $fileDir . '/' . $fileBase . '.png');
                imagedestroy($img);
                return ['success' => true, 'count' => 1, 'files' => [$fileBase . '.png']];
            }
        }
        
        $count = unpack('V', substr($data, 0, 4))[1] ?? 0;
        if ($count < 1 || $count > 200) {
            $count = unpack('N', substr($data, 0, 4))[1] ?? 0;
            if ($count < 1 || $count > 200) {
                return ['success' => false, 'error' => "Неверное количество: $count"];
            }
        }
        
        $sizes = [];
        $headerSize = 4 + $count * 4;
        for ($i = 0; $i < $count; $i++) {
            $pos = 4 + $i * 4;
            if ($pos + 3 >= $fileSize) break;
            $sizeVal = unpack('V', substr($data, $pos, 4))[1] ?? 0;
            if ($sizeVal > 0 && $sizeVal < $fileSize) {
                $sizes[] = $sizeVal;
            } else {
                $sizeVal = unpack('N', substr($data, $pos, 4))[1] ?? 0;
                if ($sizeVal > 0 && $sizeVal < $fileSize) {
                    $sizes[] = $sizeVal;
                }
            }
        }
        
        if (empty($sizes)) {
            return ['success' => false, 'error' => 'Нет размеров'];
        }
        
        $offsets = [];
        $currentOffset = $headerSize;
        foreach ($sizes as $sz) {
            $offsets[] = $currentOffset;
            $currentOffset += $sz;
        }
        
        $saved = [];
        for ($i = 0; $i < count($offsets); $i++) {
            $start = $offsets[$i];
            $end = isset($sizes[$i]) ? $start + $sizes[$i] : $fileSize;
            if ($start >= $end || $start < 0 || $end > $fileSize) continue;
            $resData = substr($data, $start, $end - $start);
            if (empty($resData) || strlen($resData) < 4) continue;
            $img = $this->parseImageData($resData, $size['w'], $size['h']);
            if ($img) {
                $filename = sprintf('%s_%03d.png', $fileBase, $i);
                imagepng($img, $fileDir . '/' . $filename);
                $saved[] = $filename;
                imagedestroy($img);
            }
        }
        return ['success' => true, 'count' => count($saved), 'files' => $saved];
    }

    private function getResourceType($fileName) {
        foreach ($this->config['sprite_sizes'] as $key => $size) {
            if (strpos($fileName, $key) !== false) return $key;
        }
        return 'default';
    }

    private function parseImageData($data, $defaultW, $defaultH) {
        $size = strlen($data);
        if ($size < 4) return null;
        if (substr($data, 0, 4) === "\x89PNG") {
            $img = @imagecreatefromstring($data);
            if ($img) return $img;
        }
        if (substr($data, 0, 2) === 'BM') {
            return $this->parseBMP($data);
        }
        $img = $this->parsePalette($data, $defaultW, $defaultH);
        if ($img) return $img;
        $img = $this->parseRLE($data);
        if ($img) return $img;
        return $this->parseRaw($data, $defaultW, $defaultH);
    }

    private function parseBMP($data) {
        $w = unpack('V', substr($data, 18, 4))[1] ?? 0;
        $h = unpack('V', substr($data, 22, 4))[1] ?? 0;
        if ($w <= 0 || $w > 2000 || $h <= 0 || $h > 2000) return null;
        $img = imagecreatetruecolor($w, $h);
        $pixelData = substr($data, 54);
        $rowSize = (int)(strlen($pixelData) / $h);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $pos = $y * $rowSize + $x * 3;
                if ($pos + 2 < strlen($pixelData)) {
                    $b = ord($pixelData[$pos]);
                    $g = ord($pixelData[$pos + 1]);
                    $r = ord($pixelData[$pos + 2]);
                    $color = imagecolorallocate($img, $r, $g, $b);
                    imagesetpixel($img, $x, $h - 1 - $y, $color);
                }
            }
        }
        return $img;
    }

    private function parsePalette($data, $defaultW, $defaultH) {
        $size = strlen($data);
        $palOffset = -1;
        $palSize = 0;
        for ($offset = 0; $offset < min(128, $size - 10); $offset++) {
            $colors = 0;
            for ($i = 0; $i < 256 && $offset + $i * 3 + 2 < $size; $i++) {
                $r = ord($data[$offset + $i * 3]);
                $g = ord($data[$offset + $i * 3 + 1]);
                $b = ord($data[$offset + $i * 3 + 2]);
                if ($r <= 255 && $g <= 255 && $b <= 255) $colors++;
                else break;
            }
            if ($colors >= 16 && $colors > $palSize) {
                $palSize = $colors;
                $palOffset = $offset;
            }
        }
        if ($palOffset < 0) return null;
        
        $palette = [];
        for ($i = 0; $i < $palSize; $i++) {
            $r = ord($data[$palOffset + $i * 3]);
            $g = ord($data[$palOffset + $i * 3 + 1]);
            $b = ord($data[$palOffset + $i * 3 + 2]);
            $palette[] = ($r << 16) | ($g << 8) | $b;
        }
        
        $pixelOffset = $palOffset + $palSize * 3;
        $pixelData = substr($data, $pixelOffset);
        $pixelLen = strlen($pixelData);
        
        $w = $defaultW;
        $h = $defaultH;
        if ($pixelLen > 4) {
            $tw = ord($data[0]);
            $th = ord($data[1]);
            if ($tw > 0 && $tw < 200 && $th > 0 && $th < 200 && $tw * $th <= $pixelLen) {
                $w = $tw;
                $h = $th;
            } else {
                for ($tw = 4; $tw <= 128; $tw++) {
                    for ($th = 4; $th <= 128; $th++) {
                        if ($tw * $th <= $pixelLen && $tw * $th > $pixelLen - 200) {
                            $w = $tw;
                            $h = $th;
                            break 2;
                        }
                    }
                }
            }
        }
        if ($w < 1 || $h < 1 || $w > 2000 || $h > 2000) { $w = 16; $h = 16; }
        
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        
        $gdPalette = [];
        foreach ($palette as $color) {
            $r = ($color >> 16) & 0xFF;
            $g = ($color >> 8) & 0xFF;
            $b = $color & 0xFF;
            $gdPalette[] = imagecolorallocate($img, $r, $g, $b);
        }
        
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $pos = $y * $w + $x;
                if ($pos < $pixelLen) {
                    $idx = ord($pixelData[$pos]);
                    if (isset($gdPalette[$idx])) {
                        imagesetpixel($img, $x, $y, $gdPalette[$idx]);
                    }
                }
            }
        }
        return $img;
    }

    private function parseRLE($data) {
        $size = strlen($data);
        if ($size < 10) return null;
        $w = ord($data[0]);
        $h = ord($data[1]);
        if ($w <= 0 || $w > 200 || $h <= 0 || $h > 200) { $w = 16; $h = 16; }
        
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        
        $palette = [];
        $pos = 2;
        for ($i = 0; $i < 256 && $pos + 2 < $size; $i++) {
            $r = ord($data[$pos]);
            $g = ord($data[$pos + 1]);
            $b = ord($data[$pos + 2]);
            if ($r == 0 && $g == 0 && $b == 0 && $i > 10) break;
            $palette[] = imagecolorallocate($img, $r, $g, $b);
            $pos += 3;
        }
        if (empty($palette)) {
            for ($i = 0; $i < 256; $i++) {
                $palette[] = imagecolorallocate($img, ($i*3)%256, ($i*7)%256, ($i*11)%256);
            }
        }
        
        $pixels = [];
        $maxPixels = $w * $h;
        while ($pos < $size && count($pixels) < $maxPixels) {
            $byte = ord($data[$pos]);
            $pos++;
            if ($byte < 0x80) {
                $count = $byte + 1;
                if ($pos >= $size) break;
                $value = ord($data[$pos]);
                $pos++;
                for ($i = 0; $i < $count && count($pixels) < $maxPixels; $i++) {
                    $pixels[] = $value;
                }
            } else {
                $count = $byte - 0x7F;
                for ($i = 0; $i < $count && $pos < $size && count($pixels) < $maxPixels; $i++) {
                    $pixels[] = ord($data[$pos]);
                    $pos++;
                }
            }
        }
        
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $idx = $y * $w + $x;
                if ($idx < count($pixels)) {
                    $colorIdx = $pixels[$idx] % count($palette);
                    imagesetpixel($img, $x, $y, $palette[$colorIdx]);
                }
            }
        }
        return $img;
    }

    private function parseRaw($data, $defaultW, $defaultH) {
        $size = strlen($data);
        if ($size < 4) return null;
        
        $w = $defaultW;
        $h = $defaultH;
        for ($tw = 4; $tw <= 128; $tw++) {
            for ($th = 4; $th <= 128; $th++) {
                if ($tw * $th <= $size && $tw * $th > $size - 200) {
                    $w = $tw;
                    $h = $th;
                    break 2;
                }
            }
        }
        if ($w < 1 || $h < 1) { $w = 16; $h = 16; }
        
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        
        $palette = [];
        for ($i = 0; $i < 256; $i++) {
            $palette[] = imagecolorallocate($img, ($i*3)%256, ($i*7)%256, ($i*11)%256);
        }
        
        $pixels = array_values(unpack('C*', substr($data, 0, $w * $h)));
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $idx = $y * $w + $x;
                if ($idx < count($pixels)) {
                    $colorIdx = $pixels[$idx] % count($palette);
                    imagesetpixel($img, $x, $y, $palette[$colorIdx]);
                }
            }
        }
        return $img;
    }
}