# ============================================================================
# Daher Phone - icon builder (from the official logo).
#
# Source:  deploy\launcher\logo-source.png  (the full Daher Phone logo)
# The icon uses the red "D + phone" glyph only - the wordmark is unreadable
# at 16-48 px, so cropping the mark is the correct icon treatment.
#
# Outputs: deploy\launcher\app.ico        (launcher EXE + installer + shortcuts)
#          public\assets\favicon.ico      (browser tab)
#          deploy\launcher\icon-preview.png  (256px preview for eyeballing)
# ============================================================================
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$repo = Split-Path -Parent $PSScriptRoot
$srcPath = Join-Path $PSScriptRoot 'launcher\logo-source.png'
if (-not (Test-Path $srcPath)) { throw "Logo source missing: $srcPath" }
$src = [System.Drawing.Bitmap]::FromFile($srcPath)

# The D-glyph region of the logo, as fractions of the full canvas.
$fx = 0.105; $fy = 0.315; $fw = 0.27; $fh = 0.335
$crop = New-Object System.Drawing.Rectangle(
    [int]($src.Width * $fx), [int]($src.Height * $fy),
    [int]($src.Width * $fw), [int]($src.Height * $fh))

# Background color sampled from the logo itself (its own near-black).
$bg = $src.GetPixel(30, 30)

$sizes = @(16, 24, 32, 48, 64, 128, 256)
$pngs = @()

foreach ($size in $sizes) {
    $bmp = New-Object System.Drawing.Bitmap($size, $size)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = 'AntiAlias'
    $g.InterpolationMode = 'HighQualityBicubic'
    $g.PixelOffsetMode = 'HighQuality'
    $g.Clear([System.Drawing.Color]::Transparent)

    # Rounded square in the logo's own black.
    $radius = [math]::Max(2, [int]($size * 0.18))
    $rect = New-Object System.Drawing.Rectangle(0, 0, $size, $size)
    $path = New-Object System.Drawing.Drawing2D.GraphicsPath
    $d = $radius * 2
    $path.AddArc($rect.X, $rect.Y, $d, $d, 180, 90)
    $path.AddArc($rect.Right - $d - 1, $rect.Y, $d, $d, 270, 90)
    $path.AddArc($rect.Right - $d - 1, $rect.Bottom - $d - 1, $d, $d, 0, 90)
    $path.AddArc($rect.X, $rect.Bottom - $d - 1, $d, $d, 90, 90)
    $path.CloseFigure()
    $brush = New-Object System.Drawing.SolidBrush($bg)
    $g.FillPath($brush, $path)

    # The glyph, centered, with breathing room (fills ~76% of the tile).
    $glyphScale = 0.76
    $ratio = $crop.Width / [double]$crop.Height
    if ($ratio -ge 1) {
        $gw = [int]($size * $glyphScale); $gh = [int]($gw / $ratio)
    } else {
        $gh = [int]($size * $glyphScale); $gw = [int]($gh * $ratio)
    }
    $gx = [int](($size - $gw) / 2)
    $gy = [int](($size - $gh) / 2)
    $g.SetClip($path)
    $g.DrawImage($src, (New-Object System.Drawing.Rectangle($gx, $gy, $gw, $gh)),
        $crop, [System.Drawing.GraphicsUnit]::Pixel)
    $g.ResetClip()

    if ($size -eq 256) {
        $bmp.Save((Join-Path $PSScriptRoot 'launcher\icon-preview.png'),
            [System.Drawing.Imaging.ImageFormat]::Png)
    }

    $ms = New-Object System.IO.MemoryStream
    $bmp.Save($ms, [System.Drawing.Imaging.ImageFormat]::Png)
    $pngs += , @{ Size = $size; Bytes = $ms.ToArray() }
    $ms.Dispose(); $g.Dispose(); $bmp.Dispose()
}
$src.Dispose()

# --- pack the .ico (PNG entries; supported since Windows Vista) -----------------
function Write-Ico([string]$path, [array]$images) {
    $fs = [System.IO.File]::Create($path)
    $w = New-Object System.IO.BinaryWriter($fs)
    $w.Write([uint16]0); $w.Write([uint16]1); $w.Write([uint16]$images.Count)
    $offset = 6 + 16 * $images.Count
    foreach ($img in $images) {
        $dim = if ($img.Size -ge 256) { 0 } else { $img.Size }
        $w.Write([byte]$dim); $w.Write([byte]$dim)
        $w.Write([byte]0); $w.Write([byte]0)
        $w.Write([uint16]1); $w.Write([uint16]32)
        $w.Write([uint32]$img.Bytes.Length)
        $w.Write([uint32]$offset)
        $offset += $img.Bytes.Length
    }
    foreach ($img in $images) { $w.Write($img.Bytes) }
    $w.Close(); $fs.Close()
}

$icoLauncher = Join-Path $PSScriptRoot 'launcher\app.ico'
Write-Ico $icoLauncher $pngs
Copy-Item $icoLauncher (Join-Path $repo 'public\assets\favicon.ico') -Force

Write-Output "icon: $icoLauncher ($([math]::Round((Get-Item $icoLauncher).Length/1KB,1)) KB, $($sizes.Count) sizes)"
Write-Output "preview: deploy\launcher\icon-preview.png"
