<?php

declare(strict_types=1);

namespace VlyDev\Steam;

/**
 * Represents a CS2 item as encoded in an inspect link.
 *
 * Fields map directly to the CEconItemPreviewDataBlock protobuf message
 * used by the CS2 game coordinator.
 *
 * paintwear is stored as a float (IEEE 754 float32). On the wire it is
 * reinterpreted as a uint32 varint — this class always exposes it as a
 * PHP float for convenience.
 *
 * @phpstan-type StickerList list<Sticker>
 */
final class ItemPreviewData
{
    /** @param list<Sticker> $stickers
     *  @param list<Sticker> $keychains
     */
    public function __construct(
        public int $accountid = 0,
        public int $itemid = 0,
        public int $defindex = 0,
        public int $paintindex = 0,
        public int $rarity = 0,
        public int $quality = 0,
        public ?float $paintwear = null,
        public int $paintseed = 0,
        public int $killeaterscoretype = 0,
        public int $killeatervalue = 0,
        public string $customname = '',
        public array $stickers = [],
        public int $inventory = 0,
        public int $origin = 0,
        public int $questid = 0,
        public int $dropreason = 0,
        public int $musicindex = 0,
        public int $entindex = 0,
        public int $petindex = 0,
        public array $keychains = [],
    ) {}
}
