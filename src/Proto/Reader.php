<?php

declare(strict_types=1);

namespace VlyDev\Steam\Proto;

/**
 * Pure PHP protobuf binary reader.
 *
 * Implements the subset of wire types needed for CEconItemPreviewDataBlock:
 *   - Wire type 0: varint (uint32, uint64, int32)
 *   - Wire type 2: length-delimited (string, bytes, nested messages)
 *   - Wire type 5: 32-bit fixed (float32)
 */
final class Reader
{
    private int $pos = 0;

    public function __construct(private readonly string $data) {}

    public function getPos(): int
    {
        return $this->pos;
    }

    public function remaining(): int
    {
        return strlen($this->data) - $this->pos;
    }

    /**
     * @throws \UnderflowException on unexpected end of data
     */
    public function readByte(): int
    {
        if ($this->pos >= strlen($this->data)) {
            throw new \UnderflowException('Unexpected end of protobuf data');
        }

        return ord($this->data[$this->pos++]);
    }

    public function readBytes(int $n): string
    {
        if ($this->pos + $n > strlen($this->data)) {
            throw new \UnderflowException(sprintf(
                'Need %d bytes but only %d remain',
                $n,
                strlen($this->data) - $this->pos,
            ));
        }
        $chunk = substr($this->data, $this->pos, $n);
        $this->pos += $n;

        return $chunk;
    }

    /**
     * Read a base-128 varint (up to 10 bytes) as a 64-bit PHP int.
     *
     * uint64 values >= 2^63 (e.g. an itemid with the top bit set) come back
     * as negative ints: same 64 bits, two's complement.
     */
    public function readVarint(): int
    {
        $result = 0;
        $shift = 0;

        do {
            if ($shift > 63) {
                throw new \OverflowException('Varint too long');
            }

            $b = $this->readByte();
            $result |= ($b & 0x7F) << $shift;
            $shift += 7;
        } while ($b & 0x80);

        return $result;
    }

    /**
     * Read tag, return [field_number, wire_type].
     *
     * @return array{0: int, 1: int}
     */
    public function readTag(): array
    {
        $tag = $this->readVarint();

        return [$tag >> 3, $tag & 0x07];
    }

    /**
     * Read a 4-byte little-endian float32 (wire type 5).
     */
    public function readFloat32(): float
    {
        $raw = $this->readBytes(4);
        $unpacked = unpack('f', $raw);

        return (float) $unpacked[1];
    }

    public function readLengthDelimited(): string
    {
        $length = $this->readVarint();

        return $this->readBytes($length);
    }

    /**
     * Read and return all fields until EOF.
     *
     * @return list<array{field: int, wire: int, value: mixed}>
     */
    public function readAllFields(): array
    {
        $fields = [];
        $fieldCount = 0;

        while ($this->remaining() > 0) {
            if (++$fieldCount > 100) {
                throw new \OverflowException('Protobuf field count exceeds limit of 100');
            }

            [$fieldNum, $wireType] = $this->readTag();

            $value = match ($wireType) {
                WireType::VARINT => $this->readVarint(),
                WireType::FIXED64 => $this->readBytes(8),
                WireType::LEN => $this->readLengthDelimited(),
                WireType::FIXED32 => $this->readBytes(4),
                default => throw new \RuntimeException(
                    sprintf('Unknown wire type %d for field %d', $wireType, $fieldNum),
                ),
            };

            $fields[] = ['field' => $fieldNum, 'wire' => $wireType, 'value' => $value];
        }

        return $fields;
    }
}
