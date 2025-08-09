<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure\Gbx;

use Exception;
use RuntimeException;
use OverflowException;
use InvalidArgumentException;

class BaseFetcher
{
    public const int GBX_CHALLENGE_TMF     = 0x03043000;
    public const int GBX_AUTOSAVE_TMF      = 0x03093000;
    public const int GBX_CHALLENGE_TM      = 0x24003000;
    public const int GBX_AUTOSAVE_TM       = 0x2403F000;
    public const int GBX_REPLAY_TM         = 0x2407E000;
    public const int MACHINE_ENDIAN_ORDER  = 0;
    public const int LITTLE_ENDIAN_ORDER   = 1;
    public const int BIG_ENDIAN_ORDER      = 2;
    public const int LOAD_LIMIT            = 256;
    public const int EPOCH_DIFF            = 116444735995904000; // Difference in 100-nanosecond units
    public const int UINT32_MAX            = 4294967296; // 2^32
    public const int USEC_IN_SEC           = 1000000; // 1 million microseconds
    public bool $parseXml = false;
    public string $xml = '';
    public array $xmlParsed = [];
    public int $authorVer = 0;
    public string $authorLogin = '';
    public string $authorNick = '';
    public string $authorZone = '';
    public string $authorEInfo = '';
    private bool $debug = false;
    private string $error = '';
    private int $endianess = 0;
    private int $gbxptr = 0;
    private int $gbxlen = 0;
    /**
     *
     * @var array<mixed>
     */
    private array $lookbacks = [];
    private string $gbxdata = '';

    public function __construct()
    {
        $this->edian();
        $this->clearGBXdata();
        $this->clearLookbacks();
    }

    protected function disableDebug(): void
    {
        $this->debug = false;
    }

    protected function debugLog(string $msg): void
    {
        if ($this->debug) {
            fwrite(STDERR, $msg . "\n");
        }
    }

    protected function setError(string $prefix): void
    {
        $this->error = $prefix;
    }


    protected function errorOut(string $msg, int $code = 0): void
    {
        $this->clearGBXdata();
        throw new Exception($this->error . $msg, $code);
    }

    protected function loadGBXdata(string $filename): void
    {
        $gbxdata = @file_get_contents($filename, false, null, 0, self::LOAD_LIMIT * 1024);
        if ($gbxdata !== false) {
            $this->storeGBXdata($gbxdata);
        } else {
            $this->errorOut('Unable to read GBX data from ' . $filename, 1);
        }
    }

    protected function storeGBXdata(string $gbxdata): void
    {
        $this->gbxdata = &$gbxdata;
        $this->gbxlen = strlen($gbxdata);
        $this->gbxptr = 0;
        if ($this->gbxlen > 0) {
            $this->debugLog('GBX data length: ' . $this->gbxlen);
        }
    }

    protected function retrieveGBXdata(): string
    {
        return $this->gbxdata;
    }

    protected function clearGBXdata(): void
    {
        $this->storeGBXdata('');
    }

    protected function getGBXptr(): int
    {
        return $this->gbxptr;
    }

    protected function setGBXptr(int $ptr): void
    {
        $this->gbxptr = $ptr;
    }

    protected function moveGBXptr(int $len): void
    {
        $this->gbxptr += $len;
    }

    protected function readData(int $len): string
    {
        if ($this->gbxptr + $len > $this->gbxlen) {
            $this->errorOut(sprintf(
                'Insufficient data for %d bytes at pos 0x%04X',
                $len,
                $this->gbxptr
            ), 2);
        }

        $data = substr($this->gbxdata, $this->gbxptr, $len);
        $this->gbxptr += $len;

        return $data;
    }

    // read signed byte from GBX data
    protected function readInt8(): int
    {
        $data = $this->readData(1);
        list(, $int8) = unpack('c*', $data);
        return $int8;
    }

    // read signed short from GBX data
    protected function readInt16(): int
    {
        $data = $this->readData(2);
        if ($this->endianess === self::BIG_ENDIAN_ORDER) {
            $data = strrev($data);
        }
        list(, $int16) = unpack('s*', $data);
        return $int16;
    }

