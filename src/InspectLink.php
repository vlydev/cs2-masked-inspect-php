<?php

declare(strict_types=1);

namespace VlyDev\Steam;

use VlyDev\Steam\Proto\Reader;
use VlyDev\Steam\Proto\WireType;
use VlyDev\Steam\Proto\Writer;

/**
 * Encodes and decodes CS2 masked inspect links.
 *
 * Binary format:
 *   [key_byte] [proto_bytes XOR'd with key] [4-byte checksum XOR'd with key]
 *
 * For tool-generated links key_byte = 0x00 (no XOR needed).
 * For native CS2 links key_byte != 0x00 — every byte must be XOR'd before parsing.
 *
 * Checksum:
 *   crc = crc32(buffer)   where buffer = chr(0x00) . proto_bytes
 *   xored = (crc & 0xffff) ^ (strlen(proto_bytes) * crc)   [unsigned 32-bit]
 *   checksum = big-endian uint32 of (xored & 0xFFFFFFFF)
 */
final class InspectLink
{
    /**
     * Encode an ItemPreviewData to an uppercase hex inspect-link payload.
     *
     * The returned string can be appended to a steam:// inspect URL or used
     * standalone. The key_byte is always 0x00 (no XOR applied).
     */
    public static function serialize(ItemPreviewData $data): string
    {
        if ($data->paintwear !== null && ($data->paintwear < 0.0 || $data->paintwear > 1.0)) {
            throw new \InvalidArgumentException(
                sprintf('paintwear must be in [0.0, 1.0], got %f', $data->paintwear),
            );
        }
        if ($data->customname !== null && strlen($data->customname) > 100) {
            throw new \InvalidArgumentException(
                sprintf('customname must not exceed 100 characters, got %d', strlen($data->customname)),
            );
        }

        $protoBytes = self::encodeItem($data);
        $buffer = "\x00" . $protoBytes;
        $checksum = self::computeChecksum($buffer, strlen($protoBytes));

        return strtoupper(bin2hex($buffer . $checksum));
    }

    /**
     * Decode an inspect-link hex payload (or full URL) into an ItemPreviewData.
     *
     * Accepts:
     *   - A raw uppercase or lowercase hex string
     *   - A full steam://rungame/... inspect URL
     *   - A CS2-style csgo://rungame/... URL
     *
     * Handles the XOR obfuscation used in native CS2 links.
     */
    public static function deserialize(string $hexOrUrl): ItemPreviewData
    {
        $hex = self::extractHex($hexOrUrl);

        if (strlen($hex) > 4096) {
            throw new \InvalidArgumentException(
                sprintf('Payload too long (max 4096 hex chars): "%s"', substr($hexOrUrl, 0, 64) . '...'),
            );
        }

        $raw = hex2bin($hex);

        if ($raw === false || strlen($raw) < 6) {
            throw new \InvalidArgumentException(
                sprintf('Payload too short or invalid hex: "%s"', $hexOrUrl),
            );
        }

        $key = ord($raw[0]);

        if ($key === 0) {
            $decrypted = $raw;
        } else {
            $decrypted = '';
            for ($i = 0, $len = strlen($raw); $i < $len; $i++) {
                $decrypted .= chr(ord($raw[$i]) ^ $key);
            }
        }

        // Layout: [key_byte] [proto_bytes] [4-byte checksum]
        $protoBytes = substr($decrypted, 1, -4);

        return self::decodeItem($protoBytes);
    }

    // ------------------------------------------------------------------
    // Private helpers: URL extraction
    // ------------------------------------------------------------------

    public static function isMasked(string $link): bool
    {
        // Pure hex blob (new steam://run/730// format)
        if (preg_match('/csgo_econ_action_preview(?:%20|\s)[0-9A-Fa-f]{10,}$/i', $link)) {
            return true;
        }
        // Hybrid: S/A/D prefix with hex proto after D
        if (preg_match('/S\d+A\d+D([0-9A-Fa-f]+)$/i', $link, $m)) {
            return (bool) preg_match('/[A-Fa-f]/', $m[1]);
        }
        return false;
    }

