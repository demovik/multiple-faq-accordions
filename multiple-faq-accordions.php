<?php
/*
Plugin Name: Multiple FAQ Accordions
Plugin URI: https://github.com/demovik
Description: Adds an FAQ section with jQuery UI accordion, supports native options, categories, duplication, and reordering.
Version: 1.0.0
Author: Viktor Demchuk
Author URI: https://www.linkedin.com/in/demovik
*/

// Register Custom Post Type and Taxonomy
function mfaq_register_faq_post_type() {
    // Register FAQ Post Type
    $labels = array(
        'name' => 'FAQs',
        'singular_name' => 'FAQ',
        'add_new' => 'Add New FAQ',
        'add_new_item' => 'Add New FAQ',
        'edit_item' => 'Edit FAQ',
        'new_item' => 'New FAQ',
        'view_item' => 'View FAQ',
        'search_items' => 'Search FAQs',
        'not_found' => 'No FAQs found',
        'not_found_in_trash' => 'No FAQs found in Trash',
        'all_items' => 'All FAQs',
        'menu_name' => 'FAQs',
    );

    $args = array(
        'public' => true,
        'label' => 'FAQs',
        'labels' => $labels,
        'supports' => array('title', 'editor', 'page-attributes'),
        'menu_icon' => 'dashicons-editor-help',
        'show_in_rest' => true,
        'hierarchical' => false,
        'taxonomies' => array('faq_category'), // Add taxonomy support
    );
    register_post_type('faq', $args);

    // Register FAQ Category Taxonomy
    $taxonomy_labels = array(
        'name' => 'FAQ Categories',
        'singular_name' => 'FAQ Category',
        'search_items' => 'Search FAQ Categories',
        'all_items' => 'All FAQ Categories',
        'parent_item' => 'Parent FAQ Category',
        'parent_item_colon' => 'Parent FAQ Category:',
        'edit_item' => 'Edit FAQ Category',
        'update_item' => 'Update FAQ Category',
        'add_new_item' => 'Add New FAQ Category',
        'new_item_name' => 'New FAQ Category Name',
        'menu_name' => 'Categories',
    );

    $taxonomy_args = array(
        'hierarchical' => true, // Like categories, not tags
        'labels' => $taxonomy_labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'faq-category'),
        'show_in_rest' => true, // For Gutenberg compatibility
    );
    register_taxonomy('faq_category', 'faq', $taxonomy_args);
}
add_action('init', 'mfaq_register_faq_post_type');

// Shortcode Function with Category Filter
function mfaq_faq_shortcode($atts) {
    $atts = shortcode_atts(array(
        'collapsible' => 'true',
        'heightStyle' => 'content',
        'active' => 'false',
        'animate' => '400',
        'category' => '', // New parameter for filtering by category
    ), $atts);

    $query_args = array(
        'post_type' => 'faq',
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    );

    // Add taxonomy query if category is specified
    if (!empty($atts['category'])) {
        $query_args['tax_query'] = array(
            array(
                'taxonomy' => 'faq_category',
                'field' => 'slug',
                'terms' => sanitize_text_field($atts['category']),
            ),
        );
    }

    $faqs = new WP_Query($query_args);

    if (!$faqs->have_posts()) {
        return '<p>No FAQs found' . (!empty($atts['category']) ? ' in category "' . esc_html($atts['category']) . '"' : '') . '.</p>';
    }

    // Validate and adjust active attribute
    if ($atts['active'] === 'false' || $atts['active'] === false) {
        $atts['active'] = 'false';
    } else {
        $active_int = intval($atts['active']);
        if ($active_int < 0 || $active_int >= $faqs->post_count) {
            $atts['active'] = 'false';
        } else {
            $atts['active'] = strval($active_int);
        }
    }

    $output = '<div class="mfaq-accordion" data-options="' . esc_attr(json_encode($atts)) . '">';
    while ($faqs->have_posts()) {
        $faqs->the_post();
        $output .= '<h3 class="mfaq-question">' . get_the_title() . '<span class="mfaq-indicator"></h3>';
        $output .= '<div class="mfaq-answer">' . apply_filters('the_content', get_the_content()) . '</div>';
    }
    $output .= '</div>';

    wp_reset_postdata();
    return $output;
}
add_shortcode('faq_accordion', 'mfaq_faq_shortcode');

// Enqueue Assets
function mfaq_enqueue_assets() {
    wp_enqueue_script('jquery-ui-accordion');
    wp_enqueue_style('mfaq-style', plugins_url('/assets/faq.css', __FILE__), array(), '1.9');
    wp_enqueue_script('mfaq-script', plugins_url('/assets/faq.js', __FILE__), array('jquery', 'jquery-ui-accordion'), '1.9', true);
}
add_action('wp_enqueue_scripts', 'mfaq_enqueue_assets');

// Add Duplicate Link to Admin List
function mfaq_add_duplicate_link($actions, $post) {
    if ($post->post_type === 'faq' && current_user_can('edit_posts')) {
        $nonce = wp_create_nonce('mfaq_duplicate_nonce');
        $actions['duplicate'] = '<a href="' . admin_url('admin.php?action=mfaq_duplicate_faq&post=' . $post->ID . '&nonce=' . $nonce) . '">Duplicate</a>';
    }
    return $actions;
}
add_filter('post_row_actions', 'mfaq_add_duplicate_link', 10, 2);

// Handle Duplication
function mfaq_duplicate_faq() {
    if (!isset($_GET['post']) || !isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'mfaq_duplicate_nonce')) {
        wp_die('Security check failed.');
    }

    $post_id = intval($_GET['post']);
    $post = get_post($post_id);

    if ($post && $post->post_type === 'faq') {
        $new_post = array(
            'post_title' => $post->post_title . ' (Copy)',
            'post_content' => $post->post_content,
            'post_type' => 'faq',
            'post_status' => 'draft',
            'menu_order' => $post->menu_order,
        );
        $new_post_id = wp_insert_post($new_post);

        // Copy taxonomy terms (categories)
        if ($new_post_id) {
            $terms = wp_get_post_terms($post_id, 'faq_category', array('fields' => 'ids'));
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_post_terms($new_post_id, $terms, 'faq_category');
            }
            wp_redirect(admin_url('edit.php?post_type=faq'));
            exit;
        }
    }
    wp_die('Duplication failed.');
}
add_action('admin_action_mfaq_duplicate_faq', 'mfaq_duplicate_faq');

// Enable Sorting in Admin
function mfaq_admin_columns($columns) {
    $columns['menu_order'] = 'Order';
    return $columns;
}
add_filter('manage_faq_posts_columns', 'mfaq_admin_columns');

function mfaq_admin_column_content($column, $post_id) {
    if ($column === 'menu_order') {
        echo get_post($post_id)->menu_order;
    }
}
add_action('manage_faq_posts_custom_column', 'mfaq_admin_column_content', 10, 2);

function mfaq_sortable_columns($columns) {
    $columns['menu_order'] = 'menu_order';
    return $columns;
}
add_filter('manage_edit-faq_sortable_columns', 'mfaq_sortable_columns');