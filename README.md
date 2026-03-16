# cs2-masked-inspect (PHP)

Pure PHP 8.2+ library for encoding and decoding CS2 masked inspect links — no runtime dependencies.

## Installation

```bash
composer require vlydev/cs2-masked-inspect
```

## Usage

### Deserialize a CS2 inspect link

```php
use VlyDev\Steam\InspectLink;

// Accepts a full steam:// URL or a raw hex string
$item = InspectLink::deserialize(
    'steam://run/730//+csgo_econ_action_preview%20E3F3367440334DE2FBE4C345E0CBE0D3...'
);

echo $item->defindex;   // 7  (AK-47)
echo $item->paintindex; // 422
echo $item->paintseed;  // 922
echo $item->paintwear;  // ~0.04121
echo $item->itemid;     // 46876117973

foreach ($item->stickers as $s) {
    echo $s->stickerId; // 7436, 5144, 6970, 8069, 5592
}
```

### Serialize an item to a hex payload

```php
use VlyDev\Steam\InspectLink;
use VlyDev\Steam\ItemPreviewData;

$data = new ItemPreviewData(
    defindex:   60,
    paintindex: 440,
    paintseed:  353,
    paintwear:  0.005411375779658556,
    rarity:     5,
);

$hex = InspectLink::serialize($data);
// 00183C20B803280538E9A3C5DD0340E102C246A0D1

$url = "steam://run/730//+csgo_econ_action_preview%20{$hex}";
```

### Item with stickers and keychains

```php
use VlyDev\Steam\InspectLink;
use VlyDev\Steam\ItemPreviewData;
use VlyDev\Steam\Sticker;

$data = new ItemPreviewData(
    defindex:   7,
    paintindex: 422,
    paintseed:  922,
    paintwear:  0.04121,
    rarity:     3,
    quality:    4,
    stickers: [
        new Sticker(slot: 0, stickerId: 7436),
        new Sticker(slot: 1, stickerId: 5144, wear: 0.1),
    ],
);

$hex = InspectLink::serialize($data);
$decoded = InspectLink::deserialize($hex); // round-trip
```

---

## Validation

Use `isMasked()` and `isClassic()` to detect the link type without attempting to decode it.

```php
use VlyDev\Steam\InspectLink;

// New masked format (pure hex blob) — can be decoded offline
$maskedUrl = 'steam://run/730//+csgo_econ_action_preview%20E3F3...';
InspectLink::isMasked($maskedUrl);   // true
InspectLink::isClassic($maskedUrl);  // false

// Hybrid format (S/A/D prefix with hex proto after D) — also decodable offline
$hybridUrl = 'steam://rungame/730/.../+csgo_econ_action_preview%20S76561199323320483A50075495125D1101C4C4FCD4AB10...';
InspectLink::isMasked($hybridUrl);   // true
InspectLink::isClassic($hybridUrl);  // false

// Classic format — requires Steam Game Coordinator to fetch item info
$classicUrl = 'steam://rungame/730/.../+csgo_econ_action_preview%20S76561199842063946A49749521570D2751293026650298712';
InspectLink::isMasked($classicUrl);  // false
InspectLink::isClassic($classicUrl); // true
```

---

## Validation rules

`deserialize()` enforces:

| Rule | Limit | Exception |
|------|-------|-----------|
| Hex payload length | max 4,096 characters | `InvalidArgumentException` |
| Protobuf field count | max 100 per message | `OverflowException` |

`serialize()` enforces:

| Field | Constraint | Exception |
|-------|-----------|-----------|
| `paintwear` | `[0.0, 1.0]` | `InvalidArgumentException` |
| `customname` | max 100 characters | `InvalidArgumentException` |

---

## How the format works

Three URL formats are handled:

1. **New masked format** — pure hex blob after `csgo_econ_action_preview`:
   ```
   steam://run/730//+csgo_econ_action_preview%20<hexbytes>
   ```

2. **Hybrid format** — old-style `S/A/D` prefix, but with a hex proto appended after `D` (instead of a decimal did):
   ```
   steam://rungame/730/.../+csgo_econ_action_preview%20S<steamid>A<assetid>D<hexproto>
   ```

3. **Classic format** — old-style `S/A/D` with a decimal did; requires Steam GC to resolve item details.

For formats 1 and 2 the library decodes the item offline. For format 3 only URL parsing is possible.

The hex blob (formats 1 and 2) has the following binary layout:

```
[key_byte] [proto_bytes XOR'd with key] [4-byte checksum XOR'd with key]
```

| Section | Size | Description |
|---------|------|-------------|
| `key_byte` | 1 byte | XOR key. `0x00` = no obfuscation (tool links). Other values = native CS2 links. |
| `proto_bytes` | variable | `CEconItemPreviewDataBlock` protobuf, each byte XOR'd with `key_byte`. |
| `checksum` | 4 bytes | Big-endian uint32, XOR'd with `key_byte`. |

