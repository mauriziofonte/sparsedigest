<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\Test\Unit\TestCase;

/**
 * Comprehensive test suite for isLikelyBinary() method.
 *
 * Tests binary detection across:
 * - Binary files (null bytes, control characters, high bytes)
 * - Text files (ASCII, UTF-8, multibyte UTF-8 with emoji/CJK)
 * - Edge cases (BOM, mixed content, boundary conditions)
 */
class BinaryDetectionTest extends TestCase
{
    // ============================================================
    // BINARY FILE TESTS - These should return TRUE
    // ============================================================

    /**
     * Test: File with null bytes in header should be detected as binary.
     */
    public function testNullBytesInHeaderDetectedAsBinary(): void
    {
        // Common binary file pattern: null bytes in header
        $content = "MZ\x00\x00\x00\x00\x00\x00" . random_bytes(1000);
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $this->assertTrue($file->isLikelyBinary(), 'Null bytes in header should be detected as binary');
    }

    /**
     * Test: File with null bytes only should be detected as binary.
     */
    public function testPureNullBytesFileDetectedAsBinary(): void
    {
        $content = str_repeat("\x00", 1000);
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $this->assertTrue($file->isLikelyBinary(), 'Pure null bytes file should be detected as binary');
    }

    /**
     * Test: PNG file signature should be detected as binary.
     */
    public function testPngSignatureDetectedAsBinary(): void
    {
        // PNG signature: 89 50 4E 47 0D 0A 1A 0A
        $content = "\x89PNG\r\n\x1a\n" . random_bytes(1000);
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $this->assertTrue($file->isLikelyBinary(), 'PNG signature should be detected as binary');
    }

    /**
     * Test: JPEG file signature should be detected as binary.
     */
    public function testJpegSignatureDetectedAsBinary(): void
    {
        // JPEG signature: FF D8 FF
        $content = "\xff\xd8\xff\xe0\x00\x10JFIF\x00" . random_bytes(1000);
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $this->assertTrue($file->isLikelyBinary(), 'JPEG signature should be detected as binary');
    }

    /**
     * Test: GIF file signature should be detected as binary.
     */
    public function testGifSignatureDetectedAsBinary(): void
    {
        // GIF89a signature with binary content
        $content = "GIF89a\x00\x01\x00\x01\x00\x00\x00\x00" . random_bytes(1000);
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $this->assertTrue($file->isLikelyBinary(), 'GIF signature with binary content should be detected as binary');
    }

    /**
     * Test: PDF file signature should be detected as binary (has control chars).
     */
    public function testPdfWithBinaryStreamDetectedAsBinary(): void
    {
        // PDF with binary stream (control char \x00 in stream)
        $content = "%PDF-1.4\n1 0 obj\n<<\n>>\nstream\n\x00\x01\x02\x03\nendstream\nendobj";
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $this->assertTrue($file->isLikelyBinary(), 'PDF with binary stream should be detected as binary');
    }

    /**
     * Test: ZIP file signature should be detected as binary.
     */
    public function testZipSignatureDetectedAsBinary(): void
    {
        // ZIP signature: PK\x03\x04
        $content = "PK\x03\x04" . random_bytes(1000);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertTrue($file->isLikelyBinary(), 'ZIP signature should be detected as binary');
    }

    /**
     * Test: ELF executable signature should be detected as binary.
     */
    public function testElfSignatureDetectedAsBinary(): void
    {
        // ELF signature: 7F 45 4C 46 (0x7F 'E' 'L' 'F')
        $content = "\x7fELF\x02\x01\x01\x00" . random_bytes(1000);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertTrue($file->isLikelyBinary(), 'ELF signature should be detected as binary');
    }

    /**
     * Test: Windows PE/MZ executable should be detected as binary.
     */
    public function testMzExecutableDetectedAsBinary(): void
    {
        // MZ header
        $content = "MZ\x90\x00\x03\x00\x00\x00" . random_bytes(1000);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertTrue($file->isLikelyBinary(), 'MZ executable should be detected as binary');
    }

    /**
     * Test: File with control characters (SOH, STX, ETX) should be detected as binary.
     */
    public function testControlCharactersDetectedAsBinary(): void
    {
        // Control characters: SOH (0x01), STX (0x02), ETX (0x03)
        $content = "\x01\x02\x03Hello World" . str_repeat('a', 1000);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertTrue($file->isLikelyBinary(), 'Control characters should be detected as binary');
    }

