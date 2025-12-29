<?php
/**
 * QuickJump Admin Uninstaller
 *
 * Cleans up all plugin data when uninstalled.
 *
 * @package QuickJump_Admin
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up all plugin data on uninstall.
 */
function quickjump_admin_uninstall()
{
    global $wpdb;

    // Drop the custom table
    $table_name = $wpdb->prefix . 'quickjump_admin_visits';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

    // Delete all plugin options
    $options = array(
        'quickjump_admin_recent_links_count',
        'quickjump_admin_recent_links_hours',
        'quickjump_admin_mostused_links_count',
        'quickjump_admin_mostused_links_days',
        'quickjump_admin_retention_days',
        'quickjump_admin_excluded_patterns',
        'quickjump_admin_button_label',
        'quickjump_admin_show_timestamps',
        'quickjump_admin_show_access_count',
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // Delete all transients
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_quickjump_%' OR option_name LIKE '_transient_timeout_quickjump_%'"
    );

    // Clear any scheduled hooks
    wp_clear_scheduled_hook('quickjump_admin_cleanup');

    // For multisite, clean up each site
    if (is_multisite()) {
        $sites = get_sites(array('fields' => 'ids'));

        foreach ($sites as $site_id) {
            switch_to_blog($site_id);

            // Drop table for this site
            $table_name = $wpdb->prefix . 'quickjump_admin_visits';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

            // Delete options for this site
            foreach ($options as $option) {
                delete_option($option);
            }

            // Delete transients for this site
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_quickjump_%' OR option_name LIKE '_transient_timeout_quickjump_%'"
            );

            restore_current_blog();
        }
    }
}

// Run the uninstaller
quickjump_admin_uninstall();
