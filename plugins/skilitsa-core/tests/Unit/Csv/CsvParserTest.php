<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Csv;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Csv\CsvParser;

final class CsvParserTest extends TestCase
{
    private CsvParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CsvParser();
    }

    public function test_parses_a_simple_table(): void
    {
        $result = $this->parser->parse("a,b\n1,2\n3,4");

        self::assertSame(['a', 'b'], $result['headers']);
        self::assertSame([['a' => '1', 'b' => '2'], ['a' => '3', 'b' => '4']], $result['rows']);
    }

    public function test_handles_a_newline_inside_a_quoted_field(): void
    {
        // The v1 killer. A multi-line cell must stay one field.
        $csv = "key,body\nintro,\"line one\nline two\"\nnext,plain";

        $result = $this->parser->parse($csv);

        self::assertCount(2, $result['rows']);
        self::assertSame("line one\nline two", $result['rows'][0]['body']);
        self::assertSame('plain', $result['rows'][1]['body']);
    }

    public function test_handles_escaped_quotes(): void
    {
        $result = $this->parser->parse("a\n\"he said \"\"hi\"\"\"");

        self::assertSame('he said "hi"', $result['rows'][0]['a']);
    }

    public function test_handles_commas_inside_quotes(): void
    {
        $result = $this->parser->parse("a,b\n\"one, two\",three");

        self::assertSame('one, two', $result['rows'][0]['a']);
        self::assertSame('three', $result['rows'][0]['b']);
    }

    public function test_strips_a_utf8_bom_from_the_first_header(): void
    {
        $result = $this->parser->parse("\xEF\xBB\xBFkey,value\na,b");

        self::assertSame(['key', 'value'], $result['headers']);
    }

    public function test_handles_crlf_line_endings(): void
    {
        $result = $this->parser->parse("a,b\r\n1,2\r\n");

        self::assertSame([['a' => '1', 'b' => '2']], $result['rows']);
    }

    public function test_preserves_greek_text(): void
    {
        $csv = "key,value\ngreeting,\"Καλημέρα, φίλε\"";

        self::assertSame('Καλημέρα, φίλε', $this->parser->parse($csv)['rows'][0]['value']);
    }

    public function test_does_not_truncate_at_a_blank_header(): void
    {
        // v1 broke out of header parsing on the first empty column, silently
        // dropping every column to its right. A spacer column must not do that.
        $result = $this->parser->parse("a,,c\n1,2,3");

        self::assertCount(3, $result['headers']);
        self::assertSame('3', $result['rows'][0]['c']);
    }

    public function test_names_unnamed_columns_positionally(): void
    {
        $result = $this->parser->parse("a,,c\n1,2,3");

        self::assertSame(['a', 'column_2', 'c'], $result['headers']);
    }

    public function test_skips_entirely_empty_rows(): void
    {
        $result = $this->parser->parse("a,b\n1,2\n,\n3,4");

        self::assertCount(2, $result['rows']);
    }

    public function test_pads_short_rows_rather_than_shifting_columns(): void
    {
        $result = $this->parser->parse("a,b,c\n1,2");

        self::assertSame(['a' => '1', 'b' => '2', 'c' => ''], $result['rows'][0]);
    }

    public function test_ignores_extra_columns_beyond_the_header(): void
    {
        $result = $this->parser->parse("a,b\n1,2,3");

        self::assertSame(['a' => '1', 'b' => '2'], $result['rows'][0]);
    }

    public function test_normalises_header_case_and_separators(): void
    {
        $result = $this->parser->parse("Breed Key,External-URL\nx,y");

        self::assertSame(['breed_key', 'external_url'], $result['headers']);
    }

    public function test_empty_input_yields_no_headers_and_no_rows(): void
    {
        $result = $this->parser->parse('');

        self::assertSame([], $result['headers']);
        self::assertSame([], $result['rows']);
    }

    public function test_trims_surrounding_whitespace_from_values(): void
    {
        $result = $this->parser->parse("a,b\n  1  ,  2  ");

        self::assertSame('1', $result['rows'][0]['a']);
    }

    public function test_takes_only_the_first_line_of_a_multi_line_header_cell(): void
    {
        // The real SETTINGS tab has explanatory prose wrapped onto further
        // lines inside the header cell. Without this rule the column ends up
        // named after a whole paragraph.
        $csv = "\"key\nplugin_name\nplugin_slug\",\"value\nSkilitsa\",notes\ngamma,1.6,x";

        $result = $this->parser->parse($csv);

        self::assertSame(['key', 'value', 'notes'], $result['headers']);
        self::assertSame('1.6', $result['rows'][0]['value']);
    }

    public function test_collapses_internal_whitespace_in_headers(): void
    {
        $result = $this->parser->parse("Breed   Key,Trait\tValue\nx,y");

        self::assertSame(['breed_key', 'trait_value'], $result['headers']);
    }

    public function test_duplicate_normalised_headers_are_suffixed_not_overwritten(): void
    {
        // Two columns normalising to one name previously overwrote each other
        // in combine(), losing a whole column of hand-edited sheet data.
        $result = $this->parser->parse("Scale Low,scale_low
1,2");

        self::assertSame(['scale_low', 'scale_low_2'], $result['headers']);
        self::assertSame('1', $result['rows'][0]['scale_low']);
        self::assertSame('2', $result['rows'][0]['scale_low_2']);
    }
}