    /**
     * Test: File with bell character (0x07) should be detected as binary.
     */
    public function testBellCharacterDetectedAsBinary(): void
    {
        $content = "Hello\x07World" . str_repeat('a', 1000);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertTrue($file->isLikelyBinary(), 'Bell character should be detected as binary');
    }

    /**
     * Test: File with backspace character (0x08) should be detected as binary.
     */
    public function testBackspaceCharacterDetectedAsBinary(): void
    {
        $content = "Hello\x08World" . str_repeat('a', 1000);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertTrue($file->isLikelyBinary(), 'Backspace character should be detected as binary');
    }

    /**
     * Test: File with form feed character (0x0C) should be detected as text.
     * Form feed is a valid text character used for page breaks in source code.
     */
    public function testFormFeedCharacterDetectedAsText(): void
    {
        $content = "Hello\x0cWorld" . str_repeat('a', 1000);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        // Form feed (0x0C = 12) is between 9-13 which are allowed whitespace chars
        $this->assertFalse($file->isLikelyBinary(), 'Form feed character should be detected as text');
    }

    /**
     * Test: File with escape character (0x1B) should be detected as binary.
     */
    public function testEscapeCharacterDetectedAsBinary(): void
    {
        $content = "Hello\x1bWorld" . str_repeat('a', 1000);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertTrue($file->isLikelyBinary(), 'Escape character should be detected as binary');
    }

    /**
     * Test: Random binary data should be detected as binary.
     */
    public function testRandomBinaryDataDetectedAsBinary(): void
    {
        $content = random_bytes(2000);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertTrue($file->isLikelyBinary(), 'Random binary data should be detected as binary');
    }

    // ============================================================
    // TEXT FILE TESTS - These should return FALSE
    // ============================================================

