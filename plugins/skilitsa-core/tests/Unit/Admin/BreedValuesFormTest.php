<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Admin\Form\BreedValuesForm;

final class BreedValuesFormTest extends TestCase
{
    /** @var list<string> */
    private const TRAITS = ['size', 'barking'];

    public function test_valid_values_parse(): void
    {
        $submission = BreedValuesForm::parse(['traits' => ['size' => '4', 'barking' => '2.5']], self::TRAITS);

        self::assertTrue($submission->isValid());
        self::assertSame(['size' => 4.0, 'barking' => 2.5], $submission->values);
    }

    public function test_a_value_off_the_scale_is_rejected_not_clamped(): void
    {
        $submission = BreedValuesForm::parse(['traits' => ['size' => '9']], self::TRAITS);

        self::assertFalse($submission->isValid());
        self::assertNotNull($submission->errorFor('size'));
        self::assertArrayNotHasKey('size', $submission->values);
    }

    public function test_zero_is_off_the_scale(): void
    {
        // The quiz scale starts at 1. A stored 0 reads as "the least possible"
        // and would quietly make the breed a bad match for everyone.
        self::assertFalse(BreedValuesForm::parse(['traits' => ['size' => '0']], self::TRAITS)->isValid());
    }

    public function test_a_blank_box_leaves_the_trait_alone(): void
    {
        $submission = BreedValuesForm::parse(['traits' => ['size' => '', 'barking' => '3']], self::TRAITS);

        self::assertTrue($submission->isValid());
        self::assertArrayNotHasKey('size', $submission->values);
        self::assertSame(3.0, $submission->values['barking']);
    }

    public function test_a_trait_that_is_not_ours_is_ignored(): void
    {
        $submission = BreedValuesForm::parse(['traits' => ['not_a_trait' => '3']], self::TRAITS);

        self::assertTrue($submission->isValid());
        self::assertSame([], $submission->values);
    }

    public function test_a_rejected_value_is_echoed_back(): void
    {
        $submission = BreedValuesForm::parse(['traits' => ['size' => 'big']], self::TRAITS);

        self::assertSame('big', $submission->raw['size']);
    }
}