    // read signed long from GBX data
    protected function readInt32(): int
    {
        $data = $this->readData(4);
        if (strlen($data) !== 4) {
            throw new \RuntimeException('Failed to read 4 bytes for int32');
        }
        if ($this->endianess === self::BIG_ENDIAN_ORDER) {
            $data = strrev($data);
        }
        list(, $int32) = unpack('l*', $data);
        return $int32;
    }

    // read string from GBX data
    protected function readString(): string
    {
        $gbxptr = $this->getGBXptr();
        $len = $this->readInt32();
        $len &= 0x7FFFFFFF;

        if ($len <= 0 || $len >= 0x18000) {  // for large XML & Data blocks
            if ($len != 0) {
                $this->errorOut(sprintf(
                    'Invalid string length %d (0x%04X) at pos 0x%04X',
                    $len,
                    $len,
                    $gbxptr
                ), 3);
            }
        }

        return $this->readData($len);
    }

    // strip UTF-8 BOM from string
    protected function stripBOM(string $str): string
    {
        return str_replace("\xEF\xBB\xBF", '', $str);
    }

    protected function clearLookbacks(): void
    {
        unset($this->lookbacks);
    }

    protected function readLookbackString(): string
    {

        if (!isset($this->lookbacks)) {
            $this->lookbacks = [];
            $version = $this->readInt32();
            if ($version !== 3) {
                $this->errorOut('Unknown lookback strings version: ' . $version, 4);
            }
        }

        // check index
        $index = $this->readInt32();
        if ($index == -1) {
            $str = '';
        } elseif (($index & 0xC0000000) == 0) {
            // use external reference string
            switch ($index) {
                case 11:
                    $str = 'Valley';
                    break;
                case 12:
                    $str = 'Canyon';
                    break;
                case 13:
                    $str = 'Lagoon';
                    break;
                case 17:
                    $str = 'TMCommon';
                    break;
                case 202:
                    $str = 'Storm';
                    break;
                case 299:
                    $str = 'SMCommon';
                    break;
                case 10003:
                    $str = 'Common';
                    break;
                default:
                    $str = 'UNKNOWN';
            }
        } elseif (($index & 0x3FFFFFFF) == 0) {
            // read string & add to lookbacks
            $str = $this->readString();
            $this->lookbacks[] = $str;
        } else {
            // use string from lookbacks
            $str = $this->lookbacks[($index & 0x3FFFFFFF) - 1];
        }

        return $str;
    }

    protected function parseXMLstring(): void
    {
        libxml_use_internal_errors(true); // to handle errors more gracefully

        $xml = preg_replace('/&(?!(?:amp|quot|apos|lt|gt);)/', '&amp;', $this->xml);
        $xmlObject = false;

        if (is_string($xml)) {
            $xmlObject = simplexml_load_string($xml);
        }

        if ($xmlObject === false) {
            $errors = libxml_get_errors();
            $this->errorOut(sprintf('XML chunk parse error: %s', $errors[0]->message), 12);
        }
        /** @var \SimpleXMLElement $xmlObject */
        $this->xmlParsed = json_decode(json_encode($xmlObject), true);
        $this->convertValues($this->xmlParsed);
    }

    private function convertValues(array &$data): void
    {
        foreach ($data as $key => &$value) {
            // Check if the value is an array and recurse
            if (is_array($value)) {
                $this->convertValues($value);
            } else {
                // Type conversion logic based on key
                switch ($key) {
                    case 'NBLAPS':
                    case 'PRICE':
                    case 'AUTHORTIME':
                    case 'AUTHORSCORE':
                    case 'BRONZE':
                    case 'SILVER':
                    case 'GOLD':
                        // Convert to integer
                        $value = (int)$value;
                        break;

                    case 'SOME_FLOAT_FIELD': // Replace with actual float fields
                        // Convert to float
                        $value = (float)$value;
                        break;

                    case 'SOME_BOOLEAN_FIELD': // Replace with actual boolean fields
                        // Convert to boolean
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;

                        // You can add more cases here as needed for other types
                    default:
                        // Leave the value as is or handle other types
                        break;
                }
            }
        }
    }