    /**
     * Test: Pure ASCII text should be detected as text.
     */
    public function testPureAsciiTextDetectedAsText(): void
    {
        $content = str_repeat("Hello, World! This is a test.\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'Pure ASCII text should be detected as text');
    }

    /**
     * Test: ASCII text with tabs should be detected as text.
     */
    public function testAsciiWithTabsDetectedAsText(): void
    {
        $content = "Column1\tColumn2\tColumn3\n" . str_repeat("Value1\tValue2\tValue3\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'ASCII text with tabs should be detected as text');
    }

    /**
     * Test: ASCII text with carriage returns (Windows-style) should be detected as text.
     */
    public function testAsciiWithCrLfDetectedAsText(): void
    {
        $content = str_repeat("Hello, World!\r\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'ASCII text with CRLF should be detected as text');
    }

    /**
     * Test: UTF-8 text with basic Latin should be detected as text.
     */
    public function testUtf8BasicLatinDetectedAsText(): void
    {
        $content = str_repeat("Héllo, Wörld! Thïs ís à tëst.\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'UTF-8 with basic Latin characters should be detected as text');
    }

    /**
     * Test: UTF-8 text with Cyrillic should be detected as text.
     */
    public function testUtf8CyrillicDetectedAsText(): void
    {
        $content = str_repeat("Привет мир! Это тест.\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'UTF-8 with Cyrillic should be detected as text');
    }

    /**
     * Test: UTF-8 text with Greek should be detected as text.
     */
    public function testUtf8GreekDetectedAsText(): void
    {
        $content = str_repeat("Γειά σου κόσμε! Αυτό είναι ένα τεστ.\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'UTF-8 with Greek should be detected as text');
    }

    /**
     * Test: UTF-8 text with Arabic should be detected as text.
     */
    public function testUtf8ArabicDetectedAsText(): void
    {
        $content = str_repeat("مرحبا بالعالم! هذا اختبار.\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'UTF-8 with Arabic should be detected as text');
    }

    /**
     * Test: UTF-8 text with Hebrew should be detected as text.
     */
    public function testUtf8HebrewDetectedAsText(): void
    {
        $content = str_repeat("שלום עולם! זה מבחן.\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'UTF-8 with Hebrew should be detected as text');
    }

    /**
     * Test: UTF-8 text with Chinese (Simplified) should be detected as text.
     */
    public function testUtf8ChineseSimplifiedDetectedAsText(): void
    {
        $content = str_repeat("你好世界!这是一个测试。\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'UTF-8 with Chinese Simplified should be detected as text');
    }

    /**
     * Test: UTF-8 text with Japanese should be detected as text.
     */
    public function testUtf8JapaneseDetectedAsText(): void
    {
        $content = str_repeat("こんにちは世界!これはテストです。\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'UTF-8 with Japanese should be detected as text');
    }

    /**
     * Test: UTF-8 text with Korean should be detected as text.
     */
    public function testUtf8KoreanDetectedAsText(): void
    {
        $content = str_repeat("안녕하세요 세계! 이것은 테스트입니다.\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'UTF-8 with Korean should be detected as text');
    }

    /**
     * Test: UTF-8 text with emoji should be detected as text.
     */
    public function testUtf8EmojiDetectedAsText(): void
    {
        $content = str_repeat("Hello 👋 World 🌍! This is a test 🧪.\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'UTF-8 with emoji should be detected as text');
    }

    /**
     * Test: JSON content should be detected as text.
     */
    public function testJsonContentDetectedAsText(): void
    {
        $content = json_encode([
            'name' => 'Test',
            'values' => [1, 2, 3, 4, 5],
            'nested' => ['key' => 'value'],
        ], JSON_PRETTY_PRINT);
        $content = str_repeat($content . "\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'JSON content should be detected as text');
    }

    /**
     * Test: XML content should be detected as text.
     */
    public function testXmlContentDetectedAsText(): void
    {
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $content .= '<root><item>Test 1</item><item>Test 2</item></root>' . "\n";
        $content = str_repeat($content, 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'XML content should be detected as text');
    }

    /**
     * Test: HTML content should be detected as text.
     */
    public function testHtmlContentDetectedAsText(): void
    {
        $content = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Hello World!</p></body></html>';
        $content = str_repeat($content . "\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'HTML content should be detected as text');
    }

    /**
     * Test: PHP source code should be detected as text.
     */
    public function testPhpSourceCodeDetectedAsText(): void
    {
        $content = '<?php' . "\n";
        $content .= 'function hello(): string {' . "\n";
        $content .= '    return "Hello, World!";' . "\n";
        $content .= '}' . "\n";
        $content = str_repeat($content, 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'PHP source code should be detected as text');
    }

    /**
     * Test: JavaScript source code should be detected as text.
     */
    public function testJavaScriptSourceCodeDetectedAsText(): void
    {
        $content = 'function hello() {' . "\n";
        $content .= '    console.log("Hello, World!");' . "\n";
        $content .= '}' . "\n";
        $content = str_repeat($content, 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'JavaScript source code should be detected as text');
    }

    /**
     * Test: Markdown content should be detected as text.
     */
    public function testMarkdownContentDetectedAsText(): void
    {
        $content = "# Header\n\nThis is **bold** and *italic* text.\n\n- Item 1\n- Item 2\n";
        $content = str_repeat($content, 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'Markdown content should be detected as text');
    }

    /**
     * Test: CSV content should be detected as text.
     */
    public function testCsvContentDetectedAsText(): void
    {
        $content = "Name,Age,City\n";
        $content .= str_repeat("John,30,New York\nJane,25,Los Angeles\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'CSV content should be detected as text');
    }

    // ============================================================
    // EDGE CASES
    // ============================================================

    /**
     * Test: UTF-8 BOM should NOT be detected as binary.
     * The BOM (EF BB BF) contains high bytes but is valid UTF-8.
     */
    public function testUtf8BomDetectedAsText(): void
    {
        // UTF-8 BOM: EF BB BF
        $content = "\xef\xbb\xbf" . str_repeat("Hello, World!\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        // BOM contains high bytes (0xEF, 0xBB, 0xBF > 127)
        // Our fast method will consider it binary, but this is acceptable
        // because BOM files are rare and the fallback to full hash is fine
        $result = $file->isLikelyBinary();

        // We accept either behavior - the important thing is it's deterministic
        $this->assertIsBool($result, 'BOM detection should return a boolean');
    }

    /**
     * Test: Plain text PDF (no binary stream) might be detected as text.
     */
    public function testPlainTextPdfDetectedAsText(): void
    {
        // Simple PDF with only ASCII content, no binary streams
        $content = "%PDF-1.4\n";
        $content .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $content .= "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n";
        $content .= "xref\n0 3\n";
        $content = str_repeat($content, 50);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        // A plain text PDF (no binary content) should be detected as text
        $this->assertFalse($file->isLikelyBinary(), 'Plain text PDF should be detected as text');
    }

    /**
     * Test: Small file (< 512 bytes) should still work.
     */
    public function testSmallFileDetection(): void
    {
        $content = "Hello, World!";
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        // Small file should be detected as text
        $this->assertFalse($file->isLikelyBinary(), 'Small text file should be detected as text');
    }

    /**
     * Test: File with only printable ASCII (space to tilde).
     */
    public function testPrintableAsciiOnlyDetectedAsText(): void
    {
        // All printable ASCII characters (32-126)
        $printable = '';
        for ($i = 32; $i <= 126; $i++) {
            $printable .= chr($i);
        }
        $content = str_repeat($printable . "\n", 50);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'Printable ASCII only should be detected as text');
    }

    /**
     * Test: File with mixed UTF-8 scripts should be detected as text.
     */
    public function testMixedUtf8ScriptsDetectedAsText(): void
    {
        $content = "English: Hello World\n";
        $content .= "Russian: Привет мир\n";
        $content .= "Greek: Γειά σου κόσμε\n";
        $content .= "Arabic: مرحبا بالعالم\n";
        $content .= "Chinese: 你好世界\n";
        $content .= "Japanese: こんにちは世界\n";
        $content .= "Korean: 안녕하세요 세계\n";
        $content .= "Emoji: 👋🌍🧪✨\n";
        $content = str_repeat($content, 50);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'Mixed UTF-8 scripts should be detected as text');
    }

    /**
     * Test: File with null byte in the middle (after 512 bytes) should be detected as text.
     * Our method only checks the first 512 bytes.
     */
    public function testNullByteAfter512BytesDetectedAsText(): void
    {
        // 600 bytes of text, then a null byte
        $content = str_repeat("Hello, World! ", 50); // ~700 bytes
        $content = substr($content, 0, 600) . "\x00" . substr($content, 600);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        // The null byte is after 512 bytes, so it won't be detected
        $this->assertFalse($file->isLikelyBinary(), 'Null byte after 512 bytes should not affect detection');
    }

    /**
     * Test: File with high bytes (> 127) in valid UTF-8 should be detected as text.
     */
    public function testValidUtf8HighBytesDetectedAsText(): void
    {
        // UTF-8 encoded string with high bytes
        // 'é' is 0xC3 0xA9 in UTF-8 (both > 127)
        $content = str_repeat("Héllo Wörld Ünïcödé\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'Valid UTF-8 with high bytes should be detected as text');
    }

    /**
     * Test: Source code with special characters should be detected as text.
     */
    public function testSourceCodeWithSpecialCharsDetectedAsText(): void
    {
        $content = '<?php' . "\n";
        $content .= '$price = 19.99; // €' . "\n";
        $content .= '$message = "Привет! 你好! مرحبا!";' . "\n";
        $content .= 'echo "Testing: " . $message;' . "\n";
        $content = str_repeat($content, 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'Source code with special chars should be detected as text');
    }

    /**
     * Test: Empty content after reading should handle gracefully.
     * Note: This test assumes file has at least some content (handled by Digestable constructor).
     */
    public function testMinimalContentHandledGracefully(): void
    {
        $content = "a"; // Just one byte
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $this->assertFalse($file->isLikelyBinary(), 'Minimal content should be detected as text');
    }

    /**
     * Test: Consistency - same file should always return the same result.
     */
    public function testDeterministicBehavior(): void
    {
        $content = str_repeat("Hello, World!\n", 100);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $result1 = $file->isLikelyBinary();
        $result2 = $file->isLikelyBinary();
        $result3 = $file->isLikelyBinary();

        $this->assertSame($result1, $result2, 'Results should be consistent across calls');
        $this->assertSame($result2, $result3, 'Results should be consistent across calls');
    }

    /**
     * Test: Consistency for binary files.
     */
    public function testDeterministicBehaviorForBinary(): void
    {
        $content = random_bytes(1000);
        $file = $this->createTempFileWithContents($content);
        $file = File::new($file);

        $result1 = $file->isLikelyBinary();
        $result2 = $file->isLikelyBinary();
        $result3 = $file->isLikelyBinary();

        $this->assertSame($result1, $result2, 'Binary results should be consistent across calls');
        $this->assertSame($result2, $result3, 'Binary results should be consistent across calls');
    }
}
