<?php
/*
Plugin Name: Post Expiration
Description: Allows you to set an expiration date for posts, automatically unpublishing, archiving, or moving them.
Version: 1.0
Author: Firstname Lastname
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PostExpirationManage {

    /**
     * Constructor for the PostExpirationManage class.
     *
     * Registers necessary hooks to add the expiration meta box,
     * save the expiration date, and expire posts based on their
     * expiration date. Also schedules a recurring event to check
     * for post expiration hourly if not already scheduled.
     */
    public function __construct() {
        // Register hooks
        add_action('add_meta_boxes', [$this, 'add_expiration_meta_box']);
        add_action('save_post', [$this, 'save_expiration_date']);
        add_action('post_expiration_check', [$this, 'expire_posts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        
        // Schedule expiration event
        if (! wp_next_scheduled('post_expiration_check')) {
            wp_schedule_event(time(), 'hourly', 'post_expiration_check');
        }
    }

    /**
     * Enqueue Flatpickr and custom script
     *
     * This function enqueues Flatpickr, a JavaScript date and time picker,
     * and a custom script that configures Flatpickr to work with the
     * expiration date meta box.
     */
    public function enqueue_scripts() {
        /**
         * Enqueue Flatpickr JavaScript library
         *
         * @link https://flatpickr.js.org/ Flatpickr documentation
         */
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
        
        /**
         * Enqueue Flatpickr CSS styles
         *
         * @link https://flatpickr.js.org/ Flatpickr documentation
         */
        wp_enqueue_style('flatpickr-style', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
        
        /**
         * Enqueue custom script that configures Flatpickr for the expiration date meta box
         *
         * The custom script is located in the js directory of the plugin and is enqueued
         * with a handle of "custom-expiration-script". The script is dependent on
         * Flatpickr and is loaded in the footer of the page.
         */
        wp_enqueue_script('custom-expiration-script', plugin_dir_url(__FILE__) . 'js/expiration-datepicker.js', ['flatpickr'], null, true);
    }

    /**
     * Add expiration date meta box to post edit screen
     *
     * This function adds a meta box to the post edit screen where users can
     * set an expiration date for the post. The meta box is displayed on the
     * side of the edit screen with high priority.
     */
    public function add_expiration_meta_box() {
        add_meta_box(
            'post_expiration_date', // Unique ID for the meta box
            'Post Expiration Date',    // Title of the meta box
            [$this, 'render_meta_box'],// Callback function to render the meta box
            'post',                    // Screen on which to show the meta box
            'side',                    // Context (location) of the meta box
            'high'                     // Priority of the meta box
        );
    }

    /**
     * Render the meta box HTML
     *
     * This function renders the HTML for the meta box, which includes a
     * datetime-local input for the expiration date. The expiration date
     * is retrieved from the post meta using the `_expiration_date` key.
     *
     * @param WP_Post $post The post object
     */
    public function render_meta_box($post) {
        $expiration_date = get_post_meta($post->ID, '_expiration_date', true);
        // Verify the nonce before saving the expiration date
        wp_nonce_field('save_expiration_date', 'expiration_date_nonce');
        ?>
        <!-- Set Expiration Date: input field -->
        <label for="expiration_date">Set Expiration Date:</label>
        <input type="datetime-local" name="expiration_date" id="expiration_date" value="<?php echo esc_attr($expiration_date); ?>">
        <?php
    }

    /**
     * Save the expiration date when the post is saved
     *
     * This function verifies the nonce and saves the expiration date
     * if it is present in the POST data.
     *
     * @param int $post_id The ID of the post being saved
     */
    public function save_expiration_date($post_id) {
        // Verify the nonce before saving the expiration date
        if (! isset($_POST['expiration_date_nonce']) || ! wp_verify_nonce($_POST['expiration_date_nonce'], 'save_expiration_date')) {
            return;
        }

        // Save the expiration date if it was submitted with the post
        if (isset($_POST['expiration_date'])) {
            update_post_meta($post_id, '_expiration_date', sanitize_text_field($_POST['expiration_date']));
        }
    }

    /**
     * Expire posts that have reached their expiration date
     *
     * This function will query posts with an expiration date of today or earlier
     * and update their status to draft. You can customize the behavior to
     * unpublish, move to trash, or delete the posts instead.
     */
    public function expire_posts() {
        $args = [
            'post_type'      => 'post',
            'meta_query'     => [
                [
                    'key'     => '_expiration_date',
                    'value'   => current_time('Y-m-d H:i:s'),
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ]
            ],
            'posts_per_page' => -1
        ];

        $expiredPosts = new WP_Query($args);
        while ($expiredPosts->have_posts()) {
            $expiredPosts->the_post();
            $postId = get_the_ID();

            // Unpublish, move to draft, or delete based on your preference
            wp_update_post([
                'ID'          => $postId,
                'post_status' => 'draft'
            ]);
            // Optionally, you could add a post meta flag to avoid reprocessing expired posts
            update_post_meta($postId, '_expired', true);
        }

        wp_reset_postdata();
    }
}

// Initialize the plugin
new PostExpirationManage();
