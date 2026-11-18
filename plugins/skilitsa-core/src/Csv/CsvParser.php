<?php
declare(strict_types=1);

namespace Skilitsa\Core\Csv;

/**
 * A CSV parser that survives what Google Sheets actually exports.
 *
 * v1 split the body on newlines before calling str_getcsv per line, so any
 * cell containing a line break - which is most of the Greek prose in the
 * TRAITS warnings and RESULT_TEMPLATES bodies - corrupted its row and every
 * column after it. This streams the whole body through fgetcsv instead, which
 * understands quoted newlines.
 */
final class CsvParser
{
    /** @return array{headers: list<string>, rows: list<array<string,string>>} */
    public function parse(string $csv): array
    {
        $csv = $this->stripBom($csv);

        if (trim($csv) === '') {
            return ['headers' => [], 'rows' => []];
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return ['headers' => [], 'rows' => []];
        }

        fwrite($handle, $csv);
        rewind($handle);

        $headerRow = fgetcsv($handle, 0, ',', '"', '');
        if (!is_array($headerRow)) {
            fclose($handle);

            return ['headers' => [], 'rows' => []];
        }

        $headers = $this->normaliseHeaders($headerRow);

        $rows = [];
        while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (!is_array($line) || $this->isEmptyRow($line)) {
                continue;
            }
            $rows[] = $this->combine($headers, $line);
        }

        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function stripBom(string $csv): string
    {
        return str_starts_with($csv, "\xEF\xBB\xBF") ? substr($csv, 3) : $csv;
    }

    /**
     * @param list<string|null> $raw
     * @return list<string>
     */
    private function normaliseHeaders(array $raw): array
    {
        $headers = [];
        $seen    = [];

        foreach ($raw as $index => $header) {
            // A header cell is a name, not prose. The real SETTINGS tab has
            // explanatory text wrapped onto further lines inside the header
            // cell itself, so take the first line and discard the rest -
            // otherwise the column ends up named after a paragraph.
            $firstLine = preg_split('/\r\n|\n|\r/', (string) $header)[0] ?? '';

            $clean = strtolower(trim($firstLine));
            $clean = preg_replace('/\s+/u', '_', $clean) ?? $clean;
            $clean = str_replace(['-', '.'], '_', $clean);
            $clean = preg_replace('/_+/', '_', $clean) ?? $clean;
            $clean = trim($clean, '_');

            // A blank column gets a positional name rather than truncating the
            // schema, which is what v1 did.
            $name = $clean === '' ? 'column_' . ($index + 1) : $clean;

            // Two columns normalising to the same name would silently overwrite
            // each other in combine(), losing a whole column. Suffix instead, so
            // the data survives and the duplicate is visible in the header list.
            if (isset($seen[$name])) {
                $seen[$name]++;
                $name .= '_' . $seen[$name];
            } else {
                $seen[$name] = 1;
            }

            $headers[] = $name;
        }

        return $headers;
    }

    /** @param list<string|null> $row */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string>      $headers
     * @param list<string|null> $row
     * @return array<string,string>
     */
    private function combine(array $headers, array $row): array
    {
        $combined = [];

        foreach ($headers as $index => $header) {
            $combined[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $combined;
    }
}
