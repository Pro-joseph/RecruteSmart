<?php

declare(strict_types=1);

use App\Services\Analysis\CvTextExtractor;
use App\Services\Analysis\UnreadableCvException;

it('extracts text from a PDF', function (): void {
    $path = extractorTestPdf('Jane Doe - Developpeuse PHP Laravel');

    $text = (new CvTextExtractor)->extract($path, 'application/pdf')->text;

    expect($text)->toContain('Jane Doe')->toContain('Laravel');

    @unlink($path);
});

it('extracts text from a DOCX', function (): void {
    $path = extractorTestDocx('Jean Dupont - Ingenieur backend');

    $text = (new CvTextExtractor)->extract($path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')->text;

    expect($text)->toContain('Jean Dupont')->toContain('backend');

    @unlink($path);
});

it('throws UnreadableCvException on a corrupted PDF', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'cvt').'.pdf';
    file_put_contents($path, 'this is not a pdf at all');

    expect(fn () => (new CvTextExtractor)->extract($path, 'application/pdf'))
        ->toThrow(UnreadableCvException::class);

    @unlink($path);
});

it('throws UnreadableCvException when the file is missing', function (): void {
    expect(fn () => (new CvTextExtractor)->extract('/nonexistent/cv.pdf', 'application/pdf'))
        ->toThrow(UnreadableCvException::class);
});

it('throws UnreadableCvException on unsupported mime types', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'cvt').'.png';
    file_put_contents($path, 'binary');

    expect(fn () => (new CvTextExtractor)->extract($path, 'image/png'))
        ->toThrow(UnreadableCvException::class);

    @unlink($path);
});

it('returns trimmed text', function (): void {
    $path = extractorTestPdf('   Margot Leclerc   ');

    expect((new CvTextExtractor)->extract($path, 'application/pdf')->text)->toBe('Margot Leclerc');

    @unlink($path);
});
