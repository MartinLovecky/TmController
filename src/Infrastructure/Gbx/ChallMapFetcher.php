<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure\Gbx;

use Yuhzel\TmController\Infrastructure\Gbx\BaseFetcher;

class ChallMapFetcher extends BaseFetcher
{
    public const int IMAGE_FLIP_HORIZONTAL = 1;
    public const int IMAGE_FLIP_VERTICAL   = 2;
    public const int IMAGE_FLIP_BOTH       = 3;
    public int $headerVersn = 0;
    public int $bronzeTime = 0;
    public int $silverTime = 0;
    public int $goldTime = 0;
    public int $authorTime = 0;
    public int $cost = 0;
    public bool $multiLap = false;
    public int $type = 0;
    public string $typeName = '';
    public int $authorScore = 0;
    public bool $simpleEdit = false;
    public bool $ghostBlocks = false;
    public int $nbChecks = 0;
    public int $nbLaps = 0;
    public string $UId = '';
    public string $envir = '';
    public string $author = '';
    public string $name = '';
    public int $kind = 0;
    public string $kindName = '';
    public string $password = '';
    public string $mood = '';
    public string $envirBg = '';
    public string $authorBg = '';
    public string $mapType = '';
    public string $mapStyle = '';
    public int $lightmap = 0;
    public string $titleUid = '';
    public string $xmlVer = '';
    public string $exeVer = '';
    public string $exeBld = '';
    public int $validated = 0;
    public string $songFile = '';
    public string $songUrl = '';
    public string $modName = '';
    public string $modFile = '';
    public string $modUrl = '';
    public string $vehicle = '';
    public int $thumbLen = 0;
    public string $thumbnail = '';
    public string $comment = '';
    public int $headerEnd = 0;
    public bool $tnImage = false;
    public bool $debug = false;

    public function __construct()
    {
        $this->setError('GBX map error: ');
    }

    public function setXml(bool $set): void
    {
        $this->parseXml = $set;
    }

    public function setImage(bool $set): void
    {
        $this->tnImage = $set;
    }

    public function setDebug(bool $set): void
    {
        $this->debug = $set;
    }

    public function processFile(string $filename): void
    {
        $this->loadGBXdata($filename);

        $this->processGBX();
    }

    public function processData(string $gbxdata): void
    {
        $this->storeGBXdata($gbxdata);

        $this->processGBX();
    }

    private function processGBX(): void
    {
        // supported challenge/map class IDs
        $challclasses = [
            parent::GBX_CHALLENGE_TMF,
            parent::GBX_CHALLENGE_TM
        ];
        $headerSize = $this->checkHeader($challclasses);

        if ($headerSize === 0) {
            $this->errorOut('No GBX header block', 8);
        }

        $headerStart = $headerEnd = $this->getGBXptr();
        $chunksList = $this->getChunksList($headerSize, $this->getChunks());

        $chunkProcessors = [
            'Info' => 'getInfoChunk',
            'String' => 'getStringChunk',
            'Version' => 'getVersionChunk',
            'XML' => 'getXMLChunk',
            'Thumbnl' => 'getThumbnlChunk',
            'Author' => 'getAuthorChunk',
        ];

        foreach ($chunkProcessors as $chunkName => $method) {
            if (isset($chunksList[$chunkName])) {
                $this->$method($chunksList);
                $this->updateHeaderEnd();
            }
        }

        if ($headerSize != $this->headerEnd - $headerStart) {
            $this->errorOut(sprintf(
                'Header size mismatch: %d <> %d',
                $headerSize,
                $this->headerEnd - $headerStart
            ), 16);
        }

        if ($this->parseXml) {
            $this->parseXmlData();
        }

        $this->clearGBXdata();
    }

    private function updateHeaderEnd(): void
    {
        $this->headerEnd = max($this->headerEnd, $this->getGBXptr());
    }

