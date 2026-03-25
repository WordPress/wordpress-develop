# WordPress AI Security Edition

> ⚠️ **WARNING: This is a work-in-progress fork** — Not intended for production use

This fork integrates AI-powered security features directly into WordPress core. Developed by Chris Bunting with AI agent assistance.

## Overview

This project aims to make WordPress more secure than ever through AI-powered features:

- **Exploit Detection** — AI analyzes code patterns to catch vulnerabilities before exploitation
- **Real-time Monitoring** — DDoS protection, brute force detection, anomaly detection
- **Plugin/Theme Scanning** — Automated vulnerability detection for extensions
- **Security Dashboard** — Configure AI settings, alerts, and monitoring from WP Admin

## Documentation

| Document | Description |
|----------|-------------|
| [README-ORIG.md](./README-ORIG.md) | Original WordPress development setup |
| [docs/implementation-plan.md](./docs/implementation-plan.md) | Security features roadmap |
| [docs/knowledge-base.md](./docs/knowledge-base.md) | Security research and architecture |

## AI Providers (Built-in)

This fork includes support for 8 AI providers. Five are integrated directly into core, three require separate plugins:

| Provider | Status | Description |
|----------|--------|-------------|
| **DeepSeek** | ✅ Built-in | Open-source models for code and chat |
| **OpenRouter** | ✅ Built-in | 100+ models via single API |
| **Ollama** | ✅ Built-in | Local models (Llama, Mistral, etc.) |
| **xAI (Grok)** | ✅ Built-in | Conversational AI with real-time knowledge |
| **Mistral** | ✅ Built-in | Strong coding models |
| **OpenAI** | 🔄 Plugin required | GPT-4o, o1, DALL-E |
| **Anthropic** | 🔄 Plugin required | Claude 3.5 Sonnet, Opus, Haiku |
| **Google** | 🔄 Plugin required | Gemini 2.0, 1.5 Pro/Flash |

**Built-in provider location:** `wp-includes/php-ai-client/src/Providers/AiConnectorPro/`

**Note:** OpenAI, Anthropic, and Google providers are available via separate WordPress plugins. These providers will be added to core in a future update.

## Quick Links

- [GitHub Repository](https://github.com/cbuntingde/wordpress-develop)
- [AI Connector Pro Plugin](https://github.com/cbuntingde/ai-connector-pro)
- [WordPress.org](https://wordpress.org/)
- [WordPress Trac](https://core.trac.wordpress.org/)

## License

GPL v2 or later — Same as WordPress core