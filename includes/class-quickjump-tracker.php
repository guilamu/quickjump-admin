<?php
/**
 * QuickJump Admin Tracker
 *
 * Tracks admin page visits for the current user.
 *
 * @package QuickJump_Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class QuickJump_Tracker
 *
 * Handles tracking of admin page visits.
 */
class QuickJump_Tracker
{

    /**
     * Database handler.
     *
     * @var QuickJump_Database
     */
    private QuickJump_Database $database;

    /**
     * Excluded URL patterns (regex).
     *
     * @var array
     */
    private array $excluded_patterns = array();

    /**
     * Constructor.
     *
     * @param QuickJump_Database $database Database handler.
     */
    public function __construct(QuickJump_Database $database)
    {
        $this->database = $database;
        $this->load_excluded_patterns();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks.
     *
     * @return void
     */
    private function init_hooks(): void
    {
        // Track page visits on in_admin_header - this fires after page title is set
        add_action('in_admin_header', array($this, 'track_page_visit'));
    }

    /**
     * Load excluded URL patterns from settings.
     *
     * @return void
     */
    private function load_excluded_patterns(): void
    {
        $patterns_string = get_option('quickjump_admin_excluded_patterns', '');

        if (empty($patterns_string)) {
            $this->excluded_patterns = array();
            return;
        }

        // Normalize line endings (handle Windows \r\n, Mac \r, and Unix \n)
        $patterns_string = str_replace(array("\r\n", "\r"), "\n", $patterns_string);

        // Split by newlines and filter empty lines
        $patterns = array_filter(
            array_map('trim', explode("\n", $patterns_string))
        );

        $this->excluded_patterns = $patterns;
    }

    /**
     * Track the current page visit.
     *
     * @return void
     */
    public function track_page_visit(): void
    {
        // Only track for logged-in users
        if (!is_user_logged_in()) {
            return;
        }

        // Only track in admin
        if (!is_admin()) {
            return;
        }

        // Don't track AJAX requests
        if (wp_doing_ajax()) {
            return;
        }

        // Don't track cron requests
        if (wp_doing_cron()) {
            return;
        }

        // Check minimum capability
        if (!current_user_can('edit_posts')) {
            return;
        }

        // Get current URL
        $current_url = $this->get_current_admin_url();

        // Check if URL should be excluded
        if ($this->is_url_excluded($current_url)) {
            return;
        }

        // Get page title
        $page_title = $this->get_page_title();

        // If we still can't determine a title, use URL-based fallback
        if (empty($page_title)) {
            $page_title = $this->get_fallback_title($current_url);
        }

        // Skip if we really can't determine any title
        if (empty($page_title)) {
            return;
        }

        // Get current user ID
        $user_id = get_current_user_id();

        // Record the visit
        $this->database->record_visit($user_id, $current_url, $page_title);
    }

    /**
     * Get the current admin URL (cleaned).
     *
     * @return string Current admin URL.
     */
    private function get_current_admin_url(): string
    {
        // Get the current admin page URL
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        $current_url = $protocol . $host . $request_uri;

        // Remove certain query parameters that we don't want to track separately
        $url_parts = wp_parse_url($current_url);

        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $query_params);

            // Remove transient/action parameters
            $params_to_remove = array(
                '_wpnonce',
                '_wp_http_referer',
                'message',
                'settings-updated',
                'updated',
                'deleted',
                'trashed',
                'untrashed',
                'ids',
                'locked',
                'skipped',
            );

            foreach ($params_to_remove as $param) {
                unset($query_params[$param]);
            }

            // Rebuild URL
            $clean_url = $url_parts['scheme'] . '://' . $url_parts['host'];

            if (isset($url_parts['path'])) {
                $clean_url .= $url_parts['path'];
            }

            if (!empty($query_params)) {
                $clean_url .= '?' . http_build_query($query_params);
            }

            return $clean_url;
        }