    private function parseXmlData(): void
    {
        // Check if xmlParsed has the expected structure and set properties accordingly
        if (isset($this->xmlParsed['HEADER'])) {
            $this->xmlVer = $this->xmlParsed['HEADER']['VERSION'] ?? $this->xmlVer;
            $this->exeVer = $this->xmlParsed['HEADER']['EXEVER'] ?? $this->exeVer;
            $this->exeBld = $this->xmlParsed['HEADER']['EXEBUILD'] ?? $this->exeBld;
            $this->lightmap = $this->xmlParsed['HEADER']['LIGHTMAP'] ?? $this->lightmap;
        }

        // Check if xmlParsed has IDENT section and set properties accordingly
        if (isset($this->xmlParsed['IDENT'])) {
            $this->authorZone = $this->xmlParsed['IDENT']['AUTHORZONE'] ?? $this->authorZone;
        }

        // Check if xmlParsed has DESC section and set properties accordingly
        if (isset($this->xmlParsed['DESC'])) {
            $this->envir = $this->xmlParsed['DESC']['ENVIR'] ?? $this->envir;
            $this->nbLaps = $this->xmlParsed['DESC']['NBLAPS'] ?? $this->nbLaps;
            $this->validated = $this->xmlParsed['DESC']['VALIDATED'] ?? $this->validated;
            $this->modName = $this->xmlParsed['DESC']['MOD'] ?? $this->modName;
        }

        // Check if xmlParsed has PLAYERMODEL section and set vehicle accordingly
        if (isset($this->xmlParsed['PLAYERMODEL'])) {
            $this->vehicle = $this->xmlParsed['PLAYERMODEL']['ID'] ?? $this->vehicle;
        }

        // Extract optional song & mod filenames from DEPS section
        if (isset($this->xmlParsed['DEPS'])) {
            foreach ($this->xmlParsed['DEPS'] as $dep) {
                if (str_contains($dep['FILE'], 'ChallengeMusics\\')) {
                    $this->songFile = $dep['FILE'];
                    $this->songUrl = $dep['URL'] ?? $this->songUrl;
                } elseif (preg_match('/.+\\\\Mod\\\\.+/', $dep['FILE'])) {
                    $this->modFile = $dep['FILE'];
                    $this->modUrl = $dep['URL'] ?? $this->modUrl;
                }
            }
        }

        // Optional: Log the parsed data for debugging
        $this->debugLog('Parsed XML Data: ' . print_r($this->xmlParsed, true));
    }

    protected function getChunks(): array
    {
        return [
            0x03043002 => 'Info',     // TM, MP
            0x24003002 => 'Info',     // TM
            0x03043003 => 'String',   // TM, MP
            0x24003003 => 'String',   // TM
            0x03043004 => 'Version',  // TM, MP
            0x24003004 => 'Version',  // TM
            0x03043005 => 'XML',      // TM, MP
            0x24003005 => 'XML',      // TM
            0x03043007 => 'Thumbnl',  // TM, MP
            0x24003007 => 'Thumbnl',  // TM
            0x03043008 => 'Author',   // MP
        ];
    }

    protected function getInfoChunk(array $chunksList): void
    {
        if (!isset($chunksList['Info'])) {
            return;
        }

        $this->initChunk($chunksList['Info']['off']);
        $version = $this->readInt8();
        $this->debugLog('GBX Info chunk version: ' . $version);

        if ($version < 3) {
            $this->UId = $this->readLookbackString();
            $this->envir  = $this->readLookbackString();
            $this->author = $this->readLookbackString();
            $this->name = $this->stripBOM($this->readString());
        }

        $this->moveGBXptr(4);  // skip bool 0

        if ($version >= 1) {
            $this->bronzeTime = $this->readInt32();
            $this->silverTime = $this->readInt32();
            $this->goldTime = $this->readInt32();
            $this->authorTime = $this->readInt32();
        }
        if ($version == 2) {
            $this->moveGBXptr(1);  // skip unknown byte
        }

        if ($version >= 4) {
            $this->cost = $this->readInt32();
        }

        if ($version >= 5) {
            $this->multiLap = (bool)$this->readInt32();
        }

        if ($version == 6) {
            $this->moveGBXptr(4);  // skip unknown bool
        }

        if ($version >= 7) {
            $this->type = $this->readInt32();
            $this->typeName = match ($this->type) {
                0 => 'Race',
                1 => 'Platform',
                2 => 'Puzzle',
                3 => 'Crazy',
                4 => 'Shortcut',
                5 => 'Stunts',
                6 => 'Script',
                default => 'UNKNOWN',
            };
        }

        if ($version >= 9) {
            $this->moveGBXptr(4);  // skip int32 0
        }

        if ($version >= 10) {
            $this->authorScore = $this->readInt32();
        }

        if ($version >= 11) {
            $editorMode = $this->readInt32();
            $this->simpleEdit = (bool)($editorMode & 1);
            $this->ghostBlocks = (bool)($editorMode & 2);
        }

        if ($version >= 12) {
            $this->moveGBXptr(4);  // skip bool 0
        }

        if ($version >= 13) {
            $this->nbChecks = $this->readInt32();
            $this->nbLaps = $this->readInt32();
        }
    }

