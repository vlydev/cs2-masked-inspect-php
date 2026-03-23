<?php

declare(strict_types=1);

namespace VlyDev\Steam;

/**
 * Gen code utilities for CS2 inspect links.
 *
 * Gen codes are space-separated command strings used on community servers:
 *   !gen {defindex} {paintindex} {paintseed} {paintwear}
 *   !gen {defindex} {paintindex} {paintseed} {paintwear} {s0_id} {s0_wear} ... {s4_id} {s4_wear} [{kc_id} {kc_wear} ...]
 *
 * Stickers are always padded to 5 slot pairs. Keychains follow without padding.
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
     *   "7 941 2 0.22540508 0 0 0 0 7203 0 0 0 0 0 36 0"
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

        return new ItemPreviewData(
            defindex: $defIndex,
            paintindex: $paintIndex,
            paintseed: $paintSeed,
            paintwear: $paintWear,
            stickers: $stickers,
            keychains: $keychains,
        );
    }
}
