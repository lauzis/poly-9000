# poly-9000

LLM translation plugin for WordPress.

Built on [lauzis/wp-plugin-packages](https://github.com/lauzis/wp-plugin-packages),
so its logging, admin notices, toasts, settings page and AI provider handling are
the same components the other plugins use.

## What is here

This is a working scaffold rather than a finished plugin: settings, provider
configuration, logging, the setup notice and the translation call are all in
place. What it does not have yet is the part that decides *which* content gets
translated and where the result is stored.

| | |
| --- | --- |
| `classes/Translator.php` | Builds the prompt and calls the shared LLM client. Markup preservation is a setting. |
| `classes/Settings.php` | Registers `config/settings.json` plus the package's `llm` and `logs` schemas. |
| `classes/Admin.php` | Admin screen, and a setup notice listing whatever still needs configuring. |
| `classes/Logs.php` | Facade over the shared logger. |

## Install

```
composer install
```

Then configure a provider under **Poly 9000 → Settings → AI Provider**. Either a
hosted provider with an access key, or a local command — the commandline option
keeps credentials out of WordPress entirely, since the script owns its own.

## Changelog

### 0.3.1
- Added a **Send a test message** button beside the Slack webhook field. It posts to whatever is in the field, saved or not, waits for Slack's answer and reports it — log traffic is fire-and-forget, so a webhook Slack rejects otherwise fails silently.

### 0.3.0
- Log entries can be sent to **Slack**. An incoming webhook URL and an errors-only/every-entry choice on the Logging settings; errors are posted even with file logging off. Only `https://` URLs are used — the webhook URL is itself a credential. Sending is fire-and-forget, so a translation run never waits on Slack, and a webhook Slack rejects fails quietly.
- Bundled shared library updated to wp-plugin-packages 1.15.0.

### 0.2.0
- Added a **Logs** page, and the log is shown on the Logging settings tab.
- The plugin version is shown in the admin footer.

## Next

- Choose what to translate: a per-post action, a bulk action, or a queue.
- Decide where translations live — separate posts, post meta, or a translation
  table — and wire `Translator::translateAll()` to it.
- Chunk long content. The shared client sends what it is given in one request;
  a long post may need splitting the way splecheh splits sentences.