        return $current_url;
    }

    /**
     * Get the current page title.
     *
     * @return string Page title.
     */
    private function get_page_title(): string
    {
        global $title, $pagenow, $plugin_page;

        // Try to get the title from global (most reliable after in_admin_header)
        if (!empty($title)) {
            return sanitize_text_field($title);
        }

        // Try to get from current screen
        $screen = get_current_screen();
        if ($screen) {
            // Get post type label for edit screens
            if ('edit' === $screen->base && !empty($screen->post_type)) {
                $post_type_obj = get_post_type_object($screen->post_type);
                if ($post_type_obj) {
                    return sanitize_text_field($post_type_obj->labels->name);
                }
            }

            // Get title for post edit screen
            if ('post' === $screen->base && !empty($screen->post_type)) {
                $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
                if ($post_id) {
                    $post = get_post($post_id);
                    if ($post) {
                        $post_type_obj = get_post_type_object($screen->post_type);
                        $type_label = $post_type_obj ? $post_type_obj->labels->singular_name : '';
                        return sanitize_text_field(
                            sprintf(
                                /* translators: 1: Post type label, 2: Post title */
                                __('%1$s: %2$s', 'quickjump-admin'),
                                $type_label,
                                $post->post_title
                            )
                        );
                    }
                }
            }
        }

        // For plugin pages (admin.php?page=xxx), try to get from menu
        if (!empty($plugin_page)) {
            $menu_title = $this->get_menu_title($plugin_page);
            if (!empty($menu_title)) {
                return sanitize_text_field($menu_title);
            }
        }

        // Use pagenow as fallback
        if (!empty($pagenow)) {
            return $this->format_pagenow_title($pagenow);
        }

        return '';
    }

    /**
     * Get menu title for a plugin page.
     *
     * @param string $plugin_page Plugin page slug.
     * @return string Menu title or empty string.
     */
    private function get_menu_title(string $plugin_page): string
    {
        global $submenu, $menu;

        // Search in submenus
        if (!empty($submenu)) {
            foreach ($submenu as $parent_slug => $items) {
                foreach ($items as $item) {
                    if (isset($item[2]) && $item[2] === $plugin_page) {
                        return isset($item[0]) ? wp_strip_all_tags($item[0]) : '';
                    }
                }
            }
        }

        // Search in top-level menus
        if (!empty($menu)) {
            foreach ($menu as $item) {
                if (isset($item[2]) && $item[2] === $plugin_page) {
                    return isset($item[0]) ? wp_strip_all_tags($item[0]) : '';
                }
            }
        }

        return '';
    }

    /**
     * Get a fallback title from the URL.
     *
     * @param string $url Page URL.
     * @return string Fallback title.
     */
    private function get_fallback_title(string $url): string
    {
        $query = wp_parse_url($url, PHP_URL_QUERY);

        if (!empty($query)) {
            parse_str($query, $params);

            // For admin.php?page=xxx URLs, use the page parameter
            if (isset($params['page'])) {
                $page_slug = $params['page'];
                // Convert slug to readable title
                $title = str_replace(array('-', '_'), ' ', $page_slug);
                $title = ucwords($title);

                // If there's an ID parameter, append it for better identification
                if (isset($params['id'])) {
                    $title .= ' #' . $params['id'];
                }

                return $title;
            }
        }

        // Use path as last resort
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!empty($path)) {
            $filename = basename($path, '.php');
            $title = str_replace(array('-', '_'), ' ', $filename);
            return ucwords($title);
        }

        return '';
    }

    /**
     * Format screen ID into readable title.
     *
     * @param string $screen_id Screen ID.
     * @return string Formatted title.
     */
    private function format_screen_title(string $screen_id): string
    {
        // Remove common prefixes
        $title = str_replace(
            array('toplevel_page_', 'settings_page_', 'tools_page_', 'edit-'),
            '',
            $screen_id
        );

        // Convert dashes and underscores to spaces
        $title = str_replace(array('-', '_'), ' ', $title);

        // Title case
        return ucwords($title);
    }

    /**
     * Format pagenow into readable title.
     *
     * @param string $pagenow Page now value.
     * @return string Formatted title.
     */
    private function format_pagenow_title(string $pagenow): string
    {
        // Known page titles
        $titles = array(
            'index.php' => __('Dashboard', 'quickjump-admin'),
            'edit.php' => __('Posts', 'quickjump-admin'),
            'post-new.php' => __('Add New Post', 'quickjump-admin'),
            'upload.php' => __('Media Library', 'quickjump-admin'),
            'media-new.php' => __('Add New Media', 'quickjump-admin'),
            'edit-comments.php' => __('Comments', 'quickjump-admin'),
            'themes.php' => __('Themes', 'quickjump-admin'),
            'widgets.php' => __('Widgets', 'quickjump-admin'),
            'nav-menus.php' => __('Menus', 'quickjump-admin'),
            'customize.php' => __('Customize', 'quickjump-admin'),
            'plugins.php' => __('Plugins', 'quickjump-admin'),
            'plugin-install.php' => __('Add Plugins', 'quickjump-admin'),
            'plugin-editor.php' => __('Plugin Editor', 'quickjump-admin'),
            'users.php' => __('Users', 'quickjump-admin'),
            'user-new.php' => __('Add New User', 'quickjump-admin'),
            'profile.php' => __('Your Profile', 'quickjump-admin'),
            'tools.php' => __('Tools', 'quickjump-admin'),
            'import.php' => __('Import', 'quickjump-admin'),
            'export.php' => __('Export', 'quickjump-admin'),
            'options-general.php' => __('General Settings', 'quickjump-admin'),
            'options-writing.php' => __('Writing Settings', 'quickjump-admin'),
            'options-reading.php' => __('Reading Settings', 'quickjump-admin'),
            'options-discussion.php' => __('Discussion Settings', 'quickjump-admin'),
            'options-media.php' => __('Media Settings', 'quickjump-admin'),
            'options-permalink.php' => __('Permalink Settings', 'quickjump-admin'),
            'options-privacy.php' => __('Privacy Settings', 'quickjump-admin'),
            'update-core.php' => __('Updates', 'quickjump-admin'),
            'admin.php' => '', // Will use fallback for plugin pages
        );

        if (isset($titles[$pagenow])) {
            return $titles[$pagenow];
        }

        // Format unknown page names
        $title = str_replace('.php', '', $pagenow);
        $title = str_replace(array('-', '_'), ' ', $title);
        return ucwords($title);
    }

    /**
     * Check if URL should be excluded from tracking.
     *
     * @param string $url URL to check.
     * @return bool True if excluded, false otherwise.
     */
    private function is_url_excluded(string $url): bool
    {
        // Default exclusions (always exclude)
        $default_exclusions = array(
            '/admin-ajax.php',
            '/async-upload.php',
            '/heartbeat.php',
            '/admin-post.php',
        );

        foreach ($default_exclusions as $exclusion) {
            if (false !== strpos($url, $exclusion)) {
                return true;
            }
        }

        // Check user-defined patterns
        foreach ($this->excluded_patterns as $pattern) {
            if (empty(trim($pattern))) {
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
}
