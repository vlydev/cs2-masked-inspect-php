<?php

declare(strict_types=1);

use VlyDev\Steam\InspectLink;
use VlyDev\Steam\ItemPreviewData;
use VlyDev\Steam\Sticker;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CS2 inspect link serialization/deserialization.
 */
class InspectLinkTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Known test vectors
    // -----------------------------------------------------------------------

    /** A real CS2 item encoded with XOR key 0xE3. */
    private const NATIVE_HEX = 'E3F3367440334DE2FBE4C345E0CBE0D3E7DB6943400AE0A379E481ECEBE2F36FD9DE2BDB515EA6E30D74D981'
        . 'ECEBE3F37BCBDE640D475DA6E35EFCD881ECEBE3F359D5DE37E9D75DA6436DD3DD81ECEBE3F366DCDE3F8F9B'
        . 'DDA69B43B6DE81ECEBE3F33BC8DEBB1CA3DFA623F7DDDF8B71E293EBFD43382B';

    /** A tool-generated link with key 0x00. */
    private const TOOL_HEX = '00183C20B803280538E9A3C5DD0340E102C246A0D1';

    // -----------------------------------------------------------------------
    // Deserialize tests
    // -----------------------------------------------------------------------

    public function testNativeXorKeyItemId(): void
    {
        $item = InspectLink::deserialize(self::NATIVE_HEX);
        $this->assertSame(46876117973, $item->itemid);
    }

    public function testNativeXorKeyDefindex(): void
    {
        $item = InspectLink::deserialize(self::NATIVE_HEX);
        $this->assertSame(7, $item->defindex); // AK-47
    }

    public function testNativeXorKeyPaintindex(): void
    {
        $item = InspectLink::deserialize(self::NATIVE_HEX);
        $this->assertSame(422, $item->paintindex);
    }

    public function testNativeXorKeyPaintseed(): void
    {
        $item = InspectLink::deserialize(self::NATIVE_HEX);
        $this->assertSame(922, $item->paintseed);
    }

    public function testNativeXorKeyPaintwear(): void
    {
        $item = InspectLink::deserialize(self::NATIVE_HEX);
        $this->assertEqualsWithDelta(0.04121, $item->paintwear, 0.0001);
    }

    public function testNativeXorKeyRarity(): void
    {
        $item = InspectLink::deserialize(self::NATIVE_HEX);
        $this->assertSame(3, $item->rarity);
    }

    public function testNativeXorKeyQuality(): void
    {
        $item = InspectLink::deserialize(self::NATIVE_HEX);
        $this->assertSame(4, $item->quality);
    }

    public function testNativeStickerCount(): void
    {
        $item = InspectLink::deserialize(self::NATIVE_HEX);
        $this->assertCount(5, $item->stickers);
    }

    public function testNativeStickerIds(): void
    {
        $item = InspectLink::deserialize(self::NATIVE_HEX);
        $ids = array_map(fn (Sticker $s) => $s->stickerId, $item->stickers);
        $this->assertSame([7436, 5144, 6970, 8069, 5592], $ids);
    }

    public function testToolHexKeyZeroDefindex(): void
    {
        $item = InspectLink::deserialize(self::TOOL_HEX);
        $this->assertSame(60, $item->defindex);
    }

    public function testToolHexKeyZeroPaintindex(): void
    {
        $item = InspectLink::deserialize(self::TOOL_HEX);
        $this->assertSame(440, $item->paintindex);
    }

    public function testToolHexKeyZeroPaintseed(): void
    {
        $item = InspectLink::deserialize(self::TOOL_HEX);
        $this->assertSame(353, $item->paintseed);
    }

    public function testToolHexKeyZeroPaintwear(): void
    {
        $item = InspectLink::deserialize(self::TOOL_HEX);
        $this->assertEqualsWithDelta(0.005411375779658556, $item->paintwear, 1e-7);
    }

    public function testToolHexKeyZeroRarity(): void
    {
        $item = InspectLink::deserialize(self::TOOL_HEX);
        $this->assertSame(5, $item->rarity);
    }

    public function testLowercaseHex(): void
    {
        $item = InspectLink::deserialize(strtolower(self::TOOL_HEX));
        $this->assertSame(60, $item->defindex);
    }

    public function testAcceptsSteamUrl(): void
    {
        $url = 'steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20A' . self::TOOL_HEX;
        $item = InspectLink::deserialize($url);
        $this->assertSame(60, $item->defindex);
    }

    public function testAcceptsCsgoStyleUrl(): void
    {
        $url = 'csgo://rungame/730/76561202255233023/+csgo_econ_action_preview A' . self::TOOL_HEX;
        $item = InspectLink::deserialize($url);
        $this->assertSame(60, $item->defindex);
    }

    public function testPayloadTooShortThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InspectLink::deserialize('0000');
    }

    // -----------------------------------------------------------------------
    // Serialize tests
    // -----------------------------------------------------------------------

    public function testKnownHexOutput(): void
    {
        $data = new ItemPreviewData(
            defindex: 60,
            paintindex: 440,
            paintseed: 353,
            paintwear: 0.005411375779658556,
            rarity: 5,
        );
        $this->assertSame(self::TOOL_HEX, InspectLink::serialize($data));
    }

    public function testReturnsUppercase(): void
    {
        $data = new ItemPreviewData(defindex: 1);
        $result = InspectLink::serialize($data);
        $this->assertSame(strtoupper($result), $result);
    }

    public function testStartsWithDoubleZero(): void
    {
        $data = new ItemPreviewData(defindex: 1);
        $this->assertStringStartsWith('00', InspectLink::serialize($data));
    }

    // -----------------------------------------------------------------------
    // Round-trip tests
    // -----------------------------------------------------------------------

    private function roundtrip(ItemPreviewData $data): ItemPreviewData
    {
        return InspectLink::deserialize(InspectLink::serialize($data));
    }

    public function testRoundtripDefindex(): void
    {
        $this->assertSame(7, $this->roundtrip(new ItemPreviewData(defindex: 7))->defindex);
    }

    public function testRoundtripPaintindex(): void
    {
        $this->assertSame(422, $this->roundtrip(new ItemPreviewData(paintindex: 422))->paintindex);
    }

    public function testRoundtripPaintseed(): void
    {
        $this->assertSame(999, $this->roundtrip(new ItemPreviewData(paintseed: 999))->paintseed);
    }

    public function testRoundtripPaintwear(): void
    {
        // float32 round-trip precision
        $original = 0.123456789;
        $packed = unpack('f', pack('f', $original));
        $expected = (float) $packed[1];
        $result = $this->roundtrip(new ItemPreviewData(paintwear: $original));
        $this->assertEqualsWithDelta($expected, $result->paintwear, 1e-7);
    }

    public function testRoundtripItemidLarge(): void
    {
        $this->assertSame(46876117973, $this->roundtrip(new ItemPreviewData(itemid: 46876117973))->itemid);
    }

    public function testRoundtripStickers(): void
    {
        $data = new ItemPreviewData(
            defindex: 7,
            stickers: [
                new Sticker(slot: 0, stickerId: 7436),
                new Sticker(slot: 1, stickerId: 5144),
            ],
        );
        $result = $this->roundtrip($data);
        $this->assertCount(2, $result->stickers);
        $this->assertSame(7436, $result->stickers[0]->stickerId);
        $this->assertSame(5144, $result->stickers[1]->stickerId);
    }

    public function testRoundtripStickerSlots(): void
    {
        $data = new ItemPreviewData(stickers: [new Sticker(slot: 3, stickerId: 123)]);
        $result = $this->roundtrip($data);
        $this->assertSame(3, $result->stickers[0]->slot);
    }

    public function testRoundtripStickerWear(): void
    {
        $data = new ItemPreviewData(stickers: [new Sticker(stickerId: 1, wear: 0.5)]);
        $result = $this->roundtrip($data);
        $this->assertNotNull($result->stickers[0]->wear);
        $this->assertEqualsWithDelta(0.5, $result->stickers[0]->wear, 1e-6);
    }

    public function testRoundtripKeychains(): void
    {
        $data = new ItemPreviewData(keychains: [new Sticker(slot: 0, stickerId: 999, pattern: 42)]);
        $result = $this->roundtrip($data);
        $this->assertCount(1, $result->keychains);
        $this->assertSame(999, $result->keychains[0]->stickerId);
        $this->assertSame(42, $result->keychains[0]->pattern);
    }

    public function testRoundtripCustomname(): void
    {
        $data = new ItemPreviewData(defindex: 7, customname: 'My Knife');
        $this->assertSame('My Knife', $this->roundtrip($data)->customname);
    }

    public function testRoundtripRarityQuality(): void
    {
        $data = new ItemPreviewData(rarity: 6, quality: 9);
        $result = $this->roundtrip($data);
        $this->assertSame(6, $result->rarity);
        $this->assertSame(9, $result->quality);
    }

    public function testRoundtripFullItem(): void
    {
        $data = new ItemPreviewData(
            itemid: 46876117973,
            defindex: 7,
            paintindex: 422,
            rarity: 3,
            quality: 4,
            paintwear: 0.04121,
            paintseed: 922,
            stickers: [
                new Sticker(slot: 0, stickerId: 7436),
                new Sticker(slot: 1, stickerId: 5144),
                new Sticker(slot: 2, stickerId: 6970),
                new Sticker(slot: 3, stickerId: 8069),
                new Sticker(slot: 4, stickerId: 5592),
            ],
        );
        $result = $this->roundtrip($data);
        $this->assertSame(7, $result->defindex);
        $this->assertSame(422, $result->paintindex);
        $this->assertSame(922, $result->paintseed);
        $this->assertCount(5, $result->stickers);
        $ids = array_map(fn (Sticker $s) => $s->stickerId, $result->stickers);
        $this->assertSame([7436, 5144, 6970, 8069, 5592], $ids);
    }

    // -----------------------------------------------------------------------
    // Hybrid URL format tests
    // -----------------------------------------------------------------------

    private const HYBRID_URL = 'steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20S76561199323320483A50075495125D1101C4C4FCD4AB10092D31B8143914211829A1FAE3FD125119591141117308191301EA550C1111912E3C111151D12C413E6BAC54D1D29BAD731E191501B92C2C9B6BF92F5411C25B2A731E191501B92C2CEA2B182E5411F7212A731E191501B92C2C4F89C12F549164592A799713611956F4339F';

    private const CLASSIC_URL = 'steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20S76561199842063946A49749521570D2751293026650298712';

    public function testIsMaskedReturnsTrueForPureHexPayload(): void
    {
        $url = 'steam://run/730//+csgo_econ_action_preview%20' . self::TOOL_HEX;
        $this->assertTrue(InspectLink::isMasked($url));
    }

    public function testIsMaskedReturnsTrueForFullMaskedUrl(): void
    {
        $url = 'steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20' . self::NATIVE_HEX;
        $this->assertTrue(InspectLink::isMasked($url));
    }

    public function testIsMaskedReturnsTrueForHybridUrl(): void
    {
        $this->assertTrue(InspectLink::isMasked(self::HYBRID_URL));
    }

    public function testIsMaskedReturnsFalseForClassicUrl(): void
    {
        $this->assertFalse(InspectLink::isMasked(self::CLASSIC_URL));
    }

    public function testIsClassicReturnsTrueForClassicUrl(): void
    {
        $this->assertTrue(InspectLink::isClassic(self::CLASSIC_URL));
    }

    public function testIsClassicReturnsFalseForMaskedUrl(): void
    {
        $url = 'steam://run/730//+csgo_econ_action_preview%20' . self::TOOL_HEX;
        $this->assertFalse(InspectLink::isClassic($url));
    }

    public function testIsClassicReturnsFalseForHybridUrl(): void
    {
        $this->assertFalse(InspectLink::isClassic(self::HYBRID_URL));
    }

    public function testDeserializeHybridUrlReturnsCorrectItemid(): void
    {
        $item = InspectLink::deserialize(self::HYBRID_URL);
        $this->assertSame(50075495125, $item->itemid);
    }

    // -----------------------------------------------------------------------
    // Regression: hex payload starting with 'A' (key byte = 0xAx)
    // -----------------------------------------------------------------------

    /**
     * Three real-world masked links whose first hex digit is 'A' (XOR key = 0xA4 or 0xA6).
     * Previously, extractHex() wrongly treated the 'A' as the classic asset-ID
     * prefix marker and stripped it, producing an odd-length hex string which
     * caused hex2bin to return false / an InvalidArgumentException.
     *
     *   A_START_HEX_1  key=0xA6, longer payload  → defindex=34  (M4A1-S)
     *   A_START_HEX_2  key=0xA4, shorter payload → defindex=4676
     *   A_START_HEX_3  key=0xA6, shorter payload → defindex=1377
     */
    private const A_START_HEX_1 = 'A6B6710C51510DA7BE848628A18EA396A29E1C181D56A5E682CEE8D6AEE7BC380F';
    private const A_START_HEX_2 = 'A4B4725C7B1EE6BC608084A48CA294A0CC9CD4AD3EDF347E';
    private const A_START_HEX_3 = 'A6B617190F659DBE47AC86A68EA096A2CEB4D6AFBFFA9FD2';

    /** @return array<string, array{string, int}> */
    public static function aStartingPayloadProvider(): array
    {
        return [
            'key=0xA6 long'  => [self::A_START_HEX_1, 34],
            'key=0xA4 short' => [self::A_START_HEX_2, 4676],
            'key=0xA6 short' => [self::A_START_HEX_3, 1377],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('aStartingPayloadProvider')]
    public function testIsMaskedReturnsTrueForAStartingPayload(string $hex, int $_defindex): void
    {
        $url = 'steam://run/730//+csgo_econ_action_preview%20' . $hex;
        $this->assertTrue(InspectLink::isMasked($url));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('aStartingPayloadProvider')]
    public function testDeserializeAStartingBareHex(string $hex, int $defindex): void
    {
        $this->assertSame($defindex, InspectLink::deserialize($hex)->defindex);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('aStartingPayloadProvider')]
    public function testDeserializeAStartingSteamRunUrl(string $hex, int $defindex): void
    {
        $url = 'steam://run/730//+csgo_econ_action_preview%20' . $hex;
        $this->assertSame($defindex, InspectLink::deserialize($url)->defindex);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('aStartingPayloadProvider')]
    public function testDeserializeAStartingSteamRungameUrl(string $hex, int $defindex): void
    {
        $url = 'steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20' . $hex;
        $this->assertSame($defindex, InspectLink::deserialize($url)->defindex);
    }

    // -----------------------------------------------------------------------
    // Checksum correctness
    // -----------------------------------------------------------------------

    public function testKnownHexChecksumMatches(): void
    {
        $data = new ItemPreviewData(
            defindex: 60,
            paintindex: 440,
            paintseed: 353,
            paintwear: 0.005411375779658556,
            rarity: 5,
        );
        $this->assertSame(self::TOOL_HEX, InspectLink::serialize($data));
    }

    // -----------------------------------------------------------------------
    // Validation: payload length, proto field count, value ranges
    // -----------------------------------------------------------------------

    public function testDeserializePayloadTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InspectLink::deserialize(str_repeat('AB', 2049)); // 4098 chars > 4096
    }

    public function testSerializePaintwearAboveOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InspectLink::serialize(new ItemPreviewData(paintwear: 1.5));
    }

    public function testSerializePaintwearBelowZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InspectLink::serialize(new ItemPreviewData(paintwear: -0.1));
    }

    public function testSerializePaintwearBoundaryValid(): void
    {
        // 0.0 and 1.0 are valid wear values
        $this->assertStringStartsWith('00', InspectLink::serialize(new ItemPreviewData(paintwear: 0.0)));
        $this->assertStringStartsWith('00', InspectLink::serialize(new ItemPreviewData(paintwear: 1.0)));
    }

    public function testSerializeCustomnameTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InspectLink::serialize(new ItemPreviewData(customname: str_repeat('A', 101)));
    }

    public function testSerializeCustomnameMaxLength(): void
    {
        // exactly 100 chars is allowed
        $result = InspectLink::serialize(new ItemPreviewData(customname: str_repeat('A', 100)));
        $this->assertStringStartsWith('00', $result);
    }

    // -----------------------------------------------------------------------
    // New test vectors from csfloat/cs-inspect-serializer gen.test.ts
    // -----------------------------------------------------------------------

    /** CSFloat vector A — no stickers, paintwear ≈ 0.6337 */
    private const CSFLOAT_A = '00180720DA03280638FBEE88F90340B2026BC03C96';

    /** CSFloat vector B — 4 stickers id=76 each, paintwear ≈ 0.99 */
    private const CSFLOAT_B = '00180720C80A280638A4E1F5FB03409A0562040800104C62040801104C62040802104C62040803104C6D4F5E30';

    /** CSFloat vector C — keychain item (defindex=1355), highlight_reel=345 */
    private const CSFLOAT_C = 'A2B2A2BA69A882A28AA192AECAA2D2B700A3A5AAA2B286FA7BA0D684BE72';

    public function testCsfloatA_Defindex(): void
    {
        $this->assertSame(7, InspectLink::deserialize(self::CSFLOAT_A)->defindex);
    }

    public function testCsfloatA_Paintindex(): void
    {
        $this->assertSame(474, InspectLink::deserialize(self::CSFLOAT_A)->paintindex);
    }

    public function testCsfloatA_Paintseed(): void
    {
        $this->assertSame(306, InspectLink::deserialize(self::CSFLOAT_A)->paintseed);
    }

    public function testCsfloatA_Rarity(): void
    {
        $this->assertSame(6, InspectLink::deserialize(self::CSFLOAT_A)->rarity);
    }

    public function testCsfloatA_Paintwear(): void
    {
        $item = InspectLink::deserialize(self::CSFLOAT_A);
        $this->assertNotNull($item->paintwear);
        $this->assertEqualsWithDelta(0.6337, $item->paintwear, 0.001);
    }

    public function testCsfloatB_StickerCount(): void
    {
        $this->assertCount(4, InspectLink::deserialize(self::CSFLOAT_B)->stickers);
    }

    public function testCsfloatB_StickerIds(): void
    {
        $stickers = InspectLink::deserialize(self::CSFLOAT_B)->stickers;
        foreach ($stickers as $s) {
            $this->assertSame(76, $s->stickerId);
        }
    }

    public function testCsfloatB_Paintindex(): void
    {
        $this->assertSame(1352, InspectLink::deserialize(self::CSFLOAT_B)->paintindex);
    }

    public function testCsfloatB_Paintwear(): void
    {
        $item = InspectLink::deserialize(self::CSFLOAT_B);
        $this->assertNotNull($item->paintwear);
        $this->assertEqualsWithDelta(0.99, $item->paintwear, 0.001);
    }

    public function testCsfloatC_Defindex(): void
    {
        $this->assertSame(1355, InspectLink::deserialize(self::CSFLOAT_C)->defindex);
    }

    public function testCsfloatC_Quality(): void
    {
        $this->assertSame(12, InspectLink::deserialize(self::CSFLOAT_C)->quality);
    }

    public function testCsfloatC_KeychainHighlightReel(): void
    {
        $keychains = InspectLink::deserialize(self::CSFLOAT_C)->keychains;
        $this->assertCount(1, $keychains);
        $this->assertSame(345, $keychains[0]->highlightReel);
    }

    public function testCsfloatC_NoPaintwear(): void
    {
        // keychain items have no wear — field 7 not present in proto
        $this->assertNull(InspectLink::deserialize(self::CSFLOAT_C)->paintwear);
    }

    // -----------------------------------------------------------------------
    // Roundtrip: highlight_reel and nullable paintwear
    // -----------------------------------------------------------------------

    public function testRoundtrip_HighlightReel(): void
    {
        $data = new ItemPreviewData(
            defindex: 7,
            keychains: [new Sticker(slot: 0, stickerId: 36, highlightReel: 345)],
        );
        $result = InspectLink::deserialize(InspectLink::serialize($data));
        $this->assertCount(1, $result->keychains);
        $this->assertSame(345, $result->keychains[0]->highlightReel);
    }

    public function testRoundtrip_NullPaintwear(): void
    {
        $data = new ItemPreviewData(defindex: 7, paintwear: null);
        $result = InspectLink::deserialize(InspectLink::serialize($data));
        $this->assertNull($result->paintwear);
    }

    public function testSerialize_NullPaintwearProducesFewerBytes(): void
    {
        // An item with null paintwear omits field 7 entirely; a non-zero float value writes it.
        // (0.0 also omits field 7 due to proto3 default-value skipping, so compare against 0.5.)
        $withNull = InspectLink::serialize(new ItemPreviewData(defindex: 7, paintwear: null));
        $withFloat = InspectLink::serialize(new ItemPreviewData(defindex: 7, paintwear: 0.5));
        $this->assertLessThan(strlen($withFloat), strlen($withNull));
    }

    // -----------------------------------------------------------------------
    // Sticker Slab test vectors (defIndex=1355, quality=8, sticker slab variant via paintKit)
    // -----------------------------------------------------------------------

    /** Sticker Slab URL A — rarity=5, keychains[0].stickerId=37, keychains[0].paintKit=7256 */
    private const SLAB_URL_A = 'steam://run/730//+csgo_econ_action_preview%20918191895A9BB191B994A199F991E191339096999181B4F149A98D5C0889';

    /** Sticker Slab URL B — rarity=3, keychains[0].stickerId=37, keychains[0].paintKit=275 */
    private const SLAB_URL_B = 'steam://run/730//+csgo_econ_action_preview%20CBDBCBD300C1EBCBE3C8FBC3A3CBBBCB69CACCC3CBDBEEAB58C9B8B67C83';

    public function testSlabA_Defindex(): void
    {
        $this->assertSame(1355, InspectLink::deserialize(self::SLAB_URL_A)->defindex);
    }

    public function testSlabA_Quality(): void
    {
        $this->assertSame(8, InspectLink::deserialize(self::SLAB_URL_A)->quality);
    }

    public function testSlabA_Rarity(): void
    {
        $this->assertSame(5, InspectLink::deserialize(self::SLAB_URL_A)->rarity);
    }

    public function testSlabA_KeychainStickerId(): void
    {
        $keychains = InspectLink::deserialize(self::SLAB_URL_A)->keychains;
        $this->assertCount(1, $keychains);
        $this->assertSame(37, $keychains[0]->stickerId);
    }

    public function testSlabA_KeychainPaintKit(): void
    {
        $keychains = InspectLink::deserialize(self::SLAB_URL_A)->keychains;
        $this->assertCount(1, $keychains);
        $this->assertSame(7256, $keychains[0]->paintKit);
    }

    public function testSlabB_Defindex(): void
    {
        $this->assertSame(1355, InspectLink::deserialize(self::SLAB_URL_B)->defindex);
    }

    public function testSlabB_Quality(): void
    {
        $this->assertSame(8, InspectLink::deserialize(self::SLAB_URL_B)->quality);
    }

    public function testSlabB_Rarity(): void
    {
        $this->assertSame(3, InspectLink::deserialize(self::SLAB_URL_B)->rarity);
    }

    public function testSlabB_KeychainStickerId(): void
    {
        $keychains = InspectLink::deserialize(self::SLAB_URL_B)->keychains;
        $this->assertCount(1, $keychains);
        $this->assertSame(37, $keychains[0]->stickerId);
    }

    public function testSlabB_KeychainPaintKit(): void
    {
        $keychains = InspectLink::deserialize(self::SLAB_URL_B)->keychains;
        $this->assertCount(1, $keychains);
        $this->assertSame(275, $keychains[0]->paintKit);
    }

    public function testRoundtrip_PaintKit(): void
    {
        $data = new ItemPreviewData(
            defindex: 1355,
            quality: 8,
            rarity: 5,
            keychains: [new Sticker(slot: 0, stickerId: 37, paintKit: 7256)],
        );
        $result = InspectLink::deserialize(InspectLink::serialize($data));
        $this->assertCount(1, $result->keychains);
        $this->assertSame(37, $result->keychains[0]->stickerId);
        $this->assertSame(7256, $result->keychains[0]->paintKit);
    }
}
