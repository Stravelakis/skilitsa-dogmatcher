<?php
declare(strict_types=1);

namespace Skilitsa\Core\WordPress;

/**
 * The breed custom post type.
 *
 * Lives at /breeds/<slug>/. The 258 existing breed articles migrate into this
 * type in Plan 03, with a 301 from every old URL.
 */
final class BreedPostType
{
    public const POST_TYPE = 'skilitsa_breed';
    public const SLUG      = 'breeds';

    public function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'               => __('Breeds', 'skilitsa-core'),
                'singular_name'      => __('Breed', 'skilitsa-core'),
                'menu_name'          => __('Breed posts', 'skilitsa-core'),
                'add_new_item'       => __('Add breed', 'skilitsa-core'),
                'edit_item'          => __('Edit breed', 'skilitsa-core'),
                'search_items'       => __('Search breeds', 'skilitsa-core'),
                'not_found'          => __('No breeds found', 'skilitsa-core'),
                'not_found_in_trash' => __('No breeds in the bin', 'skilitsa-core'),
            ],
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => ['slug' => self::SLUG, 'with_front' => false],
            // Lives under the Skilitsa menu rather than claiming a second
            // top-level item. One plugin, one entry in the sidebar.
            'show_in_menu' => 'skilitsa',
            'menu_icon'    => 'dashicons-pets',
            'show_in_rest' => true,
            'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
            'taxonomies'   => ['category'],
        ]);
    }
}
