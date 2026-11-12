<?php

declare(strict_types=1);

namespace VlyDev\Steam\Proto;

/**
 * Pure PHP protobuf binary writer.
 *
 * Writes to an in-memory buffer; call toBytes() to retrieve the result.
 * Fields with default/zero values are omitted (proto3 semantics).
 */
final class Writer
{
    /** @var list<string> */
    private array $buf = [];

    public function toBytes(): string
    {
        return implode('', $this->buf);
    }

    // ------------------------------------------------------------------
    // Low-level primitives
    // ------------------------------------------------------------------

    private function writeVarint(int $value): void
    {
        // Negative ints are uint64 >= 2^63 in two's complement: shift logically,
        // so the sign bit does not propagate and the loop ends after 10 bytes.
        $parts = [];

        do {
            $b = $value & 0x7F;
            $value = ($value >> 7) & (PHP_INT_MAX >> 6);

            if ($value !== 0) {
                $b |= 0x80;
            }

            $parts[] = chr($b);
        } while ($value !== 0);

        $this->buf[] = implode('', $parts);
    }

    private function writeTag(int $fieldNum, int $wireType): void
    {
        $this->writeVarint(($fieldNum << 3) | $wireType);
    }

    // ------------------------------------------------------------------
    // Public field writers
    // ------------------------------------------------------------------

    public function writeUint32(int $fieldNum, int $value): void
    {
        if ($value === 0) {
            return;
        }

        $this->writeTag($fieldNum, WireType::VARINT);
        $this->writeVarint($value);
    }

    public function writeUint64(int $fieldNum, int $value): void
    {
        if ($value === 0) {
            return;
        }

        $this->writeTag($fieldNum, WireType::VARINT);
        $this->writeVarint($value);
    }

    public function writeInt32(int $fieldNum, int $value): void
    {
        if ($value === 0) {
            return;
        }

        $this->writeTag($fieldNum, WireType::VARINT);
        $this->writeVarint($value);
    }

    public function writeString(int $fieldNum, string $value): void
    {
        if ($value === '') {
            return;
        }

        $this->writeTag($fieldNum, WireType::LEN);
        $this->writeVarint(strlen($value));
        $this->buf[] = $value;
    }

    /**
     * Write a float32 as wire type 5 (fixed 32-bit, little-endian).
     * Used for sticker float fields (wear, scale, rotation, etc.).
     */
    public function writeFloat32Fixed(int $fieldNum, float $value): void
    {
        $this->writeTag($fieldNum, WireType::FIXED32);
        $this->buf[] = pack('f', $value);
    }

    /**
     * Write raw bytes as a length-delimited field (wire type 2).
     */
    public function writeRawBytes(int $fieldNum, string $data): void
    {
        if ($data === '') {
            return;
        }

        $this->writeTag($fieldNum, WireType::LEN);
        $this->writeVarint(strlen($data));
        $this->buf[] = $data;
    }

    /**
     * Write a nested message (another Writer's output) as a length-delimited field.
     */
    public function writeEmbedded(int $fieldNum, Writer $nested): void
    {
        $this->writeRawBytes($fieldNum, $nested->toBytes());
    }
}