    /**
     * Check GBX header, main class ID & header block
     *
     * @param int[] $classes
     *              The main class IDs accepted for this GBX
     * @return int Size of GBX header block
     * @throws Exception if the GBX header or version is invalid
     */
    protected function checkHeader(array $classes): int
    {
        // check magic header
        $data = $this->readData(3);
        $version = $this->readInt16();
        if ($data !== 'GBX') {
            $this->errorOut('No magic GBX header', 5);
        }

        if ($version !== 6) {
            $this->errorOut('Unsupported GBX version: ' . $version, 6);
        }

        $this->moveGBXptr(4);  // skip format/compression/unknown bytes

        // check main class ID
        $mainClass = $this->readInt32();

        if (!in_array($mainClass, $classes)) {
            $this->errorOut(sprintf('Main class ID %08X not supported', $mainClass), 7);
        }

        $this->debugLog(sprintf('GBX main class ID: %08X', $mainClass));

        // get header size
        $headerSize = $this->readInt32();
        $this->debugLog(sprintf('GBX header block size: %d (%.1f KB)', $headerSize, $headerSize / 1024));

        return $headerSize;
    }

    /**
     * Get list of chunks from GBX header block
     *
     * @param int $headerSize
     *        Size of header block (chunks list & chunks data)
     * @param array $chunks
     *        List of chunk IDs & names
     * @return array List of chunk offsets & sizes
     * @throws Exception if no chunks are found or size mismatch occurs
     */
    protected function getChunksList(int $headerSize, array $chunks): array
    {
        // get number of chunks
        $numChunks = $this->readInt32();

        if ($numChunks === 0) {
            $this->errorOut('No GBX header chunks', 9);
        }

        $this->debugLog('GBX number of header chunks: ' . $numChunks);
        $chunkStart = $this->getGBXptr();
        $this->debugLog(sprintf('GBX start of chunk list: 0x%04X', $chunkStart));
        $chunkOffset = $chunkStart + $numChunks * 8;

        // get list of all chunks
        $chunksList = [];

        for ($i = 0; $i < $numChunks; $i++) {
            $chunkId = $this->readInt32();
            //$chunkSize = $this->readInt32() & 0x7FFFFFFF;
            $chunkSize = $this->readInt32();
            $chunkSize &= 0x7FFFFFFF;

            $name = $chunks[$chunkId] ?? 'UNKNOWN';

            $chunksList[$name] = [
                'off' => $chunkOffset,
                'size' => $chunkSize,
            ];

            $this->debugLog(sprintf(
                'GBX chunk %2d  %-8s  Id  0x%08X  Offset  0x%06X  Size %6d',
                $i,
                $name,
                $chunkId,
                $chunkOffset,
                $chunkSize
            ));

            $chunkOffset += $chunkSize;
        }

        // Check size consistency
        $totalSize = $chunkOffset - $chunkStart + 4;  // numChunks
        if ($headerSize !== $totalSize) {
            $this->errorOut(sprintf('Chunk list size mismatch: %d <> %d', $headerSize, $totalSize), 10);
        }

        return $chunksList;
    }

    /**
     * Initialize for a new chunk
     * @param int $offset
     */
    protected function initChunk(int $offset): void
    {
        $this->setGBXptr($offset);
        $this->clearLookbacks();
    }

    /**
     * Get XML chunk from GBX header block & optionally parse it
     *
     * @param array $chunksList
     *        List of chunk offsets & sizes
     * @throws Exception if XML chunk is not found or size mismatch occurs
     */
    protected function getXMLChunk(array $chunksList): void
    {
        if (!isset($chunksList['XML'])) {
            return; // Exit early if XML chunk does not exist
        }

        $this->initChunk($chunksList['XML']['off']);
        $this->xml = $this->readString();
        $xmlLen = strlen($this->xml);

        // Check for XML chunk that's not zero-filled
        if ($xmlLen > 0 && $chunksList['XML']['size'] !== $xmlLen + 4) {
            $this->errorOut(sprintf(
                'XML chunk size mismatch: %d <> %d',
                $chunksList['XML']['size'],
                $xmlLen + 4
            ), 11);
        }

        // Parse the XML string if the flag is set and it's not empty
        if ($this->parseXml && $this->xml !== '') {
            $this->parseXMLstring();
        }
    }

