<?php
/**
 * Plugin Name:       QuickJump Admin
 * Plugin URI:        https://github.com/guilamu/quickjump-admin
 * Description:       Navigate faster in WordPress admin with intelligent shortcuts to your recently and frequently accessed pages.
 * Version:           1.1.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Guilamu
 * Author URI:        https://github.com/guilamu
 * License:           AGPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain:       quickjump-admin
 * Domain Path:       /languages
 * Update URI:        https://github.com/guilamu/quickjump-admin/
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('QUICKJUMP_ADMIN_VERSION', '1.1.1');

/**
 * Register with Guilamu Bug Reporter
 */
add_action('plugins_loaded', function () {
    if (class_exists('Guilamu_Bug_Reporter')) {
        Guilamu_Bug_Reporter::register(array(
            'slug'        => 'quickjump-admin',
            'name'        => 'QuickJump Admin',
            'version'     => QUICKJUMP_ADMIN_VERSION,
            'github_repo' => 'guilamu/quickjump-admin',
        ));
    }
}, 20);

/**
 * Add "Report a Bug" link to plugin row meta.
 *
 * @param array  $links Plugin row links.
 * @param string $file  Plugin file.
 * @return array Modified links.
 */
add_filter('plugin_row_meta', function ($links, $file) {
    if (plugin_basename(__FILE__) !== $file) {
        return $links;
    }

    if (class_exists('Guilamu_Bug_Reporter')) {
        $links[] = sprintf(
            '<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="quickjump-admin" data-plugin-name="%s">%s</a>',
            esc_attr__('QuickJump Admin', 'quickjump-admin'),
            esc_html__('🐛 Report a Bug', 'quickjump-admin')
        );
    } else {
        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/guilamu/guilamu-bug-reporter/releases',
            esc_html__('🐛 Report a Bug (install Bug Reporter)', 'quickjump-admin')
        );
    }

    return $links;
}, 10, 2);
define('QUICKJUMP_ADMIN_PATH', plugin_dir_path(__FILE__));
define('QUICKJUMP_ADMIN_URL', plugin_dir_url(__FILE__));
define('QUICKJUMP_ADMIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main QuickJump Admin Plugin Class
 *
 * Handles plugin initialization, loading dependencies, and setup.
 */
final class QuickJump_Admin
{

    /**
     * Single instance of the class.
     *
     * @var QuickJump_Admin|null
     */
    private static ?QuickJump_Admin $instance = null;

    /**
     * Database handler instance.
     *
     * @var QuickJump_Database|null
     */
    public ?QuickJump_Database $database = null;

    /**
     * Tracker instance.
     *
     * @var QuickJump_Tracker|null
     */
    public ?QuickJump_Tracker $tracker = null;

    /**
     * Menu handler instance.
     *
     * @var QuickJump_Menu|null
     */
    public ?QuickJump_Menu $menu = null;

    /**
     * Settings handler instance.
     *
     * @var QuickJump_Settings|null
     */
    public ?QuickJump_Settings $settings = null;

    /**
     * Get single instance of the class.
     *
     * @return QuickJump_Admin
     */
    public static function instance(): QuickJump_Admin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Set up hooks and load dependencies.
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required class files.
     *
     * @return void
     */
    private function load_dependencies(): void
    {
        require_once QUICKJUMP_ADMIN_PATH . 'includes/class-quickjump-database.php';
        require_once QUICKJUMP_ADMIN_PATH . 'includes/class-quickjump-tracker.php';
        require_once QUICKJUMP_ADMIN_PATH . 'includes/class-quickjump-menu.php';
        require_once QUICKJUMP_ADMIN_PATH . 'includes/class-quickjump-settings.php';
        require_once QUICKJUMP_ADMIN_PATH . 'includes/class-github-updater.php';
    }

    /**
     * Initialize WordPress hooks.
     *
     * @return void
     */
    private function init_hooks(): void
    {
        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize on plugins_loaded
        add_action('plugins_loaded', array($this, 'init'));

        // Load text domain for translations
        add_action('init', array($this, 'load_textdomain'));

        // Schedule cleanup cron
        add_action('quickjump_admin_cleanup', array($this, 'run_cleanup'));

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . QUICKJUMP_ADMIN_BASENAME, array($this, 'add_settings_link'));
    }

    /**
     * Initialize plugin components.
     *
     * @return void
     */
    public function init(): void
    {
        // Initialize components
        $this->database = new QuickJump_Database();
        $this->tracker = new QuickJump_Tracker($this->database);
        $this->menu = new QuickJump_Menu($this->database);
        $this->settings = new QuickJump_Settings($this->database);
    }

    /**
     * Load plugin text domain for translations.
     *
     * @return void
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'quickjump-admin',
            false,
            dirname(QUICKJUMP_ADMIN_BASENAME) . '/languages'
        );
    }

    /**
     * Plugin activation.
     *
     * @return void
     */
    public function activate(): void
    {
        // Create database table
        require_once QUICKJUMP_ADMIN_PATH . 'includes/class-quickjump-database.php';
        $database = new QuickJump_Database();
        $database->create_table();

        // Set default options
        $this->set_default_options();

        // Schedule cleanup cron if not already scheduled
        if (!wp_next_scheduled('quickjump_admin_cleanup')) {
            wp_schedule_event(time(), 'daily', 'quickjump_admin_cleanup');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     *
     * @return void
     */
    public function deactivate(): void
    {
        // Clear scheduled hook
        wp_clear_scheduled_hook('quickjump_admin_cleanup');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Set default plugin options.
     *
     * @return void
     */
    private function set_default_options(): void
    {
        $defaults = array(
            'recent_links_count' => 10,
            'recent_links_hours' => 24,
            'mostused_links_count' => 10,
            'mostused_links_days' => 30,
            'retention_days' => 90,
            'excluded_patterns' => '',
            'button_label' => __('Shortcuts', 'quickjump-admin'),
            'show_timestamps' => true,
            'show_access_count' => true,
            'show_search' => true,
            'side_by_side' => false,
        );

        // Only add options if they don't exist
        foreach ($defaults as $key => $value) {
            if (false === get_option('quickjump_admin_' . $key)) {
                add_option('quickjump_admin_' . $key, $value);
            }
        }
    }

    /**
     * Run cleanup of old data.
     *
     * @return void
     */
    public function run_cleanup(): void
    {
        if ($this->database) {
            $retention_days = (int) get_option('quickjump_admin_retention_days', 90);
            $this->database->cleanup_old_data($retention_days);
        }
    }

    /**
     * Add settings link to plugin actions.
     *
     * @param array $links Existing action links.
     * @return array Modified action links.
     */
    public function add_settings_link(array $links): array
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=quickjump-admin')),
            esc_html__('Settings', 'quickjump-admin')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Get plugin option with default fallback.
     *
     * @param string $key     Option key (without prefix).
     * @param mixed  $default Default value.
     * @return mixed Option value.
     */
    public static function get_option(string $key, $default = false)
    {
        return get_option('quickjump_admin_' . $key, $default);
    }
}

/**
 * Returns the main instance of QuickJump_Admin.
 *
 * @return QuickJump_Admin
 */
function quickjump_admin(): QuickJump_Admin
{
    return QuickJump_Admin::instance();
}

// Initialize the plugin
quickjump_admin();
