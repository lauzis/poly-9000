<?php

namespace Poly9000;

/**
 * Poly 9000's settings page.
 *
 * Fields come from config/settings.json plus two schemas shipped by
 * lauzis/wp-plugin-packages, so the logging and AI provider controls match
 * every other plugin here.
 */
class Settings
{
    private const PREFIX = 'poly9000_';

    /** @return \Lauzis\WpPackages\Settings\Settings|null */
    public static function page()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        return \WpPackages_Registry::settings(POLY9000_SLUG, [
            'title'       => __('Settings', 'poly-9000'),
            'mode'        => 'tabs',
            'page_parent' => POLY9000_SLUG,
            'page_file'   => POLY9000_SLUG . '-settings',
        ]);
    }

    /** Hooked on carbon_fields_register_fields. */
    public static function register(): void
    {
        $page = self::page();

        if (!$page) {
            return;
        }

        $page->callback('poly9000_site_language', static fn(): string => explode('_', get_locale())[0]);

        $page->callback('poly9000_post_type_options', static function (): array {
            $options = [];

            foreach (get_post_types(['public' => true], 'objects') as $type) {
                $options[$type->name] = $type->label;
            }

            return $options;
        });

        $page->register(POLY9000_DIR . 'config/settings.json', [
            'prefix' => self::PREFIX,
            'domain' => 'poly-9000',
        ]);

        $page->register(\WpPackages_Registry::schema('llm'), [
            'prefix' => self::PREFIX,
            'domain' => 'wp-plugin-packages',
        ]);

        // Draws the "Send a test message" button under the Slack webhook field.
        // Without the callback the schema's html field renders nothing, so an
        // older bundled package simply has no button.
        $tester = Logs::slackTester();

        if ($tester) {
            $page->callback('logs_slack_test', [$tester, 'render']);
        }

        $page->register(\WpPackages_Registry::schema('logs'), [
            'prefix' => self::PREFIX,
            'domain' => 'wp-plugin-packages',
        ]);

        $page->render();
    }

    /**
     * @param string $id      Bare id as written in the schema.
     * @param mixed  $default
     * @return mixed
     */
    public static function get(string $id, $default = null)
    {
        $page = self::page();

        return $page ? $page->get($id, $default) : $default;
    }
}