    protected function getAuthorFields(): void
    {
        $this->authorVer   = $this->readInt32();
        $this->authorLogin = $this->readString();
        $this->authorNick  = $this->stripBOM($this->readString());
        $this->authorZone  = $this->stripBOM($this->readString());
        $this->authorEInfo = $this->readString();
    }

    protected function getAuthorChunk(array $chunksList): void
    {
        if (!isset($chunksList['Author'])) {
            return;
        }

        $this->initChunk($chunksList['Author']['off']);
        $version = $this->readInt32();
        $this->debugLog('GBX Author chunk version: ' . $version);

        $this->getAuthorFields();
    }

    protected function readFiletime(): int
    {
        $lo = $this->readInt32();
        $hi = $this->readInt32();

        // Check for 64-bit platform
        if (PHP_INT_SIZE === 8) {
            $date = ($hi << 32) | ($lo & 0xFFFFFFFF);
            $this->debugLog(sprintf('PAK CreationDate source: %016x', $date));

            return ($date === 0) ? -1 : (int)(($date - self::EPOCH_DIFF) / 10);
        }

        // Check for 32-bit platform
        if (PHP_INT_MAX === 2147483647) {
            $this->debugLog(sprintf('PAK CreationDate source: %08x%08x', $hi, $lo));

            if ($lo === 0 && $hi === 0) {
                return -1;
            }

            // Convert to unsigned strings to avoid issues with large values
            $lo = sprintf('%u', $lo);
            $hi = sprintf('%u', $hi);

            // Use GMP or BCMath if available
            try {
                $timestamp = $this->calculateTimestamp($hi, $lo);
                if ($timestamp !== null) {
                    return (int)($timestamp / self::USEC_IN_SEC);
                }
            } catch (RuntimeException $e) {
                // Log the exception message and continue with manual calculation
                $this->debugLog($e->getMessage());
            }
            // Fallback to manual timestamp calculation
            return $this->manualTimestampCalculation($hi, $lo);
        }

        return -1; // Unsupported platform
    }

    private function calculateTimestamp(string $hi, string $lo): ?int
    {
        $isWindows = stristr(PHP_OS, 'WIN') !== false;
        // Use GMP for calculations if available and not on Windows
        if (function_exists('gmp_mul') && !$isWindows) {
            $highPart = gmp_mul($hi, self::UINT32_MAX);
            $date = gmp_add($highPart, gmp_init($lo));
            $result = gmp_div(gmp_sub($date, gmp_init(self::EPOCH_DIFF)), 10);
            return gmp_intval($result);
        }

        if (function_exists('bcmul')) {
            $date = bcadd(bcmul($hi, (string)self::UINT32_MAX), $lo);
            return (int)bcdiv(bcsub($date, (string)self::EPOCH_DIFF), '10', 0);
        }

        return null;
    }

    private function manualTimestampCalculation(string $hi, string $lo): int
    {
        if (!is_numeric($hi) || !is_numeric($lo)) {
            throw new InvalidArgumentException("Both high and low parts must be numeric strings.");
        }

        $highPart = bcmul($hi, '42949');
        $lowPart = $lo;

        $total = bcadd($highPart, $lowPart);

        // Adjust for epoch difference
        $total = bcsub($total, (string)(self::EPOCH_DIFF / 10));

        // Ensure positive timestamp
        $total = bccomp($total, '0') < 0 ? '0' : $total;
        if (bccomp($total, (string)PHP_INT_MAX) > 0) {
            // return $total; // Uncomment to return as a string
            throw new OverflowException("The calculated timestamp exceeds the maximum integer size.");
        }
        // Safe to cast to int if within range
        return (int)$total;
    }

    private function edian(): void
    {
        list($endiantest) = array_values(unpack('L1L', pack('V', 1)));
        if ($endiantest != 1) {
            $this->endianess = self::BIG_ENDIAN_ORDER;
        } else {
            $this->endianess = self::LITTLE_ENDIAN_ORDER;
        }
    }
}
