<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

/**
 * Extracts raw text from a CV file (EF-602): PDF via pdfparser, DOCX via PHPWord.
 *
 * Throws UnreadableCvException when the file cannot be parsed at all.
 * Returns empty text when the file parses but contains no usable text — the
 * caller then applies the "less than 200 useful characters" rule (spec §6.2 step 3).
 */
class CvTextExtractor
{
    public function extract(string $absolutePath, string $mime): ExtractedCv
    {
        if (! is_readable($absolutePath)) {
            throw new UnreadableCvException('Fichier CV introuvable ou illisible.');
        }

        try {
            [$text, $pages] = match (true) {
                str_contains($mime, 'pdf') => $this->fromPdf($absolutePath),
                $this->isDocx($mime) => $this->fromDocx($absolutePath),
                default => throw new UnreadableCvException("Format de CV non pris en charge : {$mime}."),
            };
        } catch (UnreadableCvException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UnreadableCvException('Extraction du texte impossible : '.$e->getMessage(), previous: $e);
        }

        return new ExtractedCv(text: trim($text), mime: $mime, pages: $pages);
    }

    private function isDocx(string $mime): bool
    {
        return str_contains($mime, 'docx')
            || str_contains($mime, 'wordprocessingml.document')
            || $mime === 'application/octet-stream';
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function fromPdf(string $path): array
    {
        $document = (new Parser)->parseFile($path);

        return [$document->getText(), count($document->getPages())];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function fromDocx(string $path): array
    {
        $document = IOFactory::load($path);

        $lines = [];
        $pageBreaks = 0;
        foreach ($document->getSections() as $section) {
            $this->walk($section, $lines, $pageBreaks);
        }

        return [implode("\n", $lines), $pageBreaks + 1];
    }

    /**
     * @param  list<string>  $lines
     */
    private function walk(object $element, array &$lines, int &$pageBreaks): void
    {
        if ($element instanceof Text) {
            $lines[] = (string) $element->getText();

            return;
        }

        if ($element instanceof TextRun) {
            $lines[] = $element->getText();

            return;
        }

        if ($element instanceof PageBreak) {
            $pageBreaks++;

            return;
        }

        if ($element instanceof Table) {
            foreach ($element->getRows() as $row) {
                foreach ($row->getCells() as $cell) {
                    $this->walk($cell, $lines, $pageBreaks);
                }
            }

            return;
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $this->walk($child, $lines, $pageBreaks);
            }
        }
    }
}
