<?php
/**
 * Plugin Name: Poly 9000
 * Plugin URI:  https://github.com/lauzis/poly-9000
 * Description: Translates posts and pages using a language model.
 * Version:     0.2.0
 * Author:      Aivars Lauzis
 * Text Domain: poly-9000
 * Domain Path: /languages
 * License:     MIT
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('POLY9000_VERSION', '0.2.0');
define('POLY9000_DIR', plugin_dir_path(__FILE__));
define('POLY9000_URL', plugin_dir_url(__FILE__));
define('POLY9000_SLUG', 'poly-9000');

$poly9000_autoload = POLY9000_DIR . 'vendor/autoload.php';

if (!file_exists($poly9000_autoload)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>Poly 9000:</strong> run <code>composer install</code> in the plugin directory.</p></div>';
    });

    return;
}

require_once $poly9000_autoload;
// Required explicitly: Composer's files autoload runs only one copy of this
// package per request, so the version gate would never see the others.
require_once POLY9000_DIR . 'vendor/lauzis/wp-plugin-packages/bootstrap.php';

if (!defined('POLY9000_LOG_PATH')) {
    // Under uploads/, never inside the plugin directory: WordPress deletes and
    // re-extracts that folder on every update.
    $poly9000_uploads = wp_upload_dir();
    define('POLY9000_LOG_PATH', str_replace('\\', '/', $poly9000_uploads['basedir']) . '/poly-9000-logs/');
    unset($poly9000_uploads);
}

add_action('after_setup_theme', static function (): void {
    if (class_exists('\Carbon_Fields\Carbon_Fields')) {
        \Carbon_Fields\Carbon_Fields::boot();
    }
});

// Carbon Fields fires this on init at priority 0, so it must be attached before
// init runs rather than from inside an init callback.
add_action('carbon_fields_register_fields', ['\Poly9000\Settings', 'register']);
add_action('admin_post_poly9000_clear_logs', ['\Poly9000\Logs', 'handleClear']);

add_action('admin_menu', static function (): void {
    add_menu_page(
        __('Poly 9000', 'poly-9000'),
        __('Poly 9000', 'poly-9000'),
        'manage_options',
        POLY9000_SLUG,
        ['\Poly9000\Admin', 'render'],
        'dashicons-translation',
        81
    );

    // Only when there is something to read, and it stays while old files
    // remain: switching logging off is often exactly when somebody wants to
    // look at what it caught.
    if (\Poly9000\Logs::hasSomethingToShow()) {
        add_submenu_page(
            POLY9000_SLUG,
            __('Logs', 'poly-9000'),
            __('Logs', 'poly-9000'),
            'manage_options',
            POLY9000_SLUG . '-logs',
            ['\Poly9000\Logs', 'renderPage']
        );
    }
}, 5);

add_action('init', ['\Poly9000\Admin', 'init']);

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('poly-9000', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// The plugin's version in the admin footer, beside WordPress's own — the first
// thing worth knowing about a page misbehaving is which version drew it.
add_action( 'admin_init', static function () {
    if ( ! class_exists( '\\Lauzis\\WpPackages\\Admin\\Footer' ) ) {
        return;
    }

    \Lauzis\WpPackages\Admin\Footer::show(
        'poly-9000',
        array(
            'name'    => 'Poly 9000',
            'version' => defined( 'POLY9000_VERSION' ) ? POLY9000_VERSION : '',
        )
    );
} );
