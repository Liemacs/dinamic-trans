<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use RuntimeException;
use ZipArchive;

/**
 * A spreadsheet, written by hand.
 *
 * An .xlsx is a zip of a few XML files, and the report needs one sheet of it:
 * some text, some numbers, a bold row at the top and one at the bottom. That is
 * a couple of hundred lines here against a spreadsheet library and everything it
 * drags in — for a project whose only other dependencies are the framework
 * itself, the trade is worth making.
 *
 * Why not a CSV, which would be shorter still: a CSV has no types. Sums come out
 * of this app with a comma for the decimal mark, and an Excel that is not set to
 * Romanian reads "17.413,50" as text and refuses to add it up. Here the numbers
 * are stored as numbers and only *displayed* in the Romanian way, by the
 * spreadsheet, wherever it is opened.
 *
 * What it does not do: formulas, multiple sheets, merged cells, colours. None of
 * them are needed to hand someone the darea de seamă.
 */
final class XlsxWriter
{
    /** Cell formats, in the order their style ids are declared below. */
    public const TEXT = 0;

    public const NUMBER = 1;      // 17.413

    public const DECIMAL = 2;     // 25,0

    public const PERCENT = 3;     // 27%

    public const DATE = 4;        // 23.08.2026

    /** The bold variant of a format sits at its id plus this. */
    private const BOLD = 5;

    /** @var list<string> one <row> of XML per line */
    private array $rows = [];

    private int $rowNumber = 0;

    /**
     * @param  string  $sheet  the tab's name
     * @param  list<float>  $widths  column widths, in characters
     */
    public function __construct(
        private string $sheet,
        private array $widths = [],
    ) {}

    /**
     * One cell. `$format` is one of the constants above; `$bold` picks its bold
     * twin.
     *
     * @return array{value: mixed, format: int, bold: bool}
     */
    public static function cell(mixed $value, int $format = self::TEXT, bool $bold = false): array
    {
        return ['value' => $value, 'format' => $format, 'bold' => $bold];
    }

    /**
     * @param  list<array{value: mixed, format: int, bold: bool}|string|null>  $cells
     *                                                                                 a bare string is a plain text cell
     */
    public function row(array $cells): self
    {
        $this->rowNumber++;
        $xml = '';

        foreach (array_values($cells) as $index => $cell) {
            if (! is_array($cell)) {
                $cell = self::cell($cell);
            }

            $xml .= $this->cellXml($this->columnName($index).$this->rowNumber, $cell);
        }

        $this->rows[] = '<row r="'.$this->rowNumber.'">'.$xml.'</row>';

        return $this;
    }

    /** A blank line, to give the eye somewhere to rest. */
    public function blank(): self
    {
        return $this->row([]);
    }

    /**
     * The file itself.
     *
     * ZipArchive only writes to a path, so the workbook is built in the system's
     * temporary directory and read back — it is a few kilobytes, and it is gone
     * before this method returns.
     */
    public function contents(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($path === false) {
            throw new RuntimeException('Nu s-a putut crea fișierul temporar pentru Excel.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Nu s-a putut scrie fișierul Excel.');
        }

        foreach ($this->parts() as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    /**
     * @return array<string, string>
     */
    private function parts(): array
    {
        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',

            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',

            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="'.$this->escape($this->sheetName()).'" sheetId="1" r:id="rId1"/></sheets>'
                .'</workbook>',

            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',

            'xl/styles.xml' => $this->styles(),

            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .$this->columnsXml()
                .'<sheetData>'.implode('', $this->rows).'</sheetData>'
                .'</worksheet>',
        ];
    }

    /**
     * The ten styles the report uses: five formats, each plain and bold.
     *
     * The number formats are the Romanian ones — dot for thousands, comma for
     * decimals, dd.mm.yyyy for dates — written out rather than left to the
     * spreadsheet's locale, so the file reads the same on any machine.
     */
    private function styles(): string
    {
        $formats = [
            '#,##0',
            '#,##0.0',
            '0%',
            'dd\\.mm\\.yyyy',
        ];

        $numFmts = '';

        foreach ($formats as $index => $code) {
            $numFmts .= '<numFmt numFmtId="'.(164 + $index).'" formatCode="'.$this->escape($code).'"/>';
        }

        // Cell formats, in the order the constants above name them: text, number,
        // decimal, percent, date — then the same five again, in bold.
        $cellXfs = '';

        foreach ([false, true] as $bold) {
            $font = $bold ? 1 : 0;

            $cellXfs .= '<xf numFmtId="0" fontId="'.$font.'" fillId="0" borderId="0" xfId="0" applyFont="1"/>';

            foreach ($formats as $index => $code) {
                $cellXfs .= '<xf numFmtId="'.(164 + $index).'" fontId="'.$font.'" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="'.count($formats).'">'.$numFmts.'</numFmts>'
            .'<fonts count="2">'
            .'<font><sz val="10"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="10">'.$cellXfs.'</cellXfs>'
            // Without a named default style some readers warn that the workbook
            // has none and substitute their own.
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function columnsXml(): string
    {
        if ($this->widths === []) {
            return '';
        }

        $cols = '';

        foreach (array_values($this->widths) as $index => $width) {
            $cols .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        return '<cols>'.$cols.'</cols>';
    }

    /**
     * @param  array{value: mixed, format: int, bold: bool}  $cell
     */
    private function cellXml(string $reference, array $cell): string
    {
        $style = $cell['format'] + ($cell['bold'] ? self::BOLD : 0);
        $value = $cell['value'];

        if ($value === null || $value === '') {
            return '<c r="'.$reference.'" s="'.$style.'"/>';
        }

        if ($value instanceof CarbonInterface) {
            return '<c r="'.$reference.'" s="'.$style.'"><v>'.$this->serial($value).'</v></c>';
        }

        if (is_int($value) || is_float($value)) {
            // Percentages are stored as the fraction the format expects.
            $number = $cell['format'] === self::PERCENT ? $value / 100 : $value;

            return '<c r="'.$reference.'" s="'.$style.'"><v>'.$this->number((float) $number).'</v></c>';
        }

        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .$this->escape((string) $value)
            .'</t></is></c>';
    }

    /**
     * Excel counts days from 30 December 1899 — one day before its epoch, which
     * is how the format's own 1900 leap-year bug is absorbed.
     */
    private function serial(CarbonInterface $moment): int
    {
        return (int) $moment->copy()->startOfDay()->diffInDays('1899-12-30', absolute: true);
    }

    /** Point for the decimal mark: this is the stored value, not the display. */
    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.') ?: '0';
    }

    private function columnName(int $index): string
    {
        $name = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $name = chr(65 + $i % 26).$name;
        }

        return $name;
    }

    /** Sheet names cannot carry : \ / ? * [ ] and stop at 31 characters. */
    private function sheetName(): string
    {
        return mb_substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $this->sheet), 0, 31);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
