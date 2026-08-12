<?php

namespace Poly9000;

/**
 * Poly 9000's logging entry point.
 *
 * A thin facade over the shared logger so call sites stay short and the plugin
 * degrades to silence rather than fataling when the package is absent.
 */
class Logs
{
    /** @return \Lauzis\WpPackages\Logs\Logger|null */
    private static function logger()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        // No 'enabled' passed: the component reads logs_enabled from the schema
        // registered in Settings.
        return \WpPackages_Registry::logger(POLY9000_SLUG, ['dir' => POLY9000_LOG_PATH]);
    }

    public static function add(string $action, string $message = '', array $context = []): bool
    {
        $logger = self::logger();

        return $logger ? $logger->add($action, $message, $context) : false;
    }

    /** Always reaches PHP's error log, whatever the logging setting says. */
    public static function error(string $action, string $message = '', array $context = []): void
    {
        $logger = self::logger();

        if ($logger) {
            $logger->error($action, $message, $context);
        }
    }

    /**
     * The log, as a panel for the settings page.
     *
     * The listing is the shared package's, because every plugin here writes the
     * same log and would otherwise grow its own reader for it. What stays here
     * is whether to show it and what happens when somebody clears it.
     */
    public static function panel(): string
    {
        $logger = self::logger();

        if (!$logger || !class_exists('\\Lauzis\\WpPackages\\Logs\\Viewer')) {
            // An older copy of the shared package won the version race — see
            // WpPackages_Registry. The rest of the page still works, so this
            // says what is missing rather than fataling.
            return '<p class="description">'
                . esc_html__('The log reader needs a newer copy of the shared package than the one running.', 'poly-9000')
                . '</p>';
        }

        $viewer = new \Lauzis\WpPackages\Logs\Viewer($logger, ['clear' => 'poly9000_clear_logs']);

        return $viewer->render();
    }

    /** Empties the log, from the button on that panel. */
    public static function handleClear(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do that.', 'poly-9000'));
        }

        check_admin_referer('poly9000_clear_logs');

        self::add('logs', 'Log cleared from the settings page.', ['user' => get_current_user_id()]);

        $logger = self::logger();

        if ($logger) {
            $logger->clear();
        }

        // Back where the button was, whichever screen carried the panel.
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }
}
