<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data\Wpdb;

use Skilitsa\Core\Data\Mapper\TraitMapper;
use Skilitsa\Core\Data\Schema;
use Skilitsa\Core\Data\TraitSource;
use Skilitsa\Core\Engine\Value\TraitDefinition;

final class WpdbTraitSource implements TraitSource
{
    /** @var array<string,TraitDefinition>|null */
    private ?array $cache = null;

    public function __construct(private readonly TraitMapper $mapper = new TraitMapper())
    {
    }

    public function traits(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        global $wpdb;
        $table = Schema::tableNames($wpdb->prefix)['trait_definitions'];

        // The table name comes from our own constant, never from user input.
        $rows = $wpdb->get_results(
            "SELECT trait_key, penalty_over, penalty_under, global_weight
             FROM {$table}
             ORDER BY sort_order, trait_key",
            ARRAY_A
        );

        return $this->cache = $this->mapper->fromRows(is_array($rows) ? $rows : []);
    }

    /** @return list<string> Data-quality problems found while mapping. */
    public function skipped(): array
    {
        return $this->mapper->skipped();
    }
}
