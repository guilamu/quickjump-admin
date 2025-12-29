<?php
/**
 * QuickJump Admin Database Handler
 *
 * Handles all database operations including table creation,
 * CRUD operations, and data cleanup.
 *
 * @package QuickJump_Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class QuickJump_Database
 *
 * Database operations for the QuickJump Admin plugin.
 */
class QuickJump_Database
{

    /**
     * Database table name (without prefix).
     *
     * @var string
     */
    private const TABLE_NAME = 'quickjump_admin_visits';

    /**
     * WordPress database object.
     *
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * Full table name with prefix.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Get full table name.
     *
     * @return string
     */
    public function get_table_name(): string
    {
        return $this->table_name;
    }

    /**
     * Create the visits tracking table.
     *
     * @return void
     */
    public function create_table(): void
    {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			url varchar(2048) NOT NULL,
			page_title varchar(255) NOT NULL DEFAULT '',
			last_accessed datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			access_count int(11) UNSIGNED NOT NULL DEFAULT 1,
			is_pinned tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY last_accessed (last_accessed),
			KEY user_url (user_id, url(191)),
			KEY user_pinned (user_id, is_pinned)
		) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Drop the visits table (used during uninstall).
     *
     * @return void
     */
    public function drop_table(): void
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
    }

    /**
     * Record a page visit.
     *
     * @param int    $user_id    User ID.
     * @param string $url        Page URL.
     * @param string $page_title Page title.
     * @return bool|int False on failure, number of affected rows on success.
     */
    public function record_visit(int $user_id, string $url, string $page_title)
    {
        // Check if record exists for this user and URL
        $existing = $this->get_visit_by_url($user_id, $url);

        if ($existing) {
            // Update existing record
            $result = $this->wpdb->update(
                $this->table_name,
                array(
                    'page_title' => $page_title,
                    'last_accessed' => current_time('mysql'),
                    'access_count' => $existing->access_count + 1,
                ),
                array('id' => $existing->id),
                array('%s', '%s', '%d'),
                array('%d')
            );

            // Clear cache so updated data shows immediately
            $this->clear_user_cache($user_id);

            return $result;
        }

        // Insert new record
        $result = $this->wpdb->insert(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'url' => $url,
                'page_title' => $page_title,
                'last_accessed' => current_time('mysql'),
                'access_count' => 1,
                'is_pinned' => 0,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%d', '%d', '%s')
        );

        // Clear cache so new data shows immediately
        $this->clear_user_cache($user_id);

        return $result;
    }

    /**
     * Get a visit record by URL for a specific user.
     *
     * @param int    $user_id User ID.
     * @param string $url     Page URL.
     * @return object|null Visit record or null.
     */
    public function get_visit_by_url(int $user_id, string $url): ?object
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d AND url = %s",
                $user_id,
                $url
            )
        );

        return $result ?: null;
    }

    /**
     * Get recent links for a user.
     *
     * @param int $user_id User ID.
     * @param int $limit   Maximum number of links.
     * @param int $hours   Time window in hours.
     * @return array List of recent visits.
     */
    public function get_recent_links(int $user_id, int $limit = 10, int $hours = 24): array
    {
        $cache_key = "quickjump_recent_{$user_id}_{$limit}_{$hours}";
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            return $cached;
        }

        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Exclude pinned items so they don't count toward the limit
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, url, page_title, last_accessed, access_count, is_pinned
				FROM {$this->table_name}
				WHERE user_id = %d AND last_accessed >= %s AND is_pinned = 0
				ORDER BY last_accessed DESC
				LIMIT %d",
                $user_id,
                $cutoff,
                $limit
            )
        );

        $results = $results ?: array();

        // Cache for 2 minutes
        set_transient($cache_key, $results, 2 * MINUTE_IN_SECONDS);

        return $results;
    }

    /**
     * Get most used links for a user.
     *
     * @param int $user_id User ID.
     * @param int $limit   Maximum number of links.
     * @param int $days    Time window in days.
     * @return array List of most used visits.
     */
    public function get_most_used_links(int $user_id, int $limit = 10, int $days = 30): array
    {
        $cache_key = "quickjump_mostused_{$user_id}_{$limit}_{$days}";
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            return $cached;
        }

        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Exclude pinned items so they don't count toward the limit
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, url, page_title, last_accessed, access_count, is_pinned
				FROM {$this->table_name}
				WHERE user_id = %d AND created_at >= %s AND is_pinned = 0
				ORDER BY access_count DESC, last_accessed DESC
				LIMIT %d",
                $user_id,
                $cutoff,
                $limit
            )
        );

        $results = $results ?: array();

        // Cache for 5 minutes
        set_transient($cache_key, $results, 5 * MINUTE_IN_SECONDS);

        return $results;
    }

    /**
     * Get pinned links for a user.
     *
     * @param int $user_id User ID.
     * @return array List of pinned visits.
     */
    public function get_pinned_links(int $user_id): array
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, url, page_title, last_accessed, access_count, is_pinned
				FROM {$this->table_name}
				WHERE user_id = %d AND is_pinned = 1
				ORDER BY page_title ASC",
                $user_id
            )
        );

        return $results ?: array();
    }

    /**
     * Toggle pin status for a link.
     *
     * @param int $user_id User ID.
     * @param int $link_id Link ID.
     * @return bool True on success, false on failure.
     */
    public function toggle_pin(int $user_id, int $link_id): bool
    {
        // Get current status
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $current = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT is_pinned FROM {$this->table_name} WHERE id = %d AND user_id = %d",
                $link_id,
                $user_id
            )
        );

        if (null === $current) {
            return false;
        }

        $new_status = $current ? 0 : 1;

        $result = $this->wpdb->update(
            $this->table_name,
            array('is_pinned' => $new_status),
            array(
                'id' => $link_id,
                'user_id' => $user_id,
            ),
            array('%d'),
            array('%d', '%d')
        );

        // Clear caches
        $this->clear_user_cache($user_id);

        return false !== $result;
    }

    /**
     * Update a link's title.
     *
     * @param int    $user_id   User ID.
     * @param int    $link_id   Link ID.
     * @param string $new_title New title.
     * @return bool True on success, false on failure.
     */
    public function update_link_title(int $user_id, int $link_id, string $new_title): bool
    {
        $result = $this->wpdb->update(
            $this->table_name,
            array('page_title' => $new_title),
            array(
                'id' => $link_id,
                'user_id' => $user_id,
            ),
            array('%s'),
            array('%d', '%d')
        );

        // Clear caches
        $this->clear_user_cache($user_id);

        return false !== $result;
    }

    /**
     * Clear user's history.
     *
     * @param int  $user_id       User ID.
     * @param bool $keep_pinned   Whether to keep pinned items.
     * @return int Number of deleted rows.
     */
    public function clear_history(int $user_id, bool $keep_pinned = true): int
    {
        $where = array('user_id' => $user_id);

        if ($keep_pinned) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE user_id = %d AND is_pinned = 0",
                    $user_id
                )
            );
        } else {
            $result = $this->wpdb->delete($this->table_name, $where, array('%d'));
        }

        // Clear caches
        $this->clear_user_cache($user_id);

        return (int) $result;
    }

    /**
     * Cleanup old data.
     *
     * @param int $retention_days Days to retain data.
     * @return int Number of deleted rows.
     */
    public function cleanup_old_data(int $retention_days): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        // Delete old data but keep pinned items
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE last_accessed < %s AND is_pinned = 0",
                $cutoff
            )
        );

        return (int) $result;
    }

    /**
     * Search links for a user.
     *
     * @param int    $user_id User ID.
     * @param string $search  Search term.
     * @param int    $limit   Maximum results.
     * @return array Matching links.
     */
    public function search_links(int $user_id, string $search, int $limit = 20): array
    {
        $search_term = '%' . $this->wpdb->esc_like($search) . '%';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, url, page_title, last_accessed, access_count, is_pinned
				FROM {$this->table_name}
				WHERE user_id = %d AND (page_title LIKE %s OR url LIKE %s)
				ORDER BY access_count DESC, last_accessed DESC
				LIMIT %d",
                $user_id,
                $search_term,
                $search_term,
                $limit
            )
        );

        return $results ?: array();
    }

    /**
     * Clear cached data for a user.
     *
     * @param int $user_id User ID.
     * @return void
     */
    public function clear_user_cache(int $user_id): void
    {
        global $wpdb;

        // Delete all quickjump transients for this user
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_quickjump_%_' . $user_id . '_%'
            )
        );
    }

    /**
     * Get all visits for export.
     *
     * @param int $user_id User ID.
     * @return array All visits.
     */
    public function get_all_visits(int $user_id): array
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY last_accessed DESC",
                $user_id
            )
        );

        return $results ?: array();
    }
}
