<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data;

/**
 * Table names and CREATE TABLE statements.
 *
 * Pure by design: it takes the prefix and charset as arguments rather than
 * reaching for $wpdb, so the SQL can be asserted in a unit test. dbDelta is
 * unforgiving about formatting - uppercase CREATE TABLE, two spaces before
 * PRIMARY KEY, KEY not INDEX, one field per line - and those details are
 * exactly the kind that break silently in production.
 */
final class Schema
{
    public const VERSION = '1.3.0';

    private const PREFIX = 'skilitsa_';

    /** @return array<string,string> logical name => real table name */
    public static function tableNames(string $prefix): array
    {
        $logical = [
            'breed_traits',
            'trait_definitions',
            'questions',
            'question_trait_map',
            'explanation_templates',
            'match_sessions',
            'match_answers',
            'match_results',
            'match_events',
            'ai_drafts',
        ];

        $names = [];
        foreach ($logical as $name) {
            $names[$name] = $prefix . self::PREFIX . $name;
        }

        return $names;
    }

    /** @return list<string> */
    public static function createStatements(string $prefix, string $charsetCollate): array
    {
        $t = self::tableNames($prefix);

        return [
            "CREATE TABLE {$t['breed_traits']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  breed_id bigint(20) unsigned NOT NULL,
  trait_key varchar(64) NOT NULL,
  value decimal(4,2) NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY breed_trait (breed_id,trait_key),
  KEY breed_id (breed_id),
  KEY trait_key (trait_key)
) {$charsetCollate}",

            "CREATE TABLE {$t['trait_definitions']} (
  trait_key varchar(64) NOT NULL,
  label text NOT NULL,
  description text NOT NULL,
  scale_low text NOT NULL,
  scale_high text NOT NULL,
  warning_too_low text NOT NULL,
  warning_too_high text NOT NULL,
  penalty_over decimal(4,2) NOT NULL DEFAULT 1.00,
  penalty_under decimal(4,2) NOT NULL DEFAULT 1.00,
  global_weight decimal(4,2) NOT NULL DEFAULT 1.00,
  is_tuned tinyint(1) NOT NULL DEFAULT 0,
  sort_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (trait_key)
) {$charsetCollate}",

            "CREATE TABLE {$t['questions']} (
  question_key varchar(64) NOT NULL,
  section_key varchar(64) NOT NULL DEFAULT '',
  label text NOT NULL,
  label_low text NOT NULL,
  label_high text NOT NULL,
  helper_text text NOT NULL,
  image_low varchar(255) NOT NULL DEFAULT '',
  image_high varchar(255) NOT NULL DEFAULT '',
  weight decimal(4,2) NOT NULL DEFAULT 1.00,
  show_dealbreaker tinyint(1) NOT NULL DEFAULT 1,
  show_ignore tinyint(1) NOT NULL DEFAULT 1,
  tier varchar(16) NOT NULL DEFAULT 'extended',
  sort_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (question_key),
  KEY section_key (section_key),
  KEY tier (tier)
) {$charsetCollate}",

            "CREATE TABLE {$t['question_trait_map']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  question_key varchar(64) NOT NULL,
  trait_key varchar(64) NOT NULL,
  direction varchar(16) NOT NULL DEFAULT 'same',
  weight decimal(4,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY  (id),
  UNIQUE KEY question_trait (question_key,trait_key),
  KEY question_key (question_key),
  KEY trait_key (trait_key)
) {$charsetCollate}",

            "CREATE TABLE {$t['explanation_templates']} (
  template_key varchar(128) NOT NULL,
  body text NOT NULL,
  PRIMARY KEY  (template_key)
) {$charsetCollate}",

            "CREATE TABLE {$t['ai_drafts']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  task varchar(64) NOT NULL,
  target_type varchar(32) NOT NULL,
  target_key varchar(191) NOT NULL,
  field varchar(64) NOT NULL,
  current_value text NOT NULL,
  suggested_value text NOT NULL,
  model varchar(64) NOT NULL,
  note text NOT NULL,
  fingerprint char(32) NOT NULL,
  status varchar(16) NOT NULL DEFAULT 'pending',
  created_at datetime NOT NULL,
  reviewed_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY fingerprint (fingerprint),
  KEY status (status),
  KEY task (task),
  KEY target (target_type,target_key)
) {$charsetCollate}",

            "CREATE TABLE {$t['match_sessions']} (
  token char(12) NOT NULL,
  user_uuid char(36) DEFAULT NULL,
  flow_mode varchar(16) NOT NULL DEFAULT 'all',
  terms_version varchar(32) NOT NULL DEFAULT '',
  terms_accepted_at datetime DEFAULT NULL,
  started_at datetime NOT NULL,
  completed_at datetime DEFAULT NULL,
  result_snapshot longtext NULL,
  PRIMARY KEY  (token),
  KEY user_uuid (user_uuid),
  KEY started_at (started_at)
) {$charsetCollate}",

            "CREATE TABLE {$t['match_answers']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token char(12) NOT NULL,
  question_key varchar(64) NOT NULL,
  answer tinyint(4) NOT NULL,
  is_dealbreaker tinyint(1) NOT NULL DEFAULT 0,
  is_ignored tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY token_question (token,question_key),
  KEY token (token)
) {$charsetCollate}",

            "CREATE TABLE {$t['match_results']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token char(12) NOT NULL,
  breed_id bigint(20) unsigned NOT NULL,
  rank_position smallint(6) NOT NULL,
  score tinyint(4) NOT NULL,
  PRIMARY KEY  (id),
  KEY token (token),
  KEY breed_id (breed_id)
) {$charsetCollate}",

            "CREATE TABLE {$t['match_events']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token char(12) NOT NULL,
  event_type varchar(32) NOT NULL,
  target varchar(191) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY token (token),
  KEY event_type (event_type)
) {$charsetCollate}",
        ];
    }
}
