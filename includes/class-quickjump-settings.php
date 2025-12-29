<?php
/**
 * QuickJump Admin Settings
 *
 * Handles the plugin settings page.
 *
 * @package QuickJump_Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class QuickJump_Settings
 *
 * Creates and manages the plugin settings page.
 */
class QuickJump_Settings
{

    /**
     * Database handler.
     *
     * @var QuickJump_Database
     */
    private QuickJump_Database $database;

    /**
     * Settings page slug.
     *
     * @var string
     */
    private const PAGE_SLUG = 'quickjump-admin';

    /**
     * Option group name.
     *
     * @var string
     */
    private const OPTION_GROUP = 'quickjump_admin_options';

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
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_assets'));
    }

    /**
     * Add settings page to admin menu.
     *
     * @return void
     */
    public function add_settings_page(): void
    {
        add_options_page(
            __('QuickJump Admin Settings', 'quickjump-admin'),
            __('QuickJump Admin', 'quickjump-admin'),
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings.
     *
     * @return void
     */
    public function register_settings(): void
    {
        // Display section
        add_settings_section(
            'quickjump_display_section',
            __('Display Settings', 'quickjump-admin'),
            array($this, 'render_display_section'),
            self::PAGE_SLUG
        );

        // Recent links count
        register_setting(self::OPTION_GROUP, 'quickjump_admin_recent_links_count', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 10,
        ));
        add_settings_field(
            'recent_links_count',
            __('Number of recent links', 'quickjump-admin'),
            array($this, 'render_number_field'),
            self::PAGE_SLUG,
            'quickjump_display_section',
            array(
                'name' => 'quickjump_admin_recent_links_count',
                'default' => 10,
                'min' => 1,
                'max' => 50,
                'description' => __('Maximum number of recent links to display.', 'quickjump-admin'),
            )
        );

        // Recent links hours
        register_setting(self::OPTION_GROUP, 'quickjump_admin_recent_links_hours', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 24,
        ));
        add_settings_field(
            'recent_links_hours',
            __('Recent links time window (hours)', 'quickjump-admin'),
            array($this, 'render_number_field'),
            self::PAGE_SLUG,
            'quickjump_display_section',
            array(
                'name' => 'quickjump_admin_recent_links_hours',
                'default' => 24,
                'min' => 1,
                'max' => 168,
                'description' => __('Only show links accessed within this time window.', 'quickjump-admin'),
            )
        );

        // Most used links count
        register_setting(self::OPTION_GROUP, 'quickjump_admin_mostused_links_count', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 10,
        ));
        add_settings_field(
            'mostused_links_count',
            __('Number of most-used links', 'quickjump-admin'),
            array($this, 'render_number_field'),
            self::PAGE_SLUG,
            'quickjump_display_section',
            array(
                'name' => 'quickjump_admin_mostused_links_count',
                'default' => 10,
                'min' => 1,
                'max' => 50,
                'description' => __('Maximum number of most-used links to display.', 'quickjump-admin'),
            )
        );

        // Most used links days
        register_setting(self::OPTION_GROUP, 'quickjump_admin_mostused_links_days', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 30,
        ));
        add_settings_field(
            'mostused_links_days',
            __('Most-used links time window (days)', 'quickjump-admin'),
            array($this, 'render_number_field'),
            self::PAGE_SLUG,
            'quickjump_display_section',
            array(
                'name' => 'quickjump_admin_mostused_links_days',
                'default' => 30,
                'min' => 1,
                'max' => 365,
                'description' => __('Calculate most-used links based on this time period.', 'quickjump-admin'),
            )
        );

        // Show timestamps
        register_setting(self::OPTION_GROUP, 'quickjump_admin_show_timestamps', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => true,
        ));
        add_settings_field(
            'show_timestamps',
            __('Show timestamps', 'quickjump-admin'),
            array($this, 'render_checkbox_field'),
            self::PAGE_SLUG,
            'quickjump_display_section',
            array(
                'name' => 'quickjump_admin_show_timestamps',
                'label' => __('Display relative timestamps on recent links (e.g., "2 hours ago")', 'quickjump-admin'),
                'default' => true,
            )
        );

        // Show access count
        register_setting(self::OPTION_GROUP, 'quickjump_admin_show_access_count', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => true,
        ));
        add_settings_field(
            'show_access_count',
            __('Show access count', 'quickjump-admin'),
            array($this, 'render_checkbox_field'),
            self::PAGE_SLUG,
            'quickjump_display_section',
            array(
                'name' => 'quickjump_admin_show_access_count',
                'label' => __('Display visit count badges on most-used links', 'quickjump-admin'),
                'default' => true,
            )
        );

        // Show search bar
        register_setting(self::OPTION_GROUP, 'quickjump_admin_show_search', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => true,
        ));
        add_settings_field(
            'show_search',
            __('Show search bar', 'quickjump-admin'),
            array($this, 'render_checkbox_field'),
            self::PAGE_SLUG,
            'quickjump_display_section',
            array(
                'name' => 'quickjump_admin_show_search',
                'label' => __('Display the search field in the dropdown', 'quickjump-admin'),
                'default' => true,
            )
        );

        // Side by side layout
        register_setting(self::OPTION_GROUP, 'quickjump_admin_side_by_side', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false,
        ));
        add_settings_field(
            'side_by_side',
            __('Side by side layout', 'quickjump-admin'),
            array($this, 'render_checkbox_field'),
            self::PAGE_SLUG,
            'quickjump_display_section',
            array(
                'name' => 'quickjump_admin_side_by_side',
                'label' => __('Display Recent and Most Used sections side by side', 'quickjump-admin'),
                'default' => false,
            )
        );

        // Open links in new window
        register_setting(self::OPTION_GROUP, 'quickjump_admin_open_new_window', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false,
        ));
        add_settings_field(
            'open_new_window',
            __('Open links in new window', 'quickjump-admin'),
            array($this, 'render_checkbox_field'),
            self::PAGE_SLUG,
            'quickjump_display_section',
            array(
                'name' => 'quickjump_admin_open_new_window',
                'label' => __('Open links in a new browser window/tab', 'quickjump-admin'),
                'default' => false,
            )
        );

        // General section
        add_settings_section(
            'quickjump_general_section',
            __('General Settings', 'quickjump-admin'),
            array($this, 'render_general_section'),
            self::PAGE_SLUG
        );

        // Button label
        register_setting(self::OPTION_GROUP, 'quickjump_admin_button_label', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => __('Shortcuts', 'quickjump-admin'),
        ));
        add_settings_field(
            'button_label',
            __('Admin bar button label', 'quickjump-admin'),
            array($this, 'render_text_field'),
            self::PAGE_SLUG,
            'quickjump_general_section',
            array(
                'name' => 'quickjump_admin_button_label',
                'default' => __('Shortcuts', 'quickjump-admin'),
                'description' => __('Custom label for the admin bar button.', 'quickjump-admin'),
            )
        );

        // Data section
        add_settings_section(
            'quickjump_data_section',
            __('Data Management', 'quickjump-admin'),
            array($this, 'render_data_section'),
            self::PAGE_SLUG
        );

        // Retention days
        register_setting(self::OPTION_GROUP, 'quickjump_admin_retention_days', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 90,
        ));
        add_settings_field(
            'retention_days',
            __('Data retention period (days)', 'quickjump-admin'),
            array($this, 'render_number_field'),
            self::PAGE_SLUG,
            'quickjump_data_section',
            array(
                'name' => 'quickjump_admin_retention_days',
                'default' => 90,
                'min' => 7,
                'max' => 365,
                'description' => __('Automatically delete data older than this. Pinned items are kept.', 'quickjump-admin'),
            )
        );

        // Excluded patterns
        register_setting(self::OPTION_GROUP, 'quickjump_admin_excluded_patterns', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => '',
        ));
        add_settings_field(
            'excluded_patterns',
            __('Excluded URL patterns', 'quickjump-admin'),
            array($this, 'render_textarea_field'),
            self::PAGE_SLUG,
            'quickjump_data_section',
            array(
                'name' => 'quickjump_admin_excluded_patterns',
                'default' => '',
                'rows' => 5,
                'description' => __('One pattern per line. Supports regex or simple string matching.', 'quickjump-admin'),
                'placeholder' => "/admin\.php\?page=secret-page/\n/customizer/",
            )
        );

        // Clear history button (rendered in section callback)
    }

    /**
     * Sanitize checkbox value.
     *
     * @param mixed $value Input value.
     * @return bool Sanitized boolean.
     */
    public function sanitize_checkbox($value): bool
    {
        return (bool) $value;
    }

    /**
     * Render display section description.
     *
     * @return void
     */
    public function render_display_section(): void
    {
        echo '<p>' . esc_html__('Configure how links are displayed in the dropdown menu.', 'quickjump-admin') . '</p>';
    }

    /**
     * Render general section description.
     *
     * @return void
     */
    public function render_general_section(): void
    {
        echo '<p>' . esc_html__('General plugin settings.', 'quickjump-admin') . '</p>';
    }

    /**
     * Render data section description.
     *
     * @return void
     */
    public function render_data_section(): void
    {
        echo '<p>' . esc_html__('Manage tracked data and configure cleanup settings.', 'quickjump-admin') . '</p>';
    }

    /**
     * Render a number input field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_number_field(array $args): void
    {
        $value = get_option($args['name'], $args['default']);
        $min = $args['min'] ?? 1;
        $max = $args['max'] ?? 100;
        ?>
        <input type="number" id="<?php echo esc_attr($args['name']); ?>" name="<?php echo esc_attr($args['name']); ?>"
            value="<?php echo esc_attr($value); ?>" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>"
            class="small-text">
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Render a text input field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_text_field(array $args): void
    {
        $value = get_option($args['name'], $args['default']);
        ?>
        <input type="text" id="<?php echo esc_attr($args['name']); ?>" name="<?php echo esc_attr($args['name']); ?>"
            value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Render a checkbox field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_checkbox_field(array $args): void
    {
        // Get default from args, fallback to true for backward compatibility
        $default = isset($args['default']) ? $args['default'] : true;
        $value = (bool) get_option($args['name'], $default);
        ?>
        <label for="<?php echo esc_attr($args['name']); ?>">
            <input type="hidden" name="<?php echo esc_attr($args['name']); ?>" value="0">
            <input type="checkbox" id="<?php echo esc_attr($args['name']); ?>" name="<?php echo esc_attr($args['name']); ?>"
                value="1" <?php checked($value, true); ?>>
            <?php echo esc_html($args['label']); ?>
        </label>
        <?php
    }

    /**
     * Render a textarea field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_textarea_field(array $args): void
    {
        $value = get_option($args['name'], $args['default']);
        $rows = $args['rows'] ?? 5;
        $placeholder = $args['placeholder'] ?? '';
        ?>
        <textarea id="<?php echo esc_attr($args['name']); ?>" name="<?php echo esc_attr($args['name']); ?>"
            rows="<?php echo esc_attr($rows); ?>" class="large-text code"
            placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_textarea($value); ?></textarea>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle clear history action
        if (isset($_POST['quickjump_clear_history']) && check_admin_referer('quickjump_clear_history_action', 'quickjump_clear_history_nonce')) {
            $user_id = get_current_user_id();
            $keep_pinned = isset($_POST['keep_pinned']) ? (bool) $_POST['keep_pinned'] : true;
            $deleted = $this->database->clear_history($user_id, $keep_pinned);

            add_settings_error(
                'quickjump_admin_messages',
                'quickjump_history_cleared',
                sprintf(
                    /* translators: %d: Number of deleted items */
                    _n('%d item deleted from your history.', '%d items deleted from your history.', $deleted, 'quickjump-admin'),
                    $deleted
                ),
                'success'
            );
        }

        $tabs = array(
            'general' => __('General', 'quickjump-admin'),
            'display' => __('Display', 'quickjump-admin'),
            'data' => __('Data Management', 'quickjump-admin'),
        );

        $default_tab = 'general';
        $current_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $tabs) ? sanitize_text_field(wp_unslash($_GET['tab'])) : $default_tab;

        // Define section mapping
        $section_map = array(
            'quickjump_general_section' => 'general',
            'quickjump_display_section' => 'display',
            'quickjump_data_section' => 'data',
        );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('quickjump_admin_messages'); ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?php echo esc_url(add_query_arg('tab', $key)); ?>"
                        class="nav-tab <?php echo $current_tab === $key ? 'nav-tab-active' : ''; ?>"
                        data-tab="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);

                // Custom Loop based on do_settings_sections()
                global $wp_settings_sections, $wp_settings_fields;
                $page = self::PAGE_SLUG;

                if (isset($wp_settings_sections[$page])) {
                    foreach ((array) $wp_settings_sections[$page] as $section) {
                        $tab_key = isset($section_map[$section['id']]) ? $section_map[$section['id']] : 'general';
                        $is_visible = $tab_key === $current_tab;

                        echo '<div class="qj-tab-content ' . ($is_visible ? 'active' : '') . '" data-tab="' . esc_attr($tab_key) . '">';

                        if ($section['title']) {
                            echo "<h2>{$section['title']}</h2>\n";
                        }

                        if ($section['callback']) {
                            call_user_func($section['callback'], $section);
                        }

                        if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']])) {
                            echo '</div>';
                            continue;
                        }

                        echo '<table class="form-table" role="presentation">';
                        do_settings_fields($page, $section['id']);
                        echo '</table>';

                        echo '</div>';
                    }
                }

                submit_button();
                ?>
            </form>

            <div class="qj-tab-content <?php echo $current_tab === 'data' ? 'active' : ''; ?>" data-tab="data">
                <hr>
                <h2><?php esc_html_e('Clear History', 'quickjump-admin'); ?></h2>
                <p><?php esc_html_e('Clear your navigation history. This action cannot be undone.', 'quickjump-admin'); ?></p>

                <form method="post" action="" id="quickjump-clear-history-form">
                    <?php wp_nonce_field('quickjump_clear_history_action', 'quickjump_clear_history_nonce'); ?>
                    <input type="hidden" name="quickjump_clear_history" value="1">

                    <label for="keep_pinned">
                        <input type="checkbox" id="keep_pinned" name="keep_pinned" value="1" checked>
                        <?php esc_html_e('Keep pinned items', 'quickjump-admin'); ?>
                    </label>
                    <br><br>

                    <button type="submit" class="button button-secondary" id="quickjump-clear-btn">
                        <?php esc_html_e('Clear History', 'quickjump-admin'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue settings page assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_settings_assets(string $hook): void
    {
        if ('settings_page_' . self::PAGE_SLUG !== $hook) {
            return;
        }

        // Inline script for confirmation
        wp_add_inline_script('jquery', "
			jQuery(document).ready(function($) {
				$('#quickjump-clear-history-form').on('submit', function(e) {
					if (!confirm('" . esc_js(__('Are you sure you want to clear your history?', 'quickjump-admin')) . "')) {
						e.preventDefault();
					}
				});
			});
		");
    }
}
