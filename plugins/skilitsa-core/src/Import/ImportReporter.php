<?php
declare(strict_types=1);

namespace Skilitsa\Core\Import;

/** Renders an ImportPlan for review. Errors first, then warnings, then counts. */
final class ImportReporter
{
    private const MAX_LISTED = 25;

    public function render(ImportPlan $plan): string
    {
        $out = "Import plan\n===========\n\n";

        if ($plan->errors !== []) {
            $out .= sprintf("!! %d ERROR(S) - NOTHING WILL BE WRITTEN:\n", count($plan->errors));
            foreach ($plan->errors as $error) {
                $out .= '   - ' . $error . "\n";
            }
            $out .= "\n";
        }

        if ($plan->warnings !== []) {
            $out .= sprintf("%d warning(s):\n", count($plan->warnings));
            foreach (array_slice($plan->warnings, 0, self::MAX_LISTED) as $warning) {
                $out .= '   - ' . $warning . "\n";
            }
            if (count($plan->warnings) > self::MAX_LISTED) {
                $out .= sprintf("   ... and %d more\n", count($plan->warnings) - self::MAX_LISTED);
            }
            $out .= "\n";
        }

        $out .= "Would write:\n";
        foreach ($plan->counts() as $label => $count) {
            $out .= sprintf("  %-14s %d\n", $label, $count);
        }

        $out .= "\n" . ($plan->isSafeToApply()
            ? "Safe to apply.\n"
            : "NOT safe to apply. Resolve the errors above.\n");

        return $out;
    }
}
