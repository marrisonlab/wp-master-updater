# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.2.3] - 2026-02-16

### Changed
- Restore eseguito via richiesta al polling dell’Agent (push‑model): elimina cURL 28.
- Master marca il client come pending al restore e aggiorna contatori via AJAX.
- Risposte AJAX ora includono anche i contatori LED per refresh immediato.

## [1.1.2.2] - 2026-02-16

### Added
- LED amber per “upstream più nuovo del repository privato”.
- Contatori LED nella barra azioni, allineati a destra, visibili solo se > 0.
- Sezione “Upstream updates (non ancora nel repository privato)” nei dettagli client.

### Changed
- Aggiornata legenda nella guida con nuovi stati LED (amber/blu/grigio).

## [1.1.2.1] - 2026-02-16

### Changed
- Agent excluded plugins are preserved when clearing Master cache.
- Master clients table now refreshes correctly via the Refresh button and auto-refresh.
- Improved pending/stale visual states after sync and cache clear.

## [1.1.2] - 2026-02-16

### Added
- API token authentication between Master and Agent with HMAC-signed requests.
- API Security settings card with token generator, show/hide, and copy actions.
- Refresh button above clients table to reload status via AJAX.

### Changed
- "Last Sync" now reflects the last successful communication (poll or push) with each Agent.
- Clear Master Cache marks clients as stale with a grey indicator until the next sync.
- Sync requests mark clients as pending until the Agent sends updated data.

## [1.0.9] - 2026-02-13
### Added
- Added functionality to ignore specific plugins from update checks.
- Excluded ignored plugins from red LED status calculation.
- Added UI toggle for ignoring plugins in client details.

## [1.0.8] - 2026-02-13

### Changed
- Updated Guide page layout: merged Configuration and Private Repositories sections.
- Replaced download buttons with simple links in Guide page.

## [1.0.7] - 2026-02-13

### Changed
- Minor bug fixes and improvements.
- Skipped version 1.0.6.

## [1.0.5] - 2026-02-12

### Changed
- Updated sidebar menu icon with custom SVG.
- Improved icon styling to match WordPress admin interface.

## [1.0.4] - 2026-02-12

### Fixed
- Fixed fatal PHP error caused by undefined constant during update system initialization.
- Optimized GitHub Updater initialization in the main plugin file.

## [1.0.3] - 2026-02-12

### Fixed
- Rewrote GitHub update mechanism using remote JSON file.
- Added cache management for update requests.
- Implemented forced update check with button in plugin list.
- Added automatic plugin folder correction during update.

## [1.0.2] - 2026-02-12

### Fixed
- Fixed update detection issue (GitHub Updater) to correctly include plugin slug.
- Added support for displaying "Enable auto-updates" link in plugin list.
- Improved update response object with icons and banners.
- Fixed issue with update details popup.

## [1.0.1] - 2026-02-12

### Changed
- Renamed main plugin file from `marrison-master.php` to `wp-master-updater.php` for consistency.
- Updated API namespaces and internal slugs from `marrison-master` to `wp-master-updater`.
- Fixed API endpoints for communication with WP Agent Updater (from `marrison-agent` to `wp-agent-updater`).
- Updated documentation and UI references.

## [1.0.0] - 2024-02-12

### Added
- Initial version of the Marrison Master plugin
- Main dashboard for multi-site management
- LED indicator system for client status (green, yellow, red, black)
- Remote synchronization operations with clients
- Centralized update system for plugins, themes, and translations
- Complete backup management with remote restore
- Support for private plugin and theme repositories
- Bulk operations (bulk sync, bulk update)
- Detail interface for each client
- Notification and status message system
- Forced repository cache refresh button
- Protection against duplicate operations with button disabling
- Auto-sync after restore operations
- Error handling with detailed messages
- Support for managing deactivated plugins
- Priority logic for status indicators (black > red > yellow > green)

### Changed
- Improved PHP version management in the update core
- Optimized download URL management with sanitization
- Improved network error and timeout handling
- Optimized performance for bulk operations

### Fixed
- Fixed update issue with required PHP versions being too high
- Fixed malformed download URL handling
- Fixed display issue after cache clearing
- Fixed client count for bulk operations
- Fixed various compatibility bugs with different WordPress versions

### Security
- Implemented strict input data validation
- Added nonces for all AJAX operations
- Implemented permission control based on WordPress roles
- Sanitization of all output data

## [Pre-1.0.0] - Initial Development Phases

Versions prior to 1.0.0 were internal development and testing phases.

---

## How to Update

To update the plugin:

1. **Backup**: Always create a backup before updating
2. **Download**: Download the new version from the [GitHub repository](https://github.com/marrisonlab/wp-master-updater)
3. **Installation**: Replace the plugin files with the new version
4. **Test**: Verify that everything works correctly
5. **Sync**: Perform a full synchronization with all clients

## Reporting Issues

If you encounter problems with this plugin:

1. Verify you have the latest version
2. Check system requirements
