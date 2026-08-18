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

    /**
     * The Slack test button, or null when the package is absent or older than
     * the version that added it.
     *
     * @return \Lauzis\WpPackages\Logs\SlackTester|null
     */
    public static function slackTester()
    {
        static $tester = null;

        if (null !== $tester) {
            return $tester;
        }

        $logger = self::logger();

        if (!$logger || !class_exists('\\Lauzis\\WpPackages\\Logs\\SlackTester')) {
            return null;
        }

        $tester = new \Lauzis\WpPackages\Logs\SlackTester($logger);

        return $tester;
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
     * Whether the Logs screen is worth a menu entry.
     *
     * Not simply "is logging on": switching it off should not take away the log
     * it already wrote, which is usually the moment somebody wants to read it.
     */
    public static function hasSomethingToShow(): bool
    {
        $logger = self::logger();

        if (!$logger) {
            return false;
        }

        return $logger->isEnabled() || (bool) $logger->files();
    }

    /**
     * The Logs screen.
     *
     * A page of its own rather than a panel on the settings page: settings are
     * what the plugin will do, and a log is what it did. The listing itself is
     * the shared package's, since every plugin here writes the same log.
     */
    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $logger = self::logger();

        echo '<div class="wrap"><h1>' . esc_html__('Poly 9000 — Logs', 'poly-9000') . '</h1>';

        if (!$logger || !class_exists('\\Lauzis\\WpPackages\\Logs\\Viewer')) {
            // An older copy of the shared package won the version race — see
            // WpPackages_Registry. Said plainly rather than fataling.
            echo '<p>' . esc_html__('The log reader needs a newer copy of the shared package than the one running.', 'poly-9000') . '</p></div>';

            return;
        }

        $viewer = new \Lauzis\WpPackages\Logs\Viewer($logger, ['clear' => 'poly9000_clear_logs']);

        echo $viewer->render(); // Escaped by the viewer, field by field.
        echo '</div>';
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
