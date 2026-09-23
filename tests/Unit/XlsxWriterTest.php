<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\XlsxWriter;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * The hand-written spreadsheet.
 *
 * What matters here is that a number lands in the file as a number: the whole
 * reason for writing an .xlsx rather than a CSV is that a column of lei can be
 * summed in Excel wherever it is opened, whatever the machine's idea of a
 * decimal mark is.
 */
class XlsxWriterTest extends TestCase
{
    private function sheetXml(XlsxWriter $writer): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($path, $writer->contents());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Fișierul generat nu este o arhivă validă.');

        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        return $xml;
    }

    public function test_numbers_are_stored_as_numbers_and_not_as_formatted_text(): void
    {
        $sheet = (new XlsxWriter('Test'))->row([
            XlsxWriter::cell(17412.5, XlsxWriter::NUMBER),
            XlsxWriter::cell(25.0, XlsxWriter::DECIMAL),
        ]);

        $xml = $this->sheetXml($sheet);

        // A point for the decimal mark and no thousands separator: that is the
        // stored value. The comma and the dot are the spreadsheet's business,
        // and they come from the number format on the cell.
        $this->assertStringContainsString('<v>17412.5</v>', $xml);
        $this->assertStringContainsString('<v>25</v>', $xml);
        $this->assertStringNotContainsString('17.412,50', $xml);
        $this->assertStringNotContainsString('t="inlineStr"><is><t xml:space="preserve">17412.5', $xml);
    }

    public function test_a_percentage_is_stored_as_the_fraction_its_format_expects(): void
    {
        $xml = $this->sheetXml((new XlsxWriter('Test'))->row([
            XlsxWriter::cell(27.0, XlsxWriter::PERCENT),
        ]));

        // 27% is 0.27 with a percent format over it — writing 27 would show 2700%.
        $this->assertStringContainsString('<v>0.27</v>', $xml);
    }

    public function test_dates_are_stored_as_the_serial_excel_counts_in(): void
    {
        $xml = $this->sheetXml((new XlsxWriter('Test'))->row([
            XlsxWriter::cell(CarbonImmutable::parse('1900-01-01'), XlsxWriter::DATE),
            XlsxWriter::cell(CarbonImmutable::parse('2026-08-23 17:42:00'), XlsxWriter::DATE),
        ]));

        // Counted from 30 December 1899, and the time of day dropped.
        $this->assertStringContainsString('<v>2</v>', $xml);
        $this->assertStringContainsString('<v>46257</v>', $xml);
    }

    public function test_text_is_escaped_and_diacritics_survive(): void
    {
        $xml = $this->sheetXml((new XlsxWriter('Test'))->row([
            'Hâncești → Brăila & <retur>',
        ]));

        $this->assertStringContainsString('Hâncești → Brăila &amp; &lt;retur&gt;', $xml);
    }

    public function test_an_empty_cell_keeps_its_place_in_the_row(): void
    {
        $xml = $this->sheetXml((new XlsxWriter('Test'))->row(['Total', null, 5]));

        // The third value must still land in column C, or every figure under it
        // is a column out.
        $this->assertStringContainsString('<c r="B1"', $xml);
        $this->assertStringContainsString('<c r="C1"', $xml);
        $this->assertStringContainsString('r="C1" s="0"><v>5</v>', $xml);
    }

    public function test_columns_carry_on_past_the_alphabet(): void
    {
        $xml = $this->sheetXml((new XlsxWriter('Test'))->row(array_fill(0, 28, 'x')));

        $this->assertStringContainsString('<c r="Z1"', $xml);
        $this->assertStringContainsString('<c r="AA1"', $xml);
        $this->assertStringContainsString('<c r="AB1"', $xml);
    }

    public function test_the_sheet_name_is_trimmed_to_what_a_workbook_accepts(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($path, (new XlsxWriter('Darea de seamă: 2026/2027 [prima parte]'))->row(['x'])->contents());

        $zip = new ZipArchive;
        $zip->open($path);
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $zip->close();
        unlink($path);

        preg_match('/<sheet name="([^"]*)"/', $workbook, $matches);
        $name = $matches[1] ?? '';

        // Cut at 31 characters, with : \ / ? * [ ] replaced by spaces — the
        // limits a workbook puts on a tab's name.
        $this->assertSame(31, mb_strlen($name));
        $this->assertSame('Darea de seamă  2026 2027  prim', $name);
    }
}