    public static function isClassic(string $link): bool
    {
        return (bool) preg_match('/csgo_econ_action_preview(?:%20|\s)[SM]\d+A\d+D\d+$/i', $link);
    }

    private static function extractHex(string $input): string
    {
        $stripped = trim($input);

        // Hybrid format: S\d+A\d+D<hexproto> — extract the hex part after D
        if (preg_match('/S\d+A\d+D([0-9A-Fa-f]+)$/i', $stripped, $m)
            && preg_match('/[A-Fa-f]/', $m[1])) {
            return $m[1];
        }

        // Classic/market URL: A<hex> preceded by %20, space, or + (A is a prefix marker, not hex).
        // If stripping A yields odd-length hex, A is actually the first byte of the payload —
        // fall through to the pure-masked check below which captures it with A included.
        if (preg_match('/(?:%20|\s|\+)A([0-9A-Fa-f]+)/i', $stripped, $m)
            && 0 === strlen($m[1]) % 2) {
            return $m[1];
        }

        // Pure masked format: csgo_econ_action_preview%20<hexblob> (no S/A/M prefix).
        // Also handles payloads whose first hex character happens to be A.
        if (preg_match('/csgo_econ_action_preview(?:%20|\s|\+)([0-9A-Fa-f]{10,})$/i', $stripped, $m)) {
            return $m[1];
        }

        // Bare hex — strip any whitespace
        return preg_replace('/\s+/', '', $stripped) ?? $stripped;
    }

    // ------------------------------------------------------------------
    // Private helpers: checksum
    // ------------------------------------------------------------------

    private static function computeChecksum(string $buffer, int $protoLen): string
    {
        // PHP's crc32() returns a signed int; mask to unsigned 32-bit
        $crc = crc32($buffer) & 0xFFFFFFFF;
        $xored = (($crc & 0xFFFF) ^ ($protoLen * $crc)) & 0xFFFFFFFF;

        return pack('N', $xored); // big-endian uint32
    }

    // ------------------------------------------------------------------
    // Private helpers: float32 ↔ uint32 reinterpretation
    // ------------------------------------------------------------------

    private static function float32ToUint32(float $f): int
    {
        $packed = pack('f', $f);         // native float (little-endian on x86)
        $unpacked = unpack('V', $packed); // unsigned 32-bit little-endian

        return (int) $unpacked[1];
    }

    private static function uint32ToFloat32(int $u): float
    {
        $packed = pack('V', $u & 0xFFFFFFFF);
        $unpacked = unpack('f', $packed);

        return (float) $unpacked[1];
    }

    // ------------------------------------------------------------------
    // Private helpers: Sticker encode/decode
    // ------------------------------------------------------------------

    private static function encodeSticker(Sticker $s): string
    {
        $w = new Writer();
        $w->writeUint32(1, $s->slot);
        $w->writeUint32(2, $s->stickerId);

        if ($s->wear !== null) {
            $w->writeFloat32Fixed(3, $s->wear);
        }

        if ($s->scale !== null) {
            $w->writeFloat32Fixed(4, $s->scale);
        }

        if ($s->rotation !== null) {
            $w->writeFloat32Fixed(5, $s->rotation);
        }

        $w->writeUint32(6, $s->tintId);

        if ($s->offsetX !== null) {
            $w->writeFloat32Fixed(7, $s->offsetX);
        }

        if ($s->offsetY !== null) {
            $w->writeFloat32Fixed(8, $s->offsetY);
        }

        if ($s->offsetZ !== null) {
            $w->writeFloat32Fixed(9, $s->offsetZ);
        }

        $w->writeUint32(10, $s->pattern);

        if ($s->highlightReel !== null) {
            $w->writeUint32(11, $s->highlightReel);
        }

        return $w->toBytes();
    }

