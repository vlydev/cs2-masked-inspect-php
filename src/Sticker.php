<?php

declare(strict_types=1);

namespace VlyDev\Steam;

/**
 * Represents a sticker or keychain applied to a CS2 item.
 *
 * Maps to the Sticker protobuf message nested inside CEconItemPreviewDataBlock.
 * The same message is used for both stickers (field 12) and keychains (field 20).
 */
final class Sticker
{
    public function __construct(
        public int $slot = 0,
        public int $stickerId = 0,
        public ?float $wear = null,
        public ?float $scale = null,
        public ?float $rotation = null,
        public int $tintId = 0,
        public ?float $offsetX = null,
        public ?float $offsetY = null,
        public ?float $offsetZ = null,
        public int $pattern = 0,
        public ?int $highlightReel = null,
        public ?int $paintKit = null,
    ) {}
}
