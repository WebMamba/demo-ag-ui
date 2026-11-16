Passage Agent
=============

An AI navigator for an ocean passage — Panama to Tahiti — built with Symfony AI,
Symfony UX and the [AG-UI protocol](https://docs.ag-ui.com). It is the demo of the
SymfonyCon 2026 talk *Symfony UX + Symfony AI: Taking Agent Interactions to the Next Level*,
and runs on [AG-UI for PHP](https://github.com/WebMamba/ag-ui-php).

It shows, feature by feature, an agent that does more than chat:

| Feature | Try |
|---|---|
| The agent working: reasoning, tool calls and text, streamed | *It's sunset. Look at my position and the conditions tonight on a heading of 260 — do I need to roll the genoa?* |
| Generative UI: Twig components as answers | *Show me the route and the sea state at the Marquesas* |
| The agent drives the page: theme and language | *I'm on night watch — and show the site in French* |
| Shared state: a passage plan both sides edit | *Plan my crossing from Panama to Tahiti, with a stop in the Galápagos*, then remove a waypoint |
| Frontend tools: the browser's GPS | *What's the sea state where we are?* |
| Human in the loop: confirm before acting | *Reserve me a mooring at Marina Papeete, arriving December 2nd 2026, for 3 nights — we're a 12 m boat* |

`/chat` is the "before": the same agent behind a plain chat, with no AG-UI.

> [!WARNING]
> A demo. Never navigate with a chatbot: check the charts.

Requirements
------------

 * PHP 8.4 or higher, and [Composer](https://getcomposer.org)
 * The [Symfony CLI](https://symfony.com/download), to run the local web server
 * An [Anthropic API key](https://console.anthropic.com/settings/keys)

No database, Node.js or Docker is needed.

Installation
------------

Until AG-UI for PHP is released on Packagist, the demo installs it from a clone next to
this one (Composer path repositories in `composer.json`): both repositories must be in the
same parent directory.

```bash
git clone https://github.com/WebMamba/ag-ui-php.git
git clone https://github.com/WebMamba/demo-ag-ui.git turbo-agent
cd turbo-agent

composer install
```

Then give it your Anthropic API key, in `.env.local` (never committed):

```bash
echo 'ANTHROPIC_API_KEY=sk-ant-...' > .env.local
```

Download the JavaScript packages and build the CSS:

```bash
php bin/console importmap:install
php bin/console tailwind:build
```

Running it
----------

```bash
symfony server:start -d
```

Open the URL it prints, usually <https://127.0.0.1:8000>. Run `symfony server:ca:install`
once if your browser does not trust the local HTTPS certificate.

While you change templates or CSS, keep Tailwind rebuilding in another terminal:

```bash
php bin/console tailwind:build --watch
```

The agent's platform, model and instructions are in `config/packages/ai.yaml`; its tools
are in `src/Ai/Tool`, and the components it shows in `src/Twig/Components/Agent`.

Tests
-----

```bash
php bin/phpunit
```

The tests use no network and no API key: the platform is replaced by an in-memory one.

Learn more
----------

 * The words used in the code: [CONTEXT.md](CONTEXT.md); the design decisions: [docs/adr](docs/adr)
 * [AG-UI for PHP](https://github.com/WebMamba/ag-ui-php), the SDK this demo is built on
 * [The AG-UI protocol](https://docs.ag-ui.com) · [Symfony AI](https://symfony.com/doc/current/ai/index.html) · [Symfony UX](https://ux.symfony.com)
