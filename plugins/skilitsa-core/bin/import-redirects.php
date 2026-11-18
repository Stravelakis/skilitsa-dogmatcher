<?php

/**
 * Loads the migration's redirect map into Rank Math. DRY RUN BY DEFAULT.
 *
 * The migration moves 257 breed URLs. Without these redirects every existing
 * link, every bookmark and every indexed breed page lands on a 404 - or worse,
 * on a soft redirect to the homepage, which tells a search engine the page is
 * gone rather than moved.
 *
 * Rank Math keeps each redirect as a serialised source array; writing the rows
 * directly is the only way to do 257 of them without 257 form submissions.
 *
 *   php bin/run.php ../bin/import-redirects.php          # dry run
 *   php bin/run.php ../bin/import-redirects.php --apply
 *
 * Existing redirects for the same source are left alone: someone may have made
 * one by hand, and a script that overwrites hand-made routing is a script that
 * loses work quietly.
 *
 * No `declare(strict_types=1)`: this may be eval-file'd, where the declaration
 * cannot be the first statement.
 */

global $wpdb;

$args  = $args ?? array_slice($_SERVER['argv'] ?? [], 1);
$apply = in_array('--apply', $args, true);

$table = $wpdb->prefix . 'rank_math_redirections';

if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
    echo "Rank Math redirections table not found. Is the plugin active?\n";

    return;
}

$csv = WP_CONTENT_DIR . '/skilitsa-redirects.csv';

if (!is_readable($csv)) {
    echo "No redirect file at {$csv}. Run the migration first.\n";

    return;
}

$handle = fopen($csv, 'r');
if ($handle === false) {
    echo "Could not open {$csv}.\n";

    return;
}

$home = untrailingslashit(home_url());

$planned = [];
$skipped = [];
$header  = true;

while (($row = fgetcsv($handle)) !== false) {
    if ($header) {
        $header = false;
        continue;
    }

    if (count($row) < 2) {
        continue;
    }

    // Rank Math matches the request URI with BOTH slashes stripped. Storing
    // "border_collie/" looks right in its admin table and never matches: the
    // request falls through to the fallback, which on this site is a redirect
    // to the homepage - a soft 404 that tells search engines the page is gone
    // rather than moved. Verified against nelly, not assumed.
    $from = trim(trim((string) $row[0]), '/');
    $to   = trim((string) $row[1]);

    if ($from === '' || $to === '') {
        continue;
    }

    $exists = $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM {$table} WHERE sources LIKE %s", '%"' . $wpdb->esc_like($from) . '"%')
    );

    if ($exists !== null) {
        $skipped[] = $from;
        continue;
    }

    $planned[] = ['from' => $from, 'to' => $home . '/' . ltrim($to, '/')];
}

fclose($handle);

printf("Redirects in file : %d\n", count($planned) + count($skipped));
printf("Already present   : %d\n", count($skipped));
printf("To create         : %d\n\n", count($planned));

foreach (array_slice($planned, 0, 5) as $item) {
    printf("  /%s  ->  %s\n", $item['from'], $item['to']);
}

if (count($planned) > 5) {
    printf("  ... and %d more\n", count($planned) - 5);
}

if (!$apply) {
    echo "\nDRY RUN. Nothing was written. Re-run with --apply.\n";

    return;
}

$now     = current_time('mysql');
$written = 0;

foreach ($planned as $item) {
    // Both forms, on the same redirect.
    //
    // Rank Math matches the request URI exactly as written, and this site's
    // permalinks end in a slash - so every real inbound link and every indexed
    // URL carries one, while a hand-typed or stripped link may not. Storing one
    // form sends the other to the fallback, which here is the homepage: a soft
    // 404 telling search engines the page is gone rather than moved. Verified
    // on nelly by watching /border_collie succeed and /border_collie/ fail.
    $sources = [
        [
            'ignore'     => '',
            'pattern'    => $item['from'],
            'comparison' => 'exact',
        ],
        [
            'ignore'     => '',
            'pattern'    => $item['from'] . '/',
            'comparison' => 'exact',
        ],
    ];

    $result = $wpdb->insert($table, [
        'sources'       => serialize($sources),
        'url_to'        => $item['to'],
        'header_code'   => 301,
        'hits'          => 0,
        'status'        => 'active',
        'created'       => $now,
        'updated'       => $now,
        'last_accessed' => '0000-00-00 00:00:00',
    ]);

    if ($result) {
        $written++;
    }
}

// Rank Math caches resolved redirects; a stale cache serves the old answer.
$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}rank_math_redirections_cache");

printf("\nCreated %d redirect(s). Redirect cache cleared.\n", $written);
