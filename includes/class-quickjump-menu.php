<?php
/**
 * QuickJump Admin Menu
 *
 * Handles the admin bar menu and dropdown display.
 *
 * @package QuickJump_Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class QuickJump_Menu
 *
 * Creates and manages the admin bar dropdown menu.
 */
class QuickJump_Menu
{

    /**
     * Database handler.
     *
     * @var QuickJump_Database
     */
    private QuickJump_Database $database;

    /**
     * Whether to open links in new window.
     *
     * @var bool
     */
    private bool $open_new_window = false;

    /**
     * Constructor.
     *
     * @param QuickJump_Database $database Database handler.
     */
    public function __construct(QuickJump_Database $database)
    {
        $this->database = $database;
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks.
     *
     * @return void
     */
    private function init_hooks(): void
    {
        // Add admin bar menu
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 999);

        // Add dropdown HTML to footer
        add_action('admin_footer', array($this, 'render_dropdown'));
        add_action('wp_footer', array($this, 'render_dropdown'));

        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX handlers
        add_action('wp_ajax_quickjump_get_links', array($this, 'ajax_get_links'));
        add_action('wp_ajax_quickjump_toggle_pin', array($this, 'ajax_toggle_pin'));
        add_action('wp_ajax_quickjump_rename_link', array($this, 'ajax_rename_link'));
        add_action('wp_ajax_quickjump_hide_link', array($this, 'ajax_hide_link'));
        add_action('wp_ajax_quickjump_search', array($this, 'ajax_search'));
        add_action('wp_ajax_quickjump_clear_history', array($this, 'ajax_clear_history'));
    }

    /**
     * Add menu item to admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
     * @return void
     */
    public function add_admin_bar_menu(WP_Admin_Bar $wp_admin_bar): void
    {
        // Check minimum capability
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            return;
        }

        // Get custom button label
        $button_label = get_option('quickjump_admin_button_label', __('Shortcuts', 'quickjump-admin'));

