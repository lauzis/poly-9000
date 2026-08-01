<?php

namespace Poly9000;

/**
 * Admin screen and setup notice.
 */
class Admin
{
    /** Hooked on init, once the settings schema exists. */
    public static function init(): void
    {
        if (!is_admin() || !class_exists('WpPackages_Registry')) {
            return;
        }

        $notices = \WpPackages_Registry::notices(POLY9000_SLUG, [
            'store'      => 'user',
            'version'    => POLY9000_VERSION,
            'capability' => 'manage_options',
        ])->boot();

        \WpPackages_Registry::toasts(POLY9000_SLUG);

        add_action('admin_notices', static function () use ($notices): void {
            foreach (self::setupIssues() as $index => $issue) {
                $notices->add(
                    new \Lauzis\WpPackages\Notices\Notice(
                        'setup-' . $index,
                        $issue,
                        'warning',
                        \Lauzis\WpPackages\Notices\Notice::VERSION
                    )
                );
            }
        }, 5);
    }

    /**
     * Configuration that has to be in place before anything can be translated.
     *
     * @return string[]
     */
    private static function setupIssues(): array
    {
        $issues = [];

        $provider = (string) Settings::get('llm_provider', '');
        $key      = (string) Settings::get('llm_access_key', '');
        $command  = (string) Settings::get('llm_command', '');

        if ('commandline' === $provider || '' === $provider) {
            if ('' === trim($command)) {
                $issues[] = __('No commandline command is configured, so translation requests cannot run. Set one on the AI Provider tab, or pick a hosted provider.', 'poly-9000');
            }
        } elseif ('' === trim($key)) {
            $issues[] = sprintf(
                /* translators: %s: provider name */
                __('No access key is configured for the %s provider.', 'poly-9000'),
                $provider
            );
        }

        if (!Translator::targets()) {
            $issues[] = __('No target languages are configured, so there is nothing to translate into.', 'poly-9000');
        }

        return $issues;
    }

    /** Renders the plugin's main screen. */
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $targets = Translator::targets();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Poly 9000', 'poly-9000') . '</h1>';

        if ($targets) {
            echo '<p>' . esc_html(
                sprintf(
                    /* translators: %s: comma-separated language list */
                    __('Translating into: %s', 'poly-9000'),
                    implode(', ', $targets)
                )
            ) . '</p>';
        } else {
            echo '<p>' . esc_html__('No target languages configured yet.', 'poly-9000') . '</p>';
        }

        echo '</div>';
    }
}