    private static function decodeSticker(string $data): Sticker
    {
        $reader = new Reader($data);
        $s = new Sticker();

        foreach ($reader->readAllFields() as $f) {
            match ($f['field']) {
                1 => $s->slot = $f['value'],
                2 => $s->stickerId = $f['value'],
                3 => $s->wear = (float) unpack('f', $f['value'])[1],
                4 => $s->scale = (float) unpack('f', $f['value'])[1],
                5 => $s->rotation = (float) unpack('f', $f['value'])[1],
                6 => $s->tintId = $f['value'],
                7 => $s->offsetX = (float) unpack('f', $f['value'])[1],
                8 => $s->offsetY = (float) unpack('f', $f['value'])[1],
                9 => $s->offsetZ = (float) unpack('f', $f['value'])[1],
                10 => $s->pattern = $f['value'],
                11 => $s->highlightReel = $f['value'],
                default => null,
            };
        }

        return $s;
    }

    // ------------------------------------------------------------------
    // Private helpers: ItemPreviewData encode/decode
    // ------------------------------------------------------------------

    private static function encodeItem(ItemPreviewData $item): string
    {
        $w = new Writer();
        $w->writeUint32(1, $item->accountid);
        $w->writeUint64(2, $item->itemid);
        $w->writeUint32(3, $item->defindex);
        $w->writeUint32(4, $item->paintindex);
        $w->writeUint32(5, $item->rarity);
        $w->writeUint32(6, $item->quality);

        // paintwear: float32 reinterpreted as uint32 varint (only written if set)
        if ($item->paintwear !== null) {
            $pwUint32 = self::float32ToUint32($item->paintwear);
            $w->writeUint32(7, $pwUint32);
        }

        $w->writeUint32(8, $item->paintseed);
        $w->writeUint32(9, $item->killeaterscoretype);
        $w->writeUint32(10, $item->killeatervalue);
        $w->writeString(11, $item->customname);

        foreach ($item->stickers as $sticker) {
            $w->writeRawBytes(12, self::encodeSticker($sticker));
        }

        $w->writeUint32(13, $item->inventory);
        $w->writeUint32(14, $item->origin);
        $w->writeUint32(15, $item->questid);
        $w->writeUint32(16, $item->dropreason);
        $w->writeUint32(17, $item->musicindex);
        $w->writeInt32(18, $item->entindex);
        $w->writeUint32(19, $item->petindex);

        foreach ($item->keychains as $kc) {
            $w->writeRawBytes(20, self::encodeSticker($kc));
        }

        return $w->toBytes();
    }

    private static function decodeItem(string $data): ItemPreviewData
    {
        $reader = new Reader($data);
        $item = new ItemPreviewData();

        foreach ($reader->readAllFields() as $f) {
            match ($f['field']) {
                1 => $item->accountid = $f['value'],
                2 => $item->itemid = $f['value'],
                3 => $item->defindex = $f['value'],
                4 => $item->paintindex = $f['value'],
                5 => $item->rarity = $f['value'],
                6 => $item->quality = $f['value'],
                7 => $item->paintwear = self::uint32ToFloat32($f['value']),
                8 => $item->paintseed = $f['value'],
                9 => $item->killeaterscoretype = $f['value'],
                10 => $item->killeatervalue = $f['value'],
                11 => $item->customname = $f['value'],
                12 => $item->stickers[] = self::decodeSticker($f['value']),
                13 => $item->inventory = $f['value'],
                14 => $item->origin = $f['value'],
                15 => $item->questid = $f['value'],
                16 => $item->dropreason = $f['value'],
                17 => $item->musicindex = $f['value'],
                18 => $item->entindex = $f['value'],
                19 => $item->petindex = $f['value'],
                20 => $item->keychains[] = self::decodeSticker($f['value']),
                default => null,
            };
        }

        return $item;
    }
}
