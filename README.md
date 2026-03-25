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

The following providers are integrated directly into core:

| Provider | Models | Type |
|----------|--------|------|
| **DeepSeek** | Chat, Coder, Reasoner | Cloud |
| **OpenRouter** | 100+ models (Anthropic, OpenAI, Google, etc.) | Cloud |
| **Ollama** | Llama, Mistral, Phi, etc. | Local |
| **xAI (Grok)** | Grok-2, Grok Vision | Cloud |
| **Mistral** | Mistral Large, Codestral | Cloud |

Location: `wp-includes/php-ai-client/src/Providers/AiConnectorPro/`

## Quick Links

- [GitHub Repository](https://github.com/cbuntingde/wordpress-develop)
- [WordPress.org](https://wordpress.org/)
- [WordPress Trac](https://core.trac.wordpress.org/)
- [Contributor Handbook](https://make.wordpress.org/core/handbook/)

## License

GPL v2 or later — Same as WordPress core