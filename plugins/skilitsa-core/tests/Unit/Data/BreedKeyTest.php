<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Data\BreedKey;

final class BreedKeyTest extends TestCase
{
    public function test_sheet_key_and_post_slug_resolve_to_the_same_breed(): void
    {
        // The source sheet keys breeds with underscores; WordPress slugs use
        // hyphens. Comparing them raw silently drops the breed.
        self::assertTrue(BreedKey::matches('border_collie', 'border-collie'));
        self::assertSame('border-collie', BreedKey::canonical('border_collie'));
        self::assertSame('border-collie', BreedKey::canonical('border-collie'));
    }

    public function test_case_and_surrounding_whitespace_are_ignored(): void
    {
        self::assertTrue(BreedKey::matches('  Border_Collie ', 'border-collie'));
    }

    public function test_repeated_separators_collapse(): void
    {
        self::assertSame('a-b', BreedKey::canonical('a__b'));
        self::assertSame('a-b', BreedKey::canonical('a-_-b'));
        self::assertSame('collie', BreedKey::canonical('_collie_'));
    }

    public function test_different_breeds_do_not_match(): void
    {
        self::assertFalse(BreedKey::matches('border_collie', 'rough_collie'));
    }

    public function test_percent_encoded_slugs_are_left_alone(): void
    {
        $greek = '%ce%bc%ce%b1%ce%be%af%ce%bd';

        self::assertSame($greek, BreedKey::canonical($greek));
    }

    public function test_an_ascii_head_before_encoded_greek_is_the_key(): void
    {
        // Posts 5943 and 6363 on the live site: WordPress appended the Greek
        // title to the English base. The head is the breed key the sheet uses.
        self::assertSame(
            'italian-greyhound',
            BreedKey::canonical('italian-greyhound%ce%b9%cf%84%ce%b1%ce%bb%ce%b9%ce%ba%cf%8c')
        );
        self::assertSame(
            'saint-bernard',
            BreedKey::canonical('saint-bernard-%cf%83%ce%b5%ce%bd')
        );
    }

    public function test_reindex_rekeys_a_map_canonically(): void
    {
        $out = BreedKey::reindex(['border_collie' => 1, 'greyhound' => 2]);

        self::assertSame(['border-collie', 'greyhound'], array_keys($out));
        self::assertSame(1, $out['border-collie']);
    }
}
