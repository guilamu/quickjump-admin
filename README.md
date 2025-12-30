# QuickJump Admin

Navigate faster in WordPress admin with intelligent shortcuts to your recently and frequently accessed pages.

![Plugin Screenshot](https://github.com/guilamu/quickjump-admin/blob/main/screenshot.jpg)

## Smart Navigation

- Track all admin page visits automatically
- View your last 10 recent links from the past 24 hours (configurable)
- Access your top 10 most frequently used pages over the last 30 days (configurable)
- Pin important pages for instant access

## Quick Access Dropdown

- Hover-activated dropdown in the admin bar (no click required)
- Search within your navigation history
- Visual icons for different content types (posts, pages, media, settings)
- Relative timestamps showing "2 hours ago" style timing
- Access count badges for most-used pages

## Full Control

- Customize number of links displayed (1-50)
- Set time windows for recent and most-used calculations
- Define excluded URL patterns with regex support
- Automatic cleanup of old data based on retention period

## Key Features

- **Per-User Data:** Each user has their own private navigation history
- **Performance Optimized:** WordPress transients cache with database indexes
- **Multilingual:** Works with content in any language
- **Translation-Ready:** All strings are internationalized with POT file included
- **Secure:** Nonce verification, capability checks, prepared SQL statements
- **GitHub Updates:** Automatic updates from GitHub releases

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher

## Installation

1. Upload the `quickjump-admin` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Start navigating — tracking begins automatically
4. Hover over **Shortcuts** in the admin bar to access your pages
5. Go to **Settings → QuickJump Admin** to customize options

## FAQ

### How does the tracking work?

The plugin automatically records each admin page you visit, storing the URL, page title, timestamp, and incrementing the visit count. Data is stored per-user and never shared.

### Can I exclude certain pages from tracking?

Yes, go to **Settings → QuickJump Admin** and add URL patterns to the "Excluded URL patterns" field. Both regex and simple string matching are supported.

### How do I pin a page?

Hover over the Shortcuts dropdown and click the star icon next to any link. Pinned items appear at the top and are preserved during cleanup.

### Can I change the button label?

Yes, go to **Settings → QuickJump Admin** and modify the "Admin bar button label" field to any text you prefer.

### How is old data cleaned up?

A daily cron job automatically removes data older than the retention period (default: 90 days). Pinned items are always preserved.

## Project Structure

```
.
├── quickjump-admin.php           # Main plugin file with headers
├── uninstall.php                 # Database and options cleanup
├── README.md
├── admin
│   ├── css
│   │   └── admin.css             # Dropdown and menu styles
│   └── js
│       └── admin.js              # Search, pin, and AJAX handlers
├── includes
│   ├── class-quickjump-database.php   # Database CRUD operations
│   ├── class-quickjump-menu.php       # Admin bar dropdown
│   ├── class-quickjump-settings.php   # Settings page
│   ├── class-quickjump-tracker.php    # Page visit tracking
│   └── class-github-updater.php       # GitHub auto-updates
└── languages
    └── quickjump-admin.pot       # Translation template
```

## Changelog

### 1.0.0
- Initial release
- **New:** Admin bar dropdown with hover activation
- **New:** Recent and most-used page tracking
- **New:** Pin/unpin functionality for favorite pages
- **New:** Search within navigation history
- **New:** Configurable settings page
- **New:** GitHub auto-update support
- **New:** Automatic data cleanup with retention settings
- **New:** Tabbed settings interface
- **New:** Quick action to hide links from menu


## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Made with love for the WordPress community
</p>


