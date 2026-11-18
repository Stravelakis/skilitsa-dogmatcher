<?php
/**
 * Result page for /match/<token>.
 *
 * Deliberately thin: it mounts the same client the quiz uses, which fetches the
 * stored result by token. Rendering twice - once here in PHP, once in JS -
 * would be two chances to disagree about what the user matched with.
 *
 * @var string $token
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$token = get_query_var(\Skilitsa\Matcher\Frontend\ResultsRoute::QUERY_VAR);

get_header();
?>

<main id="skilitsa-match-result" class="skilitsa-matcher"
      data-skilitsa-matcher-result="<?php echo esc_attr((string) $token); ?>">
    <noscript>
        <p><?php esc_html_e(
            'Τα αποτελέσματα χρειάζονται JavaScript. Ενεργοποίησέ την και ανανέωσε τη σελίδα.',
            'skilitsa-matcher'
        ); ?></p>
    </noscript>
</main>

<?php
get_footer();