    protected function getStringChunk(array $chunksList): void
    {
        if (!isset($chunksList['String'])) {
            return;
        }

        $this->initChunk($chunksList['String']['off']);
        $version = $this->readInt8();
        $this->debugLog('GBX String chunk version: ' . $version);

        $this->UId = $this->readLookbackString();
        $this->envir  = $this->readLookbackString();
        $this->author = $this->readLookbackString();
        $this->name = $this->stripBOM($this->readString());
        $this->kind = $this->readInt8();

        $this->kindName = match ($this->kind) {
            0 => '(internal)EndMarker',
            1 => '(old)Campaign',
            2 => '(old)Puzzle',
            3 => '(old)Retro',
            4 => '(old)TimeAttack',
            5 => '(old)Rounds',
            6 => 'InProgress',
            7 => 'Campaign',
            8 => 'Multi',
            9 => 'Solo',
            10 => 'Site',
            11 => 'SoloNadeo',
            12 => 'MultiNadeo',
            default => 'UNKNOWN',
        };

        if ($version >= 1) {
            $this->moveGBXptr(4);  // skip locked

            $this->password = $this->readString();
        }

        if ($version >= 2) {
            $this->mood = preg_replace('/^([A-Za-z]+)\d*/', '\1', $this->readLookbackString());
            $this->envirBg  = $this->readLookbackString();
            $this->authorBg = $this->readLookbackString();
        }
        if ($version >= 3) {
            $this->moveGBXptr(8);  // skip mapOrigin
        }

        if ($version >= 4) {
            $this->moveGBXptr(8);  // skip mapTarget
        }

        if ($version >= 5) {
            $this->moveGBXptr(16);  // skip unknown int128
        }

        if ($version >= 6) {
            $this->mapType  = $this->readString();
            $this->mapStyle = $this->readString();
        }

        if ($version <= 8) {
            $this->moveGBXptr(4);  // skip unknown bool
        }

        if ($version >= 8) {
            $this->moveGBXptr(8);  // skip lightmapCacheUID
        }

        if ($version >= 9) {
            $this->lightmap = $this->readInt8();
        }

        if ($version >= 11) {
            $this->titleUid = $this->readLookbackString();
        }
    }

    protected function getVersionChunk(array $chunksList): void
    {
        if (!isset($chunksList['Version'])) {
            return;
        }

        $this->initChunk($chunksList['Version']['off']);
        $this->headerVersn = $this->readInt32();
        $this->debugLog("GBX Version chunk: Version {$this->exeVer}, Build {$this->exeBld}");
    }

    protected function getThumbnlChunk(array $chunksList): void
    {
        if (!isset($chunksList['Thumbnl'])) {
            return;
        }

        $this->initChunk($chunksList['Thumbnl']['off']);
        $version = $this->readInt32();
        $this->debugLog('GBX Thumbnail chunk version: ' . $version);

        if ($version == 1) {
            $thumbSize = $this->readInt32();
            $this->debugLog(sprintf(
                'GBX Thumbnail size: %d (%.1f KB)',
                $thumbSize,
                $thumbSize / 1024
            ));

            $this->moveGBXptr(strlen('<Thumbnail.jpg>'));
            $this->thumbnail = $this->readData($thumbSize);
            $this->thumbLen = strlen($this->thumbnail);
            $this->moveGBXptr(strlen('</Thumbnail.jpg>'));
            $this->moveGBXptr(strlen('<Comments>'));
            $this->comment = $this->stripBOM($this->readString());
            $this->moveGBXptr(strlen('</Comments>'));

            // return extracted thumbnail image?
            if ($this->tnImage && $this->thumbLen > 0) {
                // Check for GD/JPEG libraries
                if (function_exists('imagecreatefromjpeg') && function_exists('imagecopyresampled')) {
                    // Flip thumbnail via temporary file
                    $tmp = tempnam(sys_get_temp_dir(), 'gbxflip');
                    if ($tmp !== false && @file_put_contents($tmp, $this->thumbnail) !== false) {
                        if ($tn = @imagecreatefromjpeg($tmp)) {
                            $flippedTn = $this->imageFlip($tn, self::IMAGE_FLIP_HORIZONTAL);
                            if ($flippedTn && @imagejpeg($flippedTn, $tmp)) {
                                $newThumbnail = @file_get_contents($tmp);
                                if ($newThumbnail !== false) {
                                    $this->thumbnail = $newThumbnail;
                                }
                            }
                        }
                        unlink($tmp);
                    }
                }
            } else {
                $this->thumbnail = '';
            }
        }
    }

    private function imageFlip($imgsrc, int $dir)
    {
        $width      = imagesx($imgsrc);
        $height     = imagesy($imgsrc);
        $srcX      = 0;
        $srcY      = 0;
        $srcWidth  = $width;
        $srcHeight = $height;

        switch ((int)$dir) {
            case self::IMAGE_FLIP_HORIZONTAL:
                $srcY      =  $height;
                $srcHeight = -$height;
                break;
            case self::IMAGE_FLIP_VERTICAL:
                $srcX      =  $width;
                $srcWidth  = -$width;
                break;
            case self::IMAGE_FLIP_BOTH:
                $srcX      =  $width;
                $srcY      =  $height;
                $srcWidth  = -$width;
                $srcHeight = -$height;
                break;
            default:
                return $imgsrc;
        }

        $imgdest = imagecreatetruecolor($width, $height);
        if (
            imagecopyresampled(
                $imgdest,
                $imgsrc,
                0,
                0,
                $srcX,
                $srcY,
                $width,
                $height,
                $srcWidth,
                $srcHeight
            )
        ) {
            return $imgdest;
        }
        return $imgsrc;
    }
}
