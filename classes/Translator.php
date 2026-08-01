<?php

namespace Poly9000;

/**
 * Translates content with a language model.
 *
 * The provider call comes from lauzis/wp-plugin-packages; what lives here is
 * the part that is Poly 9000's — the prompt, and the rule that markup must
 * survive the round trip.
 */
class Translator
{
    /**
     * Translates a block of content.
     *
     * @param string $content     Source content, markup included.
     * @param string $targetCode  Target language code, e.g. "lv".
     * @param string $targetLabel Human-readable target language, used in the prompt.
     * @return string|\WP_Error
     */
    public static function translate(string $content, string $targetCode, string $targetLabel = '')
    {
        $content = trim($content);

        if ('' === $content) {
            return new \WP_Error('poly9000_no_content', __('There is nothing to translate.', 'poly-9000'));
        }

        if ('' === trim($targetCode)) {
            return new \WP_Error('poly9000_no_target', __('No target language was given.', 'poly-9000'));
        }

        if (!class_exists('WpPackages_Registry')) {
            return new \WP_Error('poly9000_no_llm', __('The shared LLM component is unavailable.', 'poly-9000'));
        }

        $target = '' !== $targetLabel ? $targetLabel : $targetCode;

        Logs::add('translate', 'Translation requested.', [
            'target' => $targetCode,
            'length' => strlen($content),
        ]);

        $result = \WpPackages_Registry::llm(POLY9000_SLUG)->complete(
            self::prompt($target),
            $content,
            ['target_language' => $targetCode]
        );

        if (is_wp_error($result)) {
            Logs::error('translate', 'Translation failed.', [
                'target' => $targetCode,
                'error'  => $result->get_error_message(),
            ]);

            return $result;
        }

        Logs::add('translate', 'Translation completed.', ['target' => $targetCode]);

        return trim($result);
    }

    /**
     * Translates several target languages in one pass.
     *
     * Stops at the first failure rather than continuing, so a bad key or an
     * exhausted quota is reported once instead of once per language.
     *
     * @param array<string, string> $targets Code => label.
     * @return array<string, string>|\WP_Error
     */
    public static function translateAll(string $content, array $targets)
    {
        $out = [];

        foreach ($targets as $code => $label) {
            $result = self::translate($content, (string) $code, (string) $label);

            if (is_wp_error($result)) {
                return $result;
            }

            $out[$code] = $result;
        }

        return $out;
    }

    /** Builds the system prompt from the configured behaviour settings. */
    private static function prompt(string $target): string
    {
        $parts = [
            sprintf(
                /* translators: %s: target language */
                __('Translate the text the user sends into %s.', 'poly-9000'),
                $target
            ),
            __('Reply with the translation only — no commentary, no preamble, no quotes around it.', 'poly-9000'),
        ];

        if (Settings::get('preserve_markup', true)) {
            $parts[] = __(
                'The text contains HTML and WordPress shortcodes. Keep every tag, attribute, shortcode and URL exactly as it is, and translate only the human-readable text between them.',
                'poly-9000'
            );
        }

        $extra = trim((string) Settings::get('extra_instructions', ''));

        if ('' !== $extra) {
            $parts[] = $extra;
        }

        return implode(' ', $parts);
    }

    /**
     * The configured target languages as code => label.
     *
     * @return array<string, string>
     */
    public static function targets(): array
    {
        $targets = Settings::get('target_languages', []);
        $out     = [];

        foreach (is_array($targets) ? $targets : [] as $row) {
            $code = trim((string) ($row['code'] ?? ''));

            if ('' === $code) {
                continue;
            }

            $out[$code] = trim((string) ($row['label'] ?? '')) ?: $code;
        }

        return $out;
    }
}
