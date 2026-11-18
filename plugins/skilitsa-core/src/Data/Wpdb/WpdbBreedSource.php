<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data\Wpdb;

use Skilitsa\Core\Data\BreedSource;
use Skilitsa\Core\Data\Mapper\BreedMapper;
use Skilitsa\Core\Data\Schema;
use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\WordPress\BreedPostType;

final class WpdbBreedSource implements BreedSource
{
    /** @var list<Breed>|null */
    private ?array $cache = null;

    public function __construct(private readonly BreedMapper $mapper = new BreedMapper())
    {
    }

    public function breeds(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        global $wpdb;
        $traits = Schema::tableNames($wpdb->prefix)['breed_traits'];
        $posts  = $wpdb->posts;

        // One query for the whole matrix: roughly 6,200 rows for 257 breeds.
        // Fetching per breed would be 257 round trips per page load.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.post_name AS breed_key, t.trait_key, t.value
                 FROM {$traits} t
                 INNER JOIN {$posts} p ON p.ID = t.breed_id
                 WHERE p.post_type = %s AND p.post_status = 'publish'
                 ORDER BY p.post_name, t.trait_key",
                BreedPostType::POST_TYPE
            ),
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