        // Add main menu node (just the button, no submenu)
        $wp_admin_bar->add_node(array(
            'id' => 'quickjump-admin',
            'title' => '<span class="ab-icon dashicons dashicons-admin-links"></span><span class="ab-label">' . esc_html($button_label) . '</span>',
            'href' => '#',
            'meta' => array(
                'class' => 'quickjump-admin-trigger',
                'title' => esc_attr__('Quick navigation shortcuts', 'quickjump-admin'),
            ),
        ));
    }

    /**
     * Render dropdown HTML in footer.
     *
     * @return void
     */
    public function render_dropdown(): void
    {
        // Only for users who can see the menu
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            return;
        }

        // Only if admin bar is showing
        if (!is_admin_bar_showing()) {
            return;
        }

        $user_id = get_current_user_id();

        // Get settings
        $recent_count = (int) get_option('quickjump_admin_recent_links_count', 10);
        $recent_hours = (int) get_option('quickjump_admin_recent_links_hours', 24);
        $mostused_count = (int) get_option('quickjump_admin_mostused_links_count', 10);
        $mostused_days = (int) get_option('quickjump_admin_mostused_links_days', 30);
        $show_timestamps = (bool) get_option('quickjump_admin_show_timestamps', true);
        $show_counts = (bool) get_option('quickjump_admin_show_access_count', true);
        $show_search = (bool) get_option('quickjump_admin_show_search', true);
        $side_by_side = (bool) get_option('quickjump_admin_side_by_side', false);
        $this->open_new_window = (bool) get_option('quickjump_admin_open_new_window', false);

        // Build dropdown classes
        $dropdown_classes = 'quickjump-dropdown';
        if ($side_by_side) {
            $dropdown_classes .= ' side-by-side';
        }

        // Get links
        $pinned_links = $this->database->get_pinned_links($user_id);
        $recent_links = $this->database->get_recent_links($user_id, $recent_count, $recent_hours);
        $mostused_links = $this->database->get_most_used_links($user_id, $mostused_count, $mostused_days);

        // Filter out pinned items from recent and most used lists
        $pinned_ids = array_map(function ($link) {
            return $link->id;
        }, $pinned_links);

        $recent_links = array_filter($recent_links, function ($link) use ($pinned_ids) {
            return !in_array($link->id, $pinned_ids);
        });

        $mostused_links = array_filter($mostused_links, function ($link) use ($pinned_ids) {
            return !in_array($link->id, $pinned_ids);
        });

        // Filter out excluded URLs from all lists
        $excluded_patterns = $this->get_excluded_patterns();
        $pinned_links = array_filter($pinned_links, function ($link) use ($excluded_patterns) {
            return !$this->is_url_excluded($link->url, $excluded_patterns);
        });
        $recent_links = array_filter($recent_links, function ($link) use ($excluded_patterns) {
            return !$this->is_url_excluded($link->url, $excluded_patterns);
        });
        $mostused_links = array_filter($mostused_links, function ($link) use ($excluded_patterns) {
            return !$this->is_url_excluded($link->url, $excluded_patterns);
        });
        ?>
        <div class="<?php echo esc_attr($dropdown_classes); ?>" id="quickjump-dropdown" style="display: none;">
            <?php if ($show_search): ?>
                <!-- Search -->
                <div class="quickjump-search-container">
                    <input type="text" id="quickjump-search" class="quickjump-search"
                        placeholder="<?php esc_attr_e('Search...', 'quickjump-admin'); ?>" autocomplete="off">
                    <span class="quickjump-search-icon dashicons dashicons-search"></span>
                </div>

                <!-- Search Results (hidden by default) -->
                <div class="quickjump-section quickjump-search-results" id="quickjump-search-results" style="display: none;">
                    <div class="quickjump-section-header">
                        <?php esc_html_e('Search Results', 'quickjump-admin'); ?>
                    </div>
                    <ul class="quickjump-links" id="quickjump-search-results-list"></ul>
                </div>
            <?php endif; ?>

            <!-- Main Content -->
            <div id="quickjump-main-content">
                <?php if (!empty($pinned_links)): ?>
                    <!-- Pinned Section -->
                    <div class="quickjump-section quickjump-pinned">
                        <div class="quickjump-section-header">
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php esc_html_e('Pinned', 'quickjump-admin'); ?>
                        </div>
                        <ul class="quickjump-links">
                            <?php foreach ($pinned_links as $link): ?>
                                <?php echo $this->render_link_item($link, false, false); ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Recent Section -->
                <div class="quickjump-section quickjump-recent">
                    <div class="quickjump-section-header">
                        <span class="dashicons dashicons-clock"></span>
                        <?php esc_html_e('Recent', 'quickjump-admin'); ?>
                    </div>
                    <?php if (!empty($recent_links)): ?>
                        <ul class="quickjump-links">
                            <?php foreach ($recent_links as $link): ?>
                                <?php echo $this->render_link_item($link, $show_timestamps, false); ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="quickjump-empty"><?php esc_html_e('No recent pages.', 'quickjump-admin'); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Most Used Section -->
                <div class="quickjump-section quickjump-mostused">
                    <div class="quickjump-section-header">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php esc_html_e('Most Used', 'quickjump-admin'); ?>
                    </div>
                    <?php if (!empty($mostused_links)): ?>
                        <ul class="quickjump-links">
                            <?php foreach ($mostused_links as $link): ?>
                                <?php echo $this->render_link_item($link, false, $show_counts); ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="quickjump-empty"><?php esc_html_e('No frequently used pages yet.', 'quickjump-admin'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Footer -->
            <div class="quickjump-footer">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=quickjump-admin')); ?>"
                    class="quickjump-settings-link">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('Settings', 'quickjump-admin'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single link item.
     *
     * @param object $link           Link object.
     * @param bool   $show_timestamp Whether to show timestamp.
     * @param bool   $show_count     Whether to show access count.
     * @return string Link item HTML.
     */
    private function render_link_item(object $link, bool $show_timestamp = false, bool $show_count = false): string
    {
        $icon_class = $this->get_link_icon_class($link->url);

        ob_start();
        ?>
        <li class="quickjump-link-item<?php echo $link->is_pinned ? ' is-pinned' : ''; ?>"
            data-id="<?php echo esc_attr($link->id); ?>">
            <a href="<?php echo esc_url($link->url); ?>" class="quickjump-link" <?php echo $this->open_new_window ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                <span class="quickjump-link-icon dashicons <?php echo esc_attr($icon_class); ?>"></span>
                <span class="quickjump-link-title"><?php echo esc_html($link->page_title); ?></span>
                <?php if ($show_timestamp): ?>
                    <span class="quickjump-link-time" data-time="<?php echo esc_attr($link->last_accessed); ?>">
                        <?php echo esc_html($this->format_relative_time($link->last_accessed)); ?>
                    </span>
                <?php endif; ?>
                <?php if ($show_count): ?>
                    <span class="quickjump-link-count" title="<?php esc_attr_e('Visit count', 'quickjump-admin'); ?>">
                        <?php echo esc_html($link->access_count); ?>
                    </span>
                <?php endif; ?>
            </a>
            <button type="button" class="quickjump-edit-btn" data-id="<?php echo esc_attr($link->id); ?>"
                data-title="<?php echo esc_attr($link->page_title); ?>"
                title="<?php esc_attr_e('Rename', 'quickjump-admin'); ?>">
                <span class="dashicons dashicons-edit"></span>
            </button>
            <button type="button" class="quickjump-hide-btn" data-id="<?php echo esc_attr($link->id); ?>"
                data-url="<?php echo esc_attr($link->url); ?>"
                title="<?php esc_attr_e('Hide from menu', 'quickjump-admin'); ?>">
                <span class="dashicons dashicons-hidden"></span>
            </button>
            <button type="button" class="quickjump-pin-btn" data-id="<?php echo esc_attr($link->id); ?>"
                title="<?php echo $link->is_pinned ? esc_attr__('Unpin', 'quickjump-admin') : esc_attr__('Pin', 'quickjump-admin'); ?>">
                <span
                    class="dashicons <?php echo $link->is_pinned ? 'dashicons-star-filled' : 'dashicons-star-empty'; ?>"></span>
            </button>
        </li>
        <?php
        return ob_get_clean();
    }

    /**
     * Get excluded URL patterns from settings.
     *
     * @return array Array of patterns.
     */
    private function get_excluded_patterns(): array
    {
        $patterns_string = get_option('quickjump_admin_excluded_patterns', '');
        if (empty($patterns_string)) {
            return array();
        }

        // Normalize line endings and split
        $patterns_string = str_replace("\r\n", "\n", $patterns_string);
        $patterns = explode("\n", $patterns_string);
        return array_filter(array_map('trim', $patterns));
    }

    /**
     * Check if URL should be excluded from display.
     *
     * @param string $url URL to check.
     * @param array $patterns Patterns to check against.
     * @return bool True if excluded, false otherwise.
     */
    private function is_url_excluded(string $url, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (empty($pattern)) {
                continue;
            }

            // Try as regex first (suppress errors for invalid regex)
            if (@preg_match($pattern, $url)) {
                return true;
            }

            // Fallback to simple string matching
            if (false !== strpos($url, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get appropriate icon class for a URL.
     *
     * @param string $url Page URL.
     * @return string Dashicon class.
     */
    private function get_link_icon_class(string $url): string
    {
        // Parse URL to get path
        $path = wp_parse_url($url, PHP_URL_PATH);

        if (empty($path)) {
            return 'dashicons-admin-page';
        }

        // Check for common admin patterns
        if (false !== strpos($path, 'edit.php')) {
            // Check for post type
            $query = wp_parse_url($url, PHP_URL_QUERY);
            parse_str($query ?? '', $params);

            $post_type = $params['post_type'] ?? 'post';

            $icons = array(
                'post' => 'dashicons-admin-post',
                'page' => 'dashicons-admin-page',
                'attachment' => 'dashicons-admin-media',
            );

            return $icons[$post_type] ?? 'dashicons-admin-post';
        }

        // Map specific pages to icons
        $page_icons = array(
            'index.php' => 'dashicons-dashboard',
            'upload.php' => 'dashicons-admin-media',
            'edit-comments.php' => 'dashicons-admin-comments',
            'themes.php' => 'dashicons-admin-appearance',
            'widgets.php' => 'dashicons-admin-appearance',
            'nav-menus.php' => 'dashicons-menu',
            'plugins.php' => 'dashicons-admin-plugins',
            'users.php' => 'dashicons-admin-users',
            'tools.php' => 'dashicons-admin-tools',
            'options-general.php' => 'dashicons-admin-settings',
            'options-writing.php' => 'dashicons-admin-settings',
            'options-reading.php' => 'dashicons-admin-settings',
            'options-media.php' => 'dashicons-admin-settings',
            'options-permalink.php' => 'dashicons-admin-settings',
            'profile.php' => 'dashicons-admin-users',
            'update-core.php' => 'dashicons-update',
        );

        foreach ($page_icons as $page => $icon) {
            if (false !== strpos($path, $page)) {
                return $icon;
            }
        }

        // Default icon
        return 'dashicons-admin-page';
    }

    /**
     * Format timestamp as relative time.
     *
     * @param string $timestamp MySQL timestamp.
     * @return string Relative time string.
     */
    private function format_relative_time(string $timestamp): string
    {
        $time_diff = time() - strtotime($timestamp);

        if ($time_diff < MINUTE_IN_SECONDS) {
            return __('Just now', 'quickjump-admin');
        }

        if ($time_diff < HOUR_IN_SECONDS) {
            $minutes = floor($time_diff / MINUTE_IN_SECONDS);
            /* translators: %d: Number of minutes */
            return sprintf(_n('%d min ago', '%d mins ago', $minutes, 'quickjump-admin'), $minutes);
        }

        if ($time_diff < DAY_IN_SECONDS) {
            $hours = floor($time_diff / HOUR_IN_SECONDS);
            /* translators: %d: Number of hours */
            return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'quickjump-admin'), $hours);
        }

        if ($time_diff < WEEK_IN_SECONDS) {
            $days = floor($time_diff / DAY_IN_SECONDS);
            /* translators: %d: Number of days */
            return sprintf(_n('%d day ago', '%d days ago', $days, 'quickjump-admin'), $days);
        }

        // Format as date for older items
        return date_i18n(get_option('date_format'), strtotime($timestamp));
    }

    /**
     * Enqueue CSS and JavaScript assets.
     *
     * @return void
     */
    public function enqueue_assets(): void
    {
        // Only for users who can see the menu
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            return;
        }

        // Only if admin bar is showing
        if (!is_admin_bar_showing()) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'quickjump-admin',
            QUICKJUMP_ADMIN_URL . 'admin/css/admin.css',
            array('dashicons'),
            QUICKJUMP_ADMIN_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'quickjump-admin',
            QUICKJUMP_ADMIN_URL . 'admin/js/admin.js',
            array('jquery'),
            QUICKJUMP_ADMIN_VERSION,
            true
        );

        // Localize script
        wp_localize_script('quickjump-admin', 'quickjumpAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quickjump_admin_nonce'),
            'i18n' => array(
                'confirmClear' => __('Are you sure you want to clear your history? Pinned items will be kept.', 'quickjump-admin'),
                'noResults' => __('No results found.', 'quickjump-admin'),
                'searching' => __('Searching...', 'quickjump-admin'),
                'pinned' => __('Pinned', 'quickjump-admin'),
                'unpinned' => __('Unpinned', 'quickjump-admin'),
                'justNow' => __('Just now', 'quickjump-admin'),
                'minAgo' => __('%d min ago', 'quickjump-admin'),
                'minsAgo' => __('%d mins ago', 'quickjump-admin'),
                'hourAgo' => __('%d hour ago', 'quickjump-admin'),
                'hoursAgo' => __('%d hours ago', 'quickjump-admin'),
                'dayAgo' => __('%d day ago', 'quickjump-admin'),
                'daysAgo' => __('%d days ago', 'quickjump-admin'),
            ),
        ));
    }

    /**
     * AJAX handler: Get links.
     *
     * @return void
     */
    public function ajax_get_links(): void
    {
        check_ajax_referer('quickjump_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'quickjump-admin')), 403);
        }

        $user_id = get_current_user_id();

        // Get settings
        $recent_count = (int) get_option('quickjump_admin_recent_links_count', 10);
        $recent_hours = (int) get_option('quickjump_admin_recent_links_hours', 24);
        $mostused_count = (int) get_option('quickjump_admin_mostused_links_count', 10);
        $mostused_days = (int) get_option('quickjump_admin_mostused_links_days', 30);

        wp_send_json_success(array(
            'pinned' => $this->database->get_pinned_links($user_id),
            'recent' => $this->database->get_recent_links($user_id, $recent_count, $recent_hours),
            'mostused' => $this->database->get_most_used_links($user_id, $mostused_count, $mostused_days),
        ));
    }

    /**
     * AJAX handler: Toggle pin.
     *
     * @return void
     */
    public function ajax_toggle_pin(): void
    {
        check_ajax_referer('quickjump_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'quickjump-admin')), 403);
        }

        $link_id = isset($_POST['link_id']) ? absint($_POST['link_id']) : 0;

        if (!$link_id) {
            wp_send_json_error(array('message' => __('Invalid link ID.', 'quickjump-admin')), 400);
        }

        $user_id = get_current_user_id();
        $result = $this->database->toggle_pin($user_id, $link_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Pin status updated.', 'quickjump-admin')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update pin status.', 'quickjump-admin')), 500);
        }
    }

    /**
     * AJAX handler: Search links.
     *
     * @return void
     */
    public function ajax_search(): void
    {
        check_ajax_referer('quickjump_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'quickjump-admin')), 403);
        }

        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

        if (strlen($search) < 2) {
            wp_send_json_success(array('results' => array()));
        }

        $user_id = get_current_user_id();
        $results = $this->database->search_links($user_id, $search);

        wp_send_json_success(array('results' => $results));
    }

    /**
     * AJAX handler: Clear history.
     *
     * @return void
     */
    public function ajax_clear_history(): void
    {
        check_ajax_referer('quickjump_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'quickjump-admin')), 403);
        }

        $user_id = get_current_user_id();
        $keep_pinned = isset($_POST['keep_pinned']) ? (bool) $_POST['keep_pinned'] : true;

        $deleted = $this->database->clear_history($user_id, $keep_pinned);

        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: %d: Number of deleted items */
                _n('%d item deleted.', '%d items deleted.', $deleted, 'quickjump-admin'),
                $deleted
            ),
            'deleted' => $deleted,
        ));
    }

    /**
     * AJAX handler: Rename a link.
     *
     * @return void
     */
    public function ajax_rename_link(): void
    {
        check_ajax_referer('quickjump_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'quickjump-admin')), 403);
        }

        $link_id = isset($_POST['link_id']) ? absint($_POST['link_id']) : 0;
        $new_title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';

        if (!$link_id) {
            wp_send_json_error(array('message' => __('Invalid link ID.', 'quickjump-admin')), 400);
        }

        if (empty($new_title)) {
            wp_send_json_error(array('message' => __('Title cannot be empty.', 'quickjump-admin')), 400);
        }

        $user_id = get_current_user_id();
        $result = $this->database->update_link_title($user_id, $link_id, $new_title);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Title updated.', 'quickjump-admin'),
                'title' => $new_title,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update title.', 'quickjump-admin')), 500);
        }
    }

    /**
     * AJAX handler for hiding a link (adding URL to excluded patterns).
     *
     * @return void
     */
    public function ajax_hide_link(): void
    {
        check_ajax_referer('quickjump_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'quickjump-admin')), 403);
        }

        $url = isset($_POST['url']) ? sanitize_text_field(wp_unslash($_POST['url'])) : '';

        if (empty($url)) {
            wp_send_json_error(array('message' => __('Invalid URL.', 'quickjump-admin')), 400);
        }

        // Get current excluded patterns
        $patterns = get_option('quickjump_admin_excluded_patterns', '');

        // Append the new URL on a new line
        if (!empty($patterns)) {
            $patterns .= "\n" . $url;
        } else {
            $patterns = $url;
        }

        // Update the option
        update_option('quickjump_admin_excluded_patterns', $patterns);

        wp_send_json_success(array(
            'message' => __('Link hidden from menu.', 'quickjump-admin'),
        ));
    }
}
