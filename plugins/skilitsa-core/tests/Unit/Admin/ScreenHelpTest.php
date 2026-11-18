<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Admin\AdminMenu;
use Skilitsa\Core\Admin\View\ScreenHelp;
use Skilitsa\Core\Admin\View\SettingsViewModel;

/**
 * Help is a shipping requirement, not a nicety.
 *
 * These tests are the reason a new screen or a new setting cannot arrive
 * undocumented: adding one without its sentence turns the suite red.
 */
final class ScreenHelpTest extends TestCase
{
    public function test_every_registered_screen_has_a_title_and_an_intro(): void
    {
        foreach (AdminMenu::SCREENS as $slug) {
            self::assertNotSame('', trim(ScreenHelp::title($slug)), $slug);
            self::assertNotSame('', trim(ScreenHelp::intro($slug)), $slug);
        }
    }

    public function test_help_carries_no_orphan_screens(): void
    {
        // Help for a screen that no longer exists is help nobody will ever
        // read, and a menu entry someone forgot to delete.
        self::assertSame(AdminMenu::SCREENS, array_keys(ScreenHelp::SCREENS));
    }

    public function test_intros_are_english(): void
    {
        foreach (AdminMenu::SCREENS as $slug) {
            self::assertDoesNotMatchRegularExpression('/\p{Greek}/u', ScreenHelp::intro($slug), $slug);
        }
    }

    public function test_an_intro_actually_explains_rather_than_labels(): void
    {
        // A three-word "intro" is a heading wearing a hat.
        foreach (AdminMenu::SCREENS as $slug) {
            self::assertGreaterThan(10, str_word_count(ScreenHelp::intro($slug)), $slug);
        }
    }

    public function test_every_setting_still_carries_its_own_help(): void
    {
        foreach (SettingsViewModel::build([])->fields as $field) {
            self::assertNotSame('', trim($field->help), $field->key);
        }
    }

    public function test_asking_for_an_unknown_screen_fails_loudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ScreenHelp::intro('skilitsa-not-a-screen');
    }
}
