<?php
/**
 * AI drafts. Echoes only.
 *
 * @var array<string,array{description: string, model: string, is_batch: bool, is_available: bool}> $tasks
 * @var array<string,int>                        $counts
 * @var list<\Skilitsa\Core\Ai\Draft\Draft>      $drafts
 * @var list<array{0: string, 1: string}>        $notices
 * @var string                                   $nonceField
 */
declare(strict_types=1);

use Skilitsa\Core\Admin\View\ScreenHelp;

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap skilitsa-admin">
    <h1><?php echo esc_html(ScreenHelp::title('skilitsa-ai')); ?></h1>

    <p class="skilitsa-intro"><?php echo esc_html(ScreenHelp::intro('skilitsa-ai')); ?></p>

    <?php foreach ($notices as [$type, $message]) : ?>
        <div class="notice notice-<?php echo esc_attr($type); ?>">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endforeach; ?>

    <h2>Tasks</h2>

    <table class="widefat skilitsa-tasks">
        <thead>
        <tr>
            <th scope="col">Task</th>
            <th scope="col">Model</th>
            <th scope="col">Status</th>
            <th scope="col"></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tasks as $key => $task) : ?>
            <tr>
                <th scope="row">
                    <code><?php echo esc_html($key); ?></code>
                    <span class="skilitsa-help"><?php echo esc_html($task['description']); ?></span>
                </th>
                <td><?php echo esc_html($task['model']); ?></td>
                <td>
                    <?php echo $task['is_available'] ? 'ready' : 'no key configured'; ?>
                </td>
                <td>
                    <?php if ($task['is_available']) : ?>
                        <form method="post">
                            <?php wp_nonce_field($nonceField); ?>
                            <input
                                type="hidden"
                                name="skilitsa_action"
                                value="run_<?php echo esc_attr($key); ?>"
                            >
                            <?php submit_button('Run one batch', 'secondary', 'submit', false); ?>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Drafts</h2>

    <p class="skilitsa-summary">
        <?php echo (int) ($counts['pending'] ?? 0); ?> waiting ·
        <?php echo (int) ($counts['approved'] ?? 0); ?> approved ·
        <?php echo (int) ($counts['rejected'] ?? 0); ?> rejected
    </p>

    <?php if ($drafts === []) : ?>
        <p>Nothing to review.</p>
    <?php else : ?>
        <table class="widefat skilitsa-drafts">
            <thead>
            <tr>
                <th scope="col">What</th>
                <th scope="col">Now</th>
                <th scope="col">Suggested</th>
                <th scope="col">Why</th>
                <th scope="col"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($drafts as $draft) : ?>
                <tr class="<?php echo esc_attr($draft->isStale ? 'is-stale' : ''); ?>">
                    <th scope="row">
                        <code><?php echo esc_html($draft->targetKey); ?></code>
                        <span class="skilitsa-help"><?php echo esc_html($draft->field); ?></span>
                    </th>
                    <td><?php echo esc_html($draft->currentValue === '' ? '—' : $draft->currentValue); ?></td>
                    <td><strong><?php echo esc_html($draft->suggestedValue); ?></strong></td>
                    <td class="skilitsa-why">
                        <?php echo esc_html($draft->note); ?>
                        <span class="skilitsa-help">
                            <?php echo esc_html($draft->model); ?> ·
                            <?php echo esc_html($draft->createdAt); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($draft->isStale) : ?>
                            <p class="skilitsa-error">
                                The value changed after this was written. Reject it and run the task again.
                            </p>
                        <?php else : ?>
                            <form method="post" class="skilitsa-inline">
                                <?php wp_nonce_field($nonceField); ?>
                                <input type="hidden" name="draft" value="<?php echo (int) $draft->id; ?>">
                                <button type="submit" name="skilitsa_action" value="approve" class="button button-primary">
                                    Approve
                                </button>
                            </form>
                        <?php endif; ?>

                        <form method="post" class="skilitsa-inline">
                            <?php wp_nonce_field($nonceField); ?>
                            <input type="hidden" name="draft" value="<?php echo (int) $draft->id; ?>">
                            <button type="submit" name="skilitsa_action" value="reject" class="button">
                                Reject
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
