<?php
declare(strict_types=1);

namespace Skilitsa\Core\Migration;

/**
 * Renders a MigrationPlan for a human to read before anything is applied.
 *
 * Warnings come first, deliberately: someone skimming a 258-line report must
 * see the blockers without scrolling.
 */
final class MigrationReporter
{
    public function render(MigrationPlan $plan): string
    {
        if ($plan->isEmpty() && !$plan->hasWarnings()) {
            return "Migration plan: nothing to do.\n";
        }

        $out = "Migration plan\n==============\n\n";

        if ($plan->hasWarnings()) {
            $out .= sprintf("!! %d WARNING(S) - DO NOT APPLY until resolved:\n", count($plan->warnings));
            foreach ($plan->warnings as $warning) {
                $out .= '   - ' . $warning . "\n";
            }
            $out .= "\n";
        }

        $out .= sprintf(
            "%d post(s) to change: %d type change(s), %d slug change(s), %d redirect(s).\n\n",
            $plan->count(),
            count($plan->typeChanges()),
            count($plan->slugChanges()),
            count($plan->redirects)
        );

        foreach ($plan->changes as $change) {
            $what = [];
            if ($change->changesType()) {
                $what[] = sprintf('%s -> %s', $change->oldType, $change->newType);
            }
            if ($change->changesSlug()) {
                $what[] = sprintf('%s -> %s', $change->oldSlug, $change->newSlug);
            }

            $out .= sprintf("  #%-6d %s\n", $change->postId, implode(' | ', $what));
        }

        if ($plan->hasWarnings()) {
            $out .= "\nRefusing to apply: resolve the warnings above first.\n";
        }

        return $out;
    }
}
