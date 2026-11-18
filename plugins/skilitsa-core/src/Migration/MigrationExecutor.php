<?php
declare(strict_types=1);

namespace Skilitsa\Core\Migration;

use RuntimeException;
use Skilitsa\Core\WordPress\BreedPostType;

/**
 * Applies a MigrationPlan. Computes nothing.
 *
 * The plan touches 258 live URLs, so this refuses to run against a plan with
 * unresolved warnings unless explicitly forced, and records that the force
 * flag was used.
 */
final class MigrationExecutor
{
    /**
     * @return array{applied:int, skipped:int, redirects:list<array{from:string,to:string}>, log:list<string>}
     * @throws RuntimeException when the plan has warnings and $force is false
     */
    public function apply(MigrationPlan $plan, bool $dryRun = true, bool $force = false): array
    {
        if ($plan->hasWarnings() && !$force) {
            throw new RuntimeException(sprintf(
                'Refusing to apply a migration plan with %d unresolved warning(s). Resolve them, or pass force explicitly.',
                count($plan->warnings)
            ));
        }

        $log = [];
        if ($plan->hasWarnings() && $force) {
            $log[] = sprintf('FORCED past %d warning(s).', count($plan->warnings));
        }

        $applied   = 0;
        $skipped   = 0;
        $redirects = [];

        foreach ($plan->changes as $change) {
            $from = sprintf('/%s/', $change->oldSlug);
            $to   = sprintf('/%s/%s/', BreedPostType::SLUG, $change->newSlug);

            if ($dryRun) {
                $applied++;
                $redirects[] = ['from' => $from, 'to' => $to];
                continue;
            }

            $update = ['ID' => $change->postId];
            if ($change->changesType()) {
                $update['post_type'] = $change->newType;
            }
            if ($change->changesSlug()) {
                $update['post_name'] = $change->newSlug;
            }

            $result = wp_update_post($update, true);

            if (is_wp_error($result)) {
                $skipped++;
                $log[] = sprintf('Post %d failed: %s', $change->postId, $result->get_error_message());
                continue;
            }

            // WordPress may adjust a slug it considers taken. Record what it
            // actually did rather than what we asked for, so the redirect
            // points somewhere real.
            $actualSlug = get_post_field('post_name', $change->postId);
            if (is_string($actualSlug) && $actualSlug !== '' && $actualSlug !== $change->newSlug) {
                $log[] = sprintf(
                    'Post %d: WordPress stored slug "%s", not the requested "%s".',
                    $change->postId,
                    $actualSlug,
                    $change->newSlug
                );
                $to = sprintf('/%s/%s/', BreedPostType::SLUG, $actualSlug);
            }

            $applied++;
            $redirects[] = ['from' => $from, 'to' => $to];
        }

        if (!$dryRun && $applied > 0) {
            // Post type and slug changes both alter routing.
            flush_rewrite_rules(false);
        }

        return [
            'applied'   => $applied,
            'skipped'   => $skipped,
            'redirects' => $redirects,
            'log'       => $log,
        ];
    }
}
