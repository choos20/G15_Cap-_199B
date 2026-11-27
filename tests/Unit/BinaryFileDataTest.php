<?php

namespace Tests\Unit;

use App\Services\DocumentPreviewService;
use PHPUnit\Framework\TestCase;

class BinaryFileDataTest extends TestCase
{
    public function test_postgresql_uses_ascii_hex_and_normalizes_legacy_hex_or_raw_binary(): void
    {
        $bytes = "PK\x03\x04\x14\x00document\x00\xFF\x80";
        $encoded = DocumentPreviewService::databaseValue($bytes, 'pgsql');
        $this->assertSame('\\x'.bin2hex($bytes), $encoded);
        $this->assertStringNotContainsString("\0", $encoded);
        $this->assertSame($bytes, DocumentPreviewService::normalizeBinary($encoded));
        $this->assertSame($bytes, DocumentPreviewService::normalizeBinary($bytes));
        $this->assertSame($bytes, DocumentPreviewService::databaseValue($bytes, 'sqlite'));
        $this->assertSame($bytes, DocumentPreviewService::databaseValue($bytes, 'mysql'));
        $this->assertNull(DocumentPreviewService::normalizeBinary(null));
    }

    public function test_postgresql_stream_can_be_read_repeatedly_without_losing_bytes_or_its_cursor(): void
    {
        $stream = fopen('php://memory', 'r+');
        $bytes = "PK\x03\x04\x00\xFFpreview";
        fwrite($stream, $bytes);
        fseek($stream, 5);
        try {
            $this->assertSame($bytes, DocumentPreviewService::normalizeBinary($stream));
            $this->assertSame(5, ftell($stream));
            $this->assertSame($bytes, DocumentPreviewService::normalizeBinary($stream));
            $this->assertSame(5, ftell($stream));
        } finally {
            fclose($stream);
        }
    }
}
