<?php

declare(strict_types=1);

namespace VlyDev\Steam;

/**
 * Gen code utilities for CS2 inspect links.
 *
 * Gen codes are space-separated command strings used on community servers:
 *   !gen {defindex} {paintindex} {paintseed} {paintwear}
 *   !gen {defindex} {paintindex} {paintseed} {paintwear} {s0_id} {s0_wear} ... {s4_id} {s4_wear} [{kc_id} {kc_wear} ...] {scoreType} {kills} [sd {n} ...] [kd {n} ...]
 *
 * Stickers are always padded to 5 slot pairs. Keychains follow without padding.
 * StatTrak is appended as two trailing numeric tokens: {scoreType} {kills}.
 * Optional sd/kd blocks preserve exact placement/rotation data for round-trips.
 */
final class GenCode
{
    public const INSPECT_BASE = 'steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20';

    /**
     * Format a float value, stripping trailing zeros (max 8 decimal places).
     */
    private static function formatFloat(float $value): string
    {
        $s = rtrim(number_format($value, 8, '.', ''), '0');
        $s = rtrim($s, '.');
        return $s === '' ? '0' : $s;
    }

    /**
     * Serialize stickers to [id, wear] pairs, optionally padded to N slots.
     *
     * @param Sticker[] $stickers
     * @param int|null  $padTo
     * @return string[]
     */
    private static function serializeStickerPairs(array $stickers, ?int $padTo): array
    {
        $result = [];
        $filtered = array_filter($stickers, fn(Sticker $s) => $s->stickerId !== 0);

        if ($padTo !== null) {
            $slotMap = [];
            foreach ($filtered as $s) {
                $slotMap[$s->slot] = $s;
            }
            for ($slot = 0; $slot < $padTo; $slot++) {
                if (isset($slotMap[$slot])) {
                    $s = $slotMap[$slot];
                    $result[] = (string) $s->stickerId;
                    $result[] = self::formatFloat($s->wear ?? 0.0);
                } else {
                    $result[] = '0';
                    $result[] = '0';
                }
            }
        } else {
            usort($filtered, fn(Sticker $a, Sticker $b) => $a->slot <=> $b->slot);
            foreach ($filtered as $s) {
                $result[] = (string) $s->stickerId;
                $result[] = self::formatFloat($s->wear ?? 0.0);
                if ($s->paintKit !== null) {
                    $result[] = (string) $s->paintKit;
                }
            }
        }

        return $result;
    }

    /**
     * Serialize exact sticker/charm placement records.
     *
     * Record layout:
     *   slot id wear scale rotation offsetX offsetY offsetZ pattern paintKit
     *
     * @param Sticker[] $stickers
     * @return string[]
     */
    private static function serializePlacementDetails(array $stickers, string $marker): array
    {
        $filtered = array_values(array_filter($stickers, fn(Sticker $s) => $s->stickerId !== 0));
        if ($filtered === []) {
            return [];
        }

        usort($filtered, fn(Sticker $a, Sticker $b) => $a->slot <=> $b->slot);

        $result = [$marker, (string) count($filtered)];
        foreach ($filtered as $s) {
            $result[] = (string) $s->slot;
            $result[] = (string) $s->stickerId;
            $result[] = self::formatFloat($s->wear ?? 0.0);
            $result[] = self::formatFloat($s->scale ?? 0.0);
            $result[] = self::formatFloat($s->rotation ?? 0.0);
            $result[] = self::formatFloat($s->offsetX ?? 0.0);
            $result[] = self::formatFloat($s->offsetY ?? 0.0);
            $result[] = self::formatFloat($s->offsetZ ?? 0.0);
            $result[] = (string) $s->pattern;
            $result[] = (string) ($s->paintKit ?? 0);
        }

        return $result;
    }

