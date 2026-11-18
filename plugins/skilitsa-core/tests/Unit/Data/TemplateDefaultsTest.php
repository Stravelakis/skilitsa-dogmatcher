<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Data\TemplateDefaults;

final class TemplateDefaultsTest extends TestCase
{
    public function test_size_is_the_one_trait_the_sheet_never_covered(): void
    {
        $filled = TemplateDefaults::fill([]);

        foreach (['pos_size_1', 'pos_size_2', 'neg_size_1', 'neg_size_2'] as $key) {
            self::assertArrayHasKey($key, $filled);
            self::assertNotSame('', trim($filled[$key]));
        }
    }

    public function test_the_sheet_always_wins(): void
    {
        $filled = TemplateDefaults::fill(['pos_size_1' => 'Ο ιδιοκτήτης το έγραψε αλλιώς.']);

        self::assertSame('Ο ιδιοκτήτης το έγραψε αλλιώς.', $filled['pos_size_1']);
        self::assertNotSame('', trim($filled['pos_size_2']));
    }

    public function test_a_blank_sheet_cell_is_not_an_override(): void
    {
        // An empty RESULT_TEMPLATES cell renders as a missing explanation, not
        // as a deliberate silence.
        $filled = TemplateDefaults::fill(['neg_size_1' => '   ']);

        self::assertSame(TemplateDefaults::TEMPLATES['neg_size_1'], $filled['neg_size_1']);
    }

    public function test_other_keys_pass_through_untouched(): void
    {
        $filled = TemplateDefaults::fill(['pos_shedding_1' => 'x']);

        self::assertSame('x', $filled['pos_shedding_1']);
    }
}
