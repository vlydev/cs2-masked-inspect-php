<?php

declare(strict_types=1);

use VlyDev\Steam\GenCode;
use VlyDev\Steam\ItemPreviewData;
use VlyDev\Steam\Sticker;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GenCode utilities.
 */
class GenCodeTest extends TestCase
{
    // -----------------------------------------------------------------------
    // toGenCode — basic
    // -----------------------------------------------------------------------

    public function testToGenCodeMinimal(): void
    {
        $item = new ItemPreviewData(defindex: 7, paintindex: 474, paintseed: 306, paintwear: 0.22540508);
        $code = GenCode::toGenCode($item);
        $this->assertStringStartsWith('!gen ', $code);
        $this->assertStringContainsString('7 474 306 0.22540508', $code);
    }

    public function testToGenCodeDefaultPrefix(): void
    {
        $item = new ItemPreviewData(defindex: 7, paintindex: 474, paintseed: 306, paintwear: 0.22540508);
        $code = GenCode::toGenCode($item);
        $this->assertSame('!gen', substr($code, 0, 4));
    }

    public function testToGenCodeCustomPrefix(): void
    {
        $item = new ItemPreviewData(defindex: 7, paintindex: 474, paintseed: 306, paintwear: 0.22540508);
        $code = GenCode::toGenCode($item, '!g');
        $this->assertStringStartsWith('!g ', $code);
    }

    public function testToGenCodeEmptyPrefix(): void
    {
        $item = new ItemPreviewData(defindex: 7, paintindex: 474, paintseed: 306, paintwear: 0.22540508);
        $code = GenCode::toGenCode($item, '');
        $this->assertStringStartsWith('7 ', $code);
    }

    public function testToGenCodeNullWearBecomesZero(): void
    {
        $item = new ItemPreviewData(defindex: 7, paintindex: 474, paintseed: 306, paintwear: null);
        $code = GenCode::toGenCode($item);
        // 4th token should be "0"
        $tokens = explode(' ', $code);
        $this->assertSame('0', $tokens[4]); // tokens[0]=!gen, [1]=defindex, [2]=pi, [3]=ps, [4]=pw
    }

    // -----------------------------------------------------------------------
    // toGenCode — float formatting
    // -----------------------------------------------------------------------

    public function testToGenCodeFloatStripsTrailingZeros(): void
    {
        $item = new ItemPreviewData(defindex: 7, paintindex: 474, paintseed: 306, paintwear: 0.5);
        $code = GenCode::toGenCode($item);
        $tokens = explode(' ', $code);
        $this->assertSame('0.5', $tokens[4]);
    }

    public function testToGenCodeFloatZeroValue(): void
    {
        $item = new ItemPreviewData(defindex: 7, paintindex: 474, paintseed: 306, paintwear: 0.0);
        $code = GenCode::toGenCode($item);
        $tokens = explode(' ', $code);
        $this->assertSame('0', $tokens[4]);
    }

    public function testToGenCodeFloat8DecimalPlaces(): void
    {
        $item = new ItemPreviewData(defindex: 7, paintindex: 474, paintseed: 306, paintwear: 0.22540508);
        $code = GenCode::toGenCode($item);
        $tokens = explode(' ', $code);
        $this->assertSame('0.22540508', $tokens[4]);
    }

    // -----------------------------------------------------------------------
    // toGenCode — sticker padding
    // -----------------------------------------------------------------------

    public function testToGenCodeAlwaysPadsStickerTo5Slots(): void
    {
        $item = new ItemPreviewData(
            defindex: 7,
            paintindex: 474,
            paintseed: 306,
            paintwear: 0.22540508,
            stickers: [new Sticker(slot: 2, stickerId: 7203)],
        );
        $code = GenCode::toGenCode($item);
        $tokens = explode(' ', $code);
        // !gen + 4 base + 10 sticker tokens = 15 tokens total
        $this->assertCount(15, $tokens);
        // slot 0: 0 0, slot 1: 0 0, slot 2: 7203 0, slot 3: 0 0, slot 4: 0 0
        // tokens: [0]=!gen [1]=7 [2]=474 [3]=306 [4]=0.22540508 [5]=0 [6]=0 [7]=0 [8]=0 [9]=7203 [10]=0 [11]=0 [12]=0 [13]=0 [14]=0
        $this->assertSame('0', $tokens[5]);
        $this->assertSame('0', $tokens[6]);
        $this->assertSame('0', $tokens[7]);
        $this->assertSame('0', $tokens[8]);
        $this->assertSame('7203', $tokens[9]);
        $this->assertSame('0', $tokens[10]);
    }