    /**
     * Decide whether exact sticker details should be emitted.
     *
     * @param Sticker[] $stickers
     */
    private static function needsStickerDetailBlock(array $stickers): bool
    {
        foreach ($stickers as $s) {
            if (
                $s->stickerId !== 0
                && (
                    $s->scale !== null
                    || $s->rotation !== null
                    || $s->offsetX !== null
                    || $s->offsetY !== null
                    || $s->offsetZ !== null
                    || $s->pattern !== 0
                    || $s->paintKit !== null
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $tokens
     * @param int      $start
     * @return array{list<Sticker>, int}
     */
    private static function parsePlacementDetails(array $tokens, int $start): array
    {
        $parsed = [];
        $count = count($tokens);

        if ($start + 1 >= $count) {
            throw new \InvalidArgumentException('Malformed detail block: missing record count.');
        }

        $records = (int) $tokens[$start + 1];
        $i = $start + 2;

        for ($r = 0; $r < $records; $r++) {
            if ($i + 9 >= $count) {
                throw new \InvalidArgumentException('Malformed detail block: truncated record.');
            }

            $parsed[] = new Sticker(
                slot: (int) $tokens[$i],
                stickerId: (int) $tokens[$i + 1],
                wear: (float) $tokens[$i + 2],
                scale: (float) $tokens[$i + 3],
                rotation: (float) $tokens[$i + 4],
                offsetX: (float) $tokens[$i + 5],
                offsetY: (float) $tokens[$i + 6],
                offsetZ: (float) $tokens[$i + 7],
                pattern: (int) $tokens[$i + 8],
                paintKit: (int) $tokens[$i + 9],
            );
            $i += 10;
        }

        return [$parsed, $i];
    }

    /**
     * Convert an ItemPreviewData to a gen code string.
     *
     * @param string $prefix The command prefix, e.g. "!gen" or "!g".
     */
    public static function toGenCode(ItemPreviewData $item, string $prefix = '!gen'): string
    {
        $wearStr = $item->paintwear !== null ? self::formatFloat($item->paintwear) : '0';
        $parts = [
            (string) $item->defindex,
            (string) $item->paintindex,
            (string) $item->paintseed,
            $wearStr,
        ];

        array_push($parts, ...self::serializeStickerPairs($item->stickers, 5));
        array_push($parts, ...self::serializeStickerPairs($item->keychains, null));
        array_push($parts, (string) $item->killeaterscoretype, (string) $item->killeatervalue);

        if (self::needsStickerDetailBlock($item->stickers)) {
            array_push($parts, ...self::serializePlacementDetails($item->stickers, 'sd'));
        }

        // Always include keychain details so charm slot and placement are never lost.
        if ($item->keychains !== []) {
            array_push($parts, ...self::serializePlacementDetails($item->keychains, 'kd'));
        }

        $payload = implode(' ', $parts);
        return $prefix !== '' ? "{$prefix} {$payload}" : $payload;
    }

    /**
     * Generate a full Steam inspect URL from item parameters.
     *
     * @param int     $defIndex   Weapon definition ID (e.g. 7 = AK-47)
     * @param int     $paintIndex Skin/paint ID
     * @param int     $paintSeed  Pattern index (0-1000)
     * @param float   $paintWear  Float value (0.0-1.0)
     * @param int     $rarity     Item rarity tier
     * @param int     $quality    Item quality (e.g. 9 = StatTrak)
     * @param Sticker[] $stickers
     * @param Sticker[] $keychains
     */
    public static function generate(
        int $defIndex,
        int $paintIndex,
        int $paintSeed,
        float $paintWear,
        int $rarity = 0,
        int $quality = 0,
        array $stickers = [],
        array $keychains = [],
    ): string {
        $data = new ItemPreviewData(
            defindex: $defIndex,
            paintindex: $paintIndex,
            paintseed: $paintSeed,
            paintwear: $paintWear,
            rarity: $rarity,
            quality: $quality,
            stickers: $stickers,
            keychains: $keychains,
        );
        $hex = InspectLink::serialize($data);
        return self::INSPECT_BASE . $hex;
    }

    /**
     * Generate a gen code string from an existing CS2 inspect link.
     *
     * Deserializes the inspect link and converts the item data to gen code format.
     *
     * @param string $hexOrUrl A hex payload or full steam:// inspect URL.
     * @param string $prefix   The command prefix, e.g. "!gen" or "!g".
     */
    public static function genCodeFromLink(string $hexOrUrl, string $prefix = '!gen'): string
    {
        $item = InspectLink::deserialize($hexOrUrl);
        return self::toGenCode($item, $prefix);
    }

    /**
     * Parse a gen code string into an ItemPreviewData.
     *
     * Accepts codes like:
     *   "!gen 7 474 306 0.22540508"
    *   "7 941 2 0.22540508 0 0 0 0 7203 0 0 0 0 0 36 0 0 0"
     *
     * @throws \InvalidArgumentException If the code has fewer than 4 tokens.
     */
    public static function parseGenCode(string $genCode): ItemPreviewData
    {
        $tokens = preg_split('/\s+/', trim($genCode));
        if ($tokens === false) {
            $tokens = [];
        }

        // Skip leading !-prefixed command
        if (!empty($tokens) && str_starts_with($tokens[0], '!')) {
            array_shift($tokens);
        }

        if (count($tokens) < 4) {
            throw new \InvalidArgumentException(
                "Gen code must have at least 4 tokens, got: \"{$genCode}\""
            );
        }

        $defIndex   = (int) $tokens[0];
        $paintIndex = (int) $tokens[1];
        $paintSeed  = (int) $tokens[2];
        $paintWear  = (float) $tokens[3];
        $rest       = array_slice($tokens, 4);
        $killeaterScoreType = 0;
        $killeaterValue = 0;
        $detailedStickers = null;
        $detailedKeychains = null;

        // Optional detailed blocks: sd/kd
        $detailStart = null;
        foreach ($rest as $idx => $tok) {
            if ($tok === 'sd' || $tok === 'kd') {
                $detailStart = $idx;
                break;
            }
        }

        if ($detailStart !== null) {
            $detailTokens = array_slice($rest, $detailStart);
            $rest = array_slice($rest, 0, $detailStart);

            $i = 0;
            while ($i < count($detailTokens)) {
                $marker = $detailTokens[$i] ?? null;
                if ($marker !== 'sd' && $marker !== 'kd') {
                    throw new \InvalidArgumentException('Malformed detail blocks: unknown marker token.');
                }

                [$parsed, $nextIndex] = self::parsePlacementDetails($detailTokens, $i);
                if ($marker === 'sd') {
                    $detailedStickers = $parsed;
                } else {
                    $detailedKeychains = $parsed;
                }

                $i = $nextIndex;
            }
        }

        // Compatibility 1: marker-based suffix: "st <scoreType> <kills>"
        if (count($rest) >= 3) {
            $stPos = array_search('st', $rest, true);
            if ($stPos !== false && $stPos + 2 < count($rest)) {
                $killeaterScoreType = (int) $rest[$stPos + 1];
                $killeaterValue = (int) $rest[$stPos + 2];
                $rest = array_slice($rest, 0, $stPos);
            }
        }

        // Compatibility 2 (current format): trailing numeric suffix: "... <scoreType> <kills>"
        // If no marker is present and at least two tokens remain, consume the last two tokens.
        if (count($rest) >= 2) {
            $last = count($rest) - 1;
            $killeaterScoreType = (int) $rest[$last - 1];
            $killeaterValue = (int) $rest[$last];
            $rest = array_slice($rest, 0, $last - 1);
        }

        $stickers  = [];
        $keychains = [];

        if (count($rest) >= 10) {
            $stickerTokens = array_slice($rest, 0, 10);
            for ($slot = 0; $slot < 5; $slot++) {
                $sid  = (int) $stickerTokens[$slot * 2];
                $wear = (float) $stickerTokens[$slot * 2 + 1];
                if ($sid !== 0) {
                    $stickers[] = new Sticker(slot: $slot, stickerId: $sid, wear: $wear);
                }
            }
            $rest = array_slice($rest, 10);
        }

        for ($i = 0; $i + 1 < count($rest); $i += 2) {
            $sid  = (int) $rest[$i];
            $wear = (float) $rest[$i + 1];
            if ($sid !== 0) {
                $keychains[] = new Sticker(slot: (int) ($i / 2), stickerId: $sid, wear: $wear);
            }
        }

        if ($detailedStickers !== null) {
            $stickers = $detailedStickers;
        }

        if ($detailedKeychains !== null) {
            $keychains = $detailedKeychains;
        }

        return new ItemPreviewData(
            defindex: $defIndex,
            paintindex: $paintIndex,
            paintseed: $paintSeed,
            paintwear: $paintWear,
            killeaterscoretype: $killeaterScoreType,
            killeatervalue: $killeaterValue,
            stickers: $stickers,
            keychains: $keychains,
        );
    }
}
