<?php

declare(strict_types=1);

use App\Services\Analysis\CvTextExtractor;
use App\Services\Analysis\UnreadableCvException;

function extractorTestPdf(string $text): string
{
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $stream = "BT /F1 12 Tf 72 720 Td ({$escaped}) Tj ET";
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        4 => '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf('%010d 00000 n '."\n", $offset);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

    $path = tempnam(sys_get_temp_dir(), 'cvt').'.pdf';
    file_put_contents($path, $pdf);

    return $path;
}

function extractorTestDocx(string $text): string
{
    $path = tempnam(sys_get_temp_dir(), 'cvt').'.docx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
          <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
          <Default Extension="xml" ContentType="application/xml"/>
          <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
        </Types>
        XML);
    $zip->addFromString('_rels/.rels', <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
          <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
        </Relationships>
        XML);
    $zip->addFromString('word/document.xml', <<<XML
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
          <w:body><w:p><w:r><w:t>{$text}</w:t></w:r></w:p></w:body>
        </w:document>
        XML);
    $zip->close();

    return $path;
}

it('extracts text from a PDF', function (): void {
    $path = extractorTestPdf('Jane Doe - Developpeuse PHP Laravel');

    $text = (new CvTextExtractor)->extract($path, 'application/pdf');

    expect($text)->toContain('Jane Doe')->toContain('Laravel');

    @unlink($path);
});

it('extracts text from a DOCX', function (): void {
    $path = extractorTestDocx('Jean Dupont - Ingenieur backend');

    $text = (new CvTextExtractor)->extract($path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    expect($text)->toContain('Jean Dupont')->toContain('backend');

    @unlink($path);
});

it('throws UnreadableCvException on a corrupted PDF', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'cvt').'.pdf';
    file_put_contents($path, 'this is not a pdf at all');

    expect(fn (): string => (new CvTextExtractor)->extract($path, 'application/pdf'))
        ->toThrow(UnreadableCvException::class);

    @unlink($path);
});

it('throws UnreadableCvException when the file is missing', function (): void {
    expect(fn (): string => (new CvTextExtractor)->extract('/nonexistent/cv.pdf', 'application/pdf'))
        ->toThrow(UnreadableCvException::class);
});

it('throws UnreadableCvException on unsupported mime types', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'cvt').'.png';
    file_put_contents($path, 'binary');

    expect(fn (): string => (new CvTextExtractor)->extract($path, 'image/png'))
        ->toThrow(UnreadableCvException::class);

    @unlink($path);
});

it('returns trimmed text', function (): void {
    $path = extractorTestPdf('   Margot Leclerc   ');

    expect((new CvTextExtractor)->extract($path, 'application/pdf'))->toBe('Margot Leclerc');

    @unlink($path);
});