    public function testToGenCodeStickerWearFormatted(): void
    {
        $item = new ItemPreviewData(
            defindex: 7,
            paintindex: 474,
            paintseed: 306,
            paintwear: 0.22540508,
            stickers: [new Sticker(slot: 0, stickerId: 7203, wear: 0.5)],
        );
        $code = GenCode::toGenCode($item);
        $tokens = explode(' ', $code);
        $this->assertSame('7203', $tokens[5]);
        $this->assertSame('0.5', $tokens[6]);
    }

    public function testToGenCodeNoStickersHas5EmptySlots(): void
    {
        $item = new ItemPreviewData(defindex: 7, paintindex: 474, paintseed: 306, paintwear: 0.5);
        $code = GenCode::toGenCode($item);
        $tokens = explode(' ', $code);
        // !gen + 4 + 10 = 15 tokens
        $this->assertCount(15, $tokens);
        for ($i = 5; $i <= 14; $i++) {
            $this->assertSame('0', $tokens[$i]);
        }
    }

    // -----------------------------------------------------------------------
    // toGenCode — keychains (no padding)
    // -----------------------------------------------------------------------

    public function testToGenCodeKeychainAppendedAfterStickers(): void
    {
        $item = new ItemPreviewData(
            defindex: 7,
            paintindex: 474,
            paintseed: 306,
            paintwear: 0.22540508,
            keychains: [new Sticker(slot: 0, stickerId: 36)],
        );
        $code = GenCode::toGenCode($item);
        $tokens = explode(' ', $code);
        // !gen + 4 base + 10 sticker + 2 keychain = 17
        $this->assertCount(17, $tokens);
        $this->assertSame('36', $tokens[15]);
        $this->assertSame('0', $tokens[16]);
    }

    public function testToGenCodeKeychainNotPadded(): void
    {
        $item = new ItemPreviewData(
            defindex: 7,
            paintindex: 474,
            paintseed: 306,
            paintwear: 0.22540508,
            keychains: [new Sticker(slot: 2, stickerId: 36)],
        );
        $code = GenCode::toGenCode($item);
        $tokens = explode(' ', $code);
        // Only slot 2 keychain, not padded to 3 slots
        $this->assertCount(17, $tokens);
    }

    // -----------------------------------------------------------------------
    // parseGenCode — basic
    // -----------------------------------------------------------------------

    public function testParseGenCodeMinimal(): void
    {
        $item = GenCode::parseGenCode('!gen 7 474 306 0.22540508');
        $this->assertSame(7, $item->defindex);
        $this->assertSame(474, $item->paintindex);
        $this->assertSame(306, $item->paintseed);
        $this->assertEqualsWithDelta(0.22540508, $item->paintwear, 1e-7);
    }

    public function testParseGenCodeWithoutPrefix(): void
    {
        $item = GenCode::parseGenCode('7 474 306 0.22540508');
        $this->assertSame(7, $item->defindex);
        $this->assertSame(474, $item->paintindex);
    }

    public function testParseGenCodeCustomPrefix(): void
    {
        $item = GenCode::parseGenCode('!g 7 474 306 0.22540508');
        $this->assertSame(7, $item->defindex);
    }

    public function testParseGenCodeTooFewTokensThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GenCode::parseGenCode('!gen 7 474 306');
    }

    public function testParseGenCodeTooFewTokensNoPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GenCode::parseGenCode('7 474 306');
    }

    // -----------------------------------------------------------------------
    // parseGenCode — stickers
    // -----------------------------------------------------------------------

    public function testParseGenCodeStickersFromPaddedSlots(): void
    {
        // slot2 = 7203, others empty
        $item = GenCode::parseGenCode('!gen 7 474 306 0.22540508 0 0 0 0 7203 0 0 0 0 0');
        $this->assertCount(1, $item->stickers);
        $this->assertSame(7203, $item->stickers[0]->stickerId);
        $this->assertSame(2, $item->stickers[0]->slot);
    }

    public function testParseGenCodeMultipleStickers(): void
    {
        $item = GenCode::parseGenCode('!gen 7 474 306 0.22540508 7436 0 5144 0 0 0 0 0 0 0');
        $this->assertCount(2, $item->stickers);
        $ids = array_map(fn(Sticker $s) => $s->stickerId, $item->stickers);
        $this->assertContains(7436, $ids);
        $this->assertContains(5144, $ids);
    }

    public function testParseGenCodeStickerWear(): void
    {
        $item = GenCode::parseGenCode('!gen 7 474 306 0.5 7203 0.25 0 0 0 0 0 0 0 0');
        $this->assertCount(1, $item->stickers);
        $this->assertNotNull($item->stickers[0]->wear);
        $this->assertEqualsWithDelta(0.25, $item->stickers[0]->wear, 1e-6);
    }

    public function testParseGenCodeNoStickersWhenLessThan10Tokens(): void
    {
        $item = GenCode::parseGenCode('7 474 306 0.5');
        $this->assertCount(0, $item->stickers);
    }

    // -----------------------------------------------------------------------
    // parseGenCode — keychains
    // -----------------------------------------------------------------------

    public function testParseGenCodeKeychain(): void
    {
        // 10 sticker tokens + 2 keychain tokens
        $item = GenCode::parseGenCode('7 941 2 0.22540508 0 0 0 0 0 0 0 0 0 0 36 0');
        $this->assertCount(1, $item->keychains);
        $this->assertSame(36, $item->keychains[0]->stickerId);
    }

    // -----------------------------------------------------------------------
    // Round-trip: toGenCode → parseGenCode
    // -----------------------------------------------------------------------

    public function testRoundtripMinimal(): void
    {
        $original = new ItemPreviewData(defindex: 7, paintindex: 474, paintseed: 306, paintwear: 0.22540508);
        $parsed = GenCode::parseGenCode(GenCode::toGenCode($original));
        $this->assertSame($original->defindex, $parsed->defindex);
        $this->assertSame($original->paintindex, $parsed->paintindex);
        $this->assertSame($original->paintseed, $parsed->paintseed);
        $this->assertEqualsWithDelta($original->paintwear, $parsed->paintwear, 1e-6);
    }

    public function testRoundtripWithStickers(): void
    {
        $original = new ItemPreviewData(
            defindex: 7,
            paintindex: 474,
            paintseed: 306,
            paintwear: 0.22540508,
            stickers: [
                new Sticker(slot: 0, stickerId: 7436),
                new Sticker(slot: 3, stickerId: 5144, wear: 0.25),
            ],
        );
        $parsed = GenCode::parseGenCode(GenCode::toGenCode($original));
        $this->assertCount(2, $parsed->stickers);
        $slotMap = [];
        foreach ($parsed->stickers as $s) {
            $slotMap[$s->slot] = $s;
        }
        $this->assertSame(7436, $slotMap[0]->stickerId);
        $this->assertSame(5144, $slotMap[3]->stickerId);
        $this->assertEqualsWithDelta(0.25, $slotMap[3]->wear, 1e-6);
    }

    public function testRoundtripWithKeychain(): void
    {
        $original = new ItemPreviewData(
            defindex: 7,
            paintindex: 474,
            paintseed: 306,
            paintwear: 0.22540508,
            keychains: [new Sticker(slot: 0, stickerId: 36)],
        );
        $parsed = GenCode::parseGenCode(GenCode::toGenCode($original));
        $this->assertCount(1, $parsed->keychains);
        $this->assertSame(36, $parsed->keychains[0]->stickerId);
    }

    // -----------------------------------------------------------------------
    // toGenCode — keychain paintKit
    // -----------------------------------------------------------------------

    public function testToGenCodeKeychainWithPaintKitAppendsPaintKit(): void
    {
        $item = new ItemPreviewData(
            defindex: 1355,
            paintindex: 0,
            paintseed: 0,
            paintwear: 0.0,
            keychains: [new Sticker(slot: 0, stickerId: 37, wear: 0.0, paintKit: 929)],
        );
        $code = GenCode::toGenCode($item, '');
        $tokens = explode(' ', $code);
        // last three tokens should be: 37 0 929
        $this->assertSame('37', $tokens[count($tokens) - 3]);
        $this->assertSame('0', $tokens[count($tokens) - 2]);
        $this->assertSame('929', $tokens[count($tokens) - 1]);
    }

    public function testToGenCodeKeychainWithoutPaintKitNoExtraToken(): void
    {
        $item = new ItemPreviewData(
            defindex: 7,
            paintindex: 0,
            paintseed: 0,
            paintwear: 0.0,
            keychains: [new Sticker(slot: 0, stickerId: 36, wear: 0.0)],
        );
        $code = GenCode::toGenCode($item, '');
        $tokens = explode(' ', $code);
        // last two tokens should be: 36 0
        $this->assertSame('36', $tokens[count($tokens) - 2]);
        $this->assertSame('0', $tokens[count($tokens) - 1]);
    }

    // -----------------------------------------------------------------------
    // genCodeFromLink — sticker slab
    // -----------------------------------------------------------------------

    public function testGenCodeFromLinkSlabUrlEndsWithPaintKit(): void
    {
        $slabUrl = 'steam://run/730//+csgo_econ_action_preview%20819181994A8BA181A982B189E981F181238086898191A4E1208698F309C9';
        $code = GenCode::genCodeFromLink($slabUrl, '');
        $tokens = explode(' ', $code);
        $this->assertSame('37', $tokens[count($tokens) - 3]);
        $this->assertSame('0', $tokens[count($tokens) - 2]);
        $this->assertSame('929', $tokens[count($tokens) - 1]);
    }

    // -----------------------------------------------------------------------
    // genCodeFromLink
    // -----------------------------------------------------------------------

    public function testGenCodeFromLinkFromHex(): void
    {
        $url = GenCode::generate(7, 474, 306, 0.22540508);
        $hex = substr($url, strlen(GenCode::INSPECT_BASE));
        $code = GenCode::genCodeFromLink($hex);
        $this->assertStringStartsWith('!gen 7 474 306', $code);
    }

    public function testGenCodeFromLinkFromFullUrl(): void
    {
        $url = GenCode::generate(7, 474, 306, 0.22540508);
        $code = GenCode::genCodeFromLink($url);
        $this->assertStringStartsWith('!gen 7 474 306', $code);
    }

    // -----------------------------------------------------------------------
    // generate
    // -----------------------------------------------------------------------

    public function testGenerateReturnsSteamUrl(): void
    {
        $url = GenCode::generate(7, 474, 306, 0.22540508);
        $this->assertStringStartsWith('steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20', $url);
    }

    public function testGenerateUrlContainsUppercaseHex(): void
    {
        $url = GenCode::generate(7, 474, 306, 0.22540508);
        $hex = substr($url, strlen(GenCode::INSPECT_BASE));
        $this->assertMatchesRegularExpression('/^[0-9A-F]+$/', $hex);
    }

    public function testGenerateRoundtripViaDeserialize(): void
    {
        $url = GenCode::generate(7, 474, 306, 0.22540508, 6, 0);
        $item = \VlyDev\Steam\InspectLink::deserialize($url);
        $this->assertSame(7, $item->defindex);
        $this->assertSame(474, $item->paintindex);
        $this->assertSame(306, $item->paintseed);
        $this->assertSame(6, $item->rarity);
        $this->assertEqualsWithDelta(0.22540508, $item->paintwear, 1e-6);
    }

    public function testGenerateWithStickers(): void
    {
        $url = GenCode::generate(
            defIndex: 7,
            paintIndex: 474,
            paintSeed: 306,
            paintWear: 0.22540508,
            stickers: [new Sticker(slot: 0, stickerId: 7436)],
        );
        $item = \VlyDev\Steam\InspectLink::deserialize($url);
        $this->assertCount(1, $item->stickers);
        $this->assertSame(7436, $item->stickers[0]->stickerId);
    }

    public function testGenerateWithKeychain(): void
    {
        $url = GenCode::generate(
            defIndex: 7,
            paintIndex: 474,
            paintSeed: 306,
            paintWear: 0.22540508,
            keychains: [new Sticker(slot: 0, stickerId: 36)],
        );
        $item = \VlyDev\Steam\InspectLink::deserialize($url);
        $this->assertCount(1, $item->keychains);
        $this->assertSame(36, $item->keychains[0]->stickerId);
    }

    public function testGenerateMatchesCsfloatVector(): void
    {
        // CSFloat vector A: defindex=7, paintindex=474, paintseed=306, rarity=6, paintwear≈0.6337
        // CSFLOAT_A = '00180720DA03280638FBEE88F90340B2026BC03C96'
        // We round-trip it: deserialize → generate → deserialize
        $knownHex = '00180720DA03280638FBEE88F90340B2026BC03C96';
        $item = \VlyDev\Steam\InspectLink::deserialize($knownHex);
        $url = GenCode::generate(
            defIndex: $item->defindex,
            paintIndex: $item->paintindex,
            paintSeed: $item->paintseed,
            paintWear: $item->paintwear,
            rarity: $item->rarity,
            quality: $item->quality,
        );
        $decoded = \VlyDev\Steam\InspectLink::deserialize($url);
        $this->assertSame($item->defindex, $decoded->defindex);
        $this->assertSame($item->paintindex, $decoded->paintindex);
        $this->assertSame($item->paintseed, $decoded->paintseed);
        $this->assertEqualsWithDelta($item->paintwear, $decoded->paintwear, 1e-6);
    }
}