### Checksum algorithm

```php
$buffer   = "\x00" . $protoBytes;
$crc      = crc32($buffer) & 0xFFFFFFFF;
$xored    = (($crc & 0xFFFF) ^ (strlen($protoBytes) * $crc)) & 0xFFFFFFFF;
$checksum = pack('N', $xored); // big-endian uint32
```

### `paintwear` encoding

`paintwear` is stored as a `uint32` varint whose bit pattern is the IEEE 754 representation
of a `float32`. The library handles this transparently — callers always work with PHP `float` values.

---

## Proto field reference

### CEconItemPreviewDataBlock

| Field | Number | Type | Description |
|-------|--------|------|-------------|
| `accountid` | 1 | uint32 | Steam account ID (often 0) |
| `itemid` | 2 | uint64 | Item ID in the owner's inventory |
| `defindex` | 3 | uint32 | Item definition index (weapon type) |
| `paintindex` | 4 | uint32 | Skin paint index |
| `rarity` | 5 | uint32 | Item rarity |
| `quality` | 6 | uint32 | Item quality |
| `paintwear` | 7 | uint32* | float32 reinterpreted as uint32 |
| `paintseed` | 8 | uint32 | Pattern seed (0–1000) |
| `killeaterscoretype` | 9 | uint32 | StatTrak counter type |
| `killeatervalue` | 10 | uint32 | StatTrak value |
| `customname` | 11 | string | Name tag |
| `stickers` | 12 | repeated Sticker | Applied stickers |
| `inventory` | 13 | uint32 | Inventory flags |
| `origin` | 14 | uint32 | Origin |
| `questid` | 15 | uint32 | Quest ID |
| `dropreason` | 16 | uint32 | Drop reason |
| `musicindex` | 17 | uint32 | Music kit index |
| `entindex` | 18 | int32 | Entity index |
| `petindex` | 19 | uint32 | Pet index |
| `keychains` | 20 | repeated Sticker | Applied keychains |

### Sticker

| Field | Number | Type | Description |
|-------|--------|------|-------------|
| `slot` | 1 | uint32 | Slot position |
| `sticker_id` | 2 | uint32 | Sticker definition ID |
| `wear` | 3 | float32 | Wear (fixed32) |
| `scale` | 4 | float32 | Scale (fixed32) |
| `rotation` | 5 | float32 | Rotation (fixed32) |
| `tint_id` | 6 | uint32 | Tint |
| `offset_x` | 7 | float32 | X offset (fixed32) |
| `offset_y` | 8 | float32 | Y offset (fixed32) |
| `offset_z` | 9 | float32 | Z offset (fixed32) |
| `pattern` | 10 | uint32 | Pattern (keychains) |

---

## Known test vectors

### Vector 1 — Native CS2 link (XOR key 0xE3)

```
E3F3367440334DE2FBE4C345E0CBE0D3E7DB6943400AE0A379E481ECEBE2F36F
D9DE2BDB515EA6E30D74D981ECEBE3F37BCBDE640D475DA6E35EFCD881ECEBE3
F359D5DE37E9D75DA6436DD3DD81ECEBE3F366DCDE3F8F9BDDA69B43B6DE81EC
EBE3F33BC8DEBB1CA3DFA623F7DDDF8B71E293EBFD43382B
```

| Field | Value |
|-------|-------|
| `itemid` | `46876117973` |
| `defindex` | `7` (AK-47) |
| `paintindex` | `422` |
| `paintseed` | `922` |
| `paintwear` | `≈ 0.04121` |
| `rarity` | `3` |
| `quality` | `4` |
| sticker IDs | `[7436, 5144, 6970, 8069, 5592]` |

### Vector 2 — Tool-generated link (key 0x00)

```php
new ItemPreviewData(defindex: 60, paintindex: 440, paintseed: 353,
                    paintwear: 0.005411375779658556, rarity: 5)
```

Expected hex:

```
00183C20B803280538E9A3C5DD0340E102C246A0D1
```

---

## Running tests

```bash
composer install
composer test
```

---

## Contributing

Bug reports and pull requests are welcome on [GitHub](https://github.com/vlydev/cs2-masked-inspect-php).

1. Fork the repository
2. Create a branch: `git checkout -b my-fix`
3. Make your changes and add tests
4. Ensure all tests pass: `composer test`
5. Open a Pull Request

All PRs require the CI checks to pass before merging.

---

## Author

[VlyDev](https://github.com/vlydev) — vladdnepr1989@gmail.com

---

## License

MIT © [VlyDev](https://github.com/vlydev)
