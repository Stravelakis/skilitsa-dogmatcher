<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Admin\Form\SettingsForm;
use Skilitsa\Core\Admin\View\SettingsViewModel;
use Skilitsa\Core\Data\Settings;

/**
 * Pointing the plugin at a different sheet.
 *
 * This is what makes another language possible without a developer: copy the
 * sheet, translate the text columns, paste the new address here. Everything
 * else about the install stays the same.
 */
final class SheetSettingTest extends TestCase
{
    public function test_a_pasted_sheet_address_becomes_an_id(): void
    {
        // Nobody knows what a sheet ID is. Everybody can copy the address bar.
        $url = 'https://docs.google.com/spreadsheets/d/1AbC_deFGhijkLMnop-QRstuv12345/edit?gid=0#gid=0';

        self::assertSame('1AbC_deFGhijkLMnop-QRstuv12345', Settings::sheetIdFrom($url));
    }

    public function test_a_bare_id_is_accepted_as_it_is(): void
    {
        self::assertSame(
            Settings::DEFAULT_SHEET_ID,
            Settings::sheetIdFrom(Settings::DEFAULT_SHEET_ID)
        );
    }

    public function test_anything_that_is_not_a_sheet_is_refused(): void
    {
        foreach (['', '   ', 'my sheet', 'https://example.com/', 'short'] as $bad) {
            self::assertSame('', Settings::sheetIdFrom($bad), $bad);
        }
    }

    public function test_the_form_reports_a_bad_address_instead_of_clearing_it(): void
    {
        // Silently keeping the old sheet while showing a new value would be the
        // worst outcome: an import that looks configured and is not.
        $submission = SettingsForm::parse(['sheet_id' => 'https://example.com/not-a-sheet']);

        self::assertFalse($submission->isValid());
        self::assertNotNull($submission->errorFor('sheet_id'));
        self::assertSame(Settings::DEFAULT_SHEET_ID, $submission->values['sheet_id']);
    }

    public function test_the_form_keeps_the_id_out_of_a_full_url(): void
    {
        $submission = SettingsForm::parse([
            'sheet_id' => 'https://docs.google.com/spreadsheets/d/1AbC_deFGhijkLMnop-QRstuv12345/edit',
        ]);

        self::assertTrue($submission->isValid());
        self::assertSame('1AbC_deFGhijkLMnop-QRstuv12345', $submission->values['sheet_id']);
    }

    public function test_a_blank_field_keeps_the_sheet_it_had(): void
    {
        $submission = SettingsForm::parse(['sheet_id' => '']);

        self::assertTrue($submission->isValid());
        self::assertSame(Settings::DEFAULT_SHEET_ID, $submission->values['sheet_id']);
    }

    public function test_sanitize_refuses_rubbish_from_the_database(): void
    {
        $clean = Settings::sanitize(['sheet_id' => '../../etc/passwd']);

        self::assertSame(Settings::DEFAULT_SHEET_ID, $clean['sheet_id']);
    }

    public function test_it_renders_as_a_text_field_with_help_about_copying(): void
    {
        $field = SettingsViewModel::build([])->field('sheet_id');

        self::assertSame('text', $field->type);
        self::assertStringContainsString('copy the sheet', strtolower($field->help));
        self::assertStringContainsString('tab and column name', strtolower($field->help));
    }
}
