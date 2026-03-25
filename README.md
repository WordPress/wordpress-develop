# WordPress AI Security Edition

⚠️ **WARNING: This is a work-in-progress fork**  
This version of WordPress is being developed by **Chris Bunting** with AI agent assistance. It is **NOT intended for production use**. Features are experimental and the core is actively being modified.

---

## About This Fork

This fork aims to integrate AI-powered security features directly into WordPress core to provide:

- **AI-powered exploit detection** — Code analysis and pattern recognition to catch vulnerabilities before they're exploited
- **Real-time threat monitoring** — DDoS detection, brute force protection, anomalous behavior detection
- **Plugin/theme security scanning** — Automated vulnerability detection for installed extensions
- **Configurable security dashboard** — Manage AI settings, alerts, and monitoring rules directly from WP Admin

---

## For Original Documentation

See [README-ORIG.md](./README-ORIG.md) for the standard WordPress development setup instructions.

---

## Changes & Updates

*(This section will be updated as features are added)*

### Current Status: Implementation

- [x] Research: WordPress core security architecture and hook system
- [x] Research: Existing AI client in core (wp-includes/ai-client/)
- [x] Research: Abilities API system (wp-includes/abilities-api/)
- [x] Architecture: Design AI security plugin integration
- [x] Create detailed implementation plan (docs/implementation-plan.md)
- [x] Research: AI provider plugins - found they need separate plugins
- [x] Design: AI Connector Pro plugin (multi-provider support)
- [x] Core Integration: Add AI providers directly to WordPress core

### Providers Now Built Into Core

The following AI providers are now integrated directly into WordPress core:

- **DeepSeek** - Open-source models for code and chat
- **OpenRouter** - 100+ models via single API
- **Ollama** - Local models (Llama, Mistral, etc.)
- **xAI (Grok)** - Conversational AI with real-time knowledge
- **Mistral** - Strong coding models

Location: `wp-includes/php-ai-client/src/Providers/AiConnectorPro/`

### Related Projects

- **AI Connector Pro Plugin** (separate repo): https://github.com/cbuntingde/ai-connector-pro
- **Implementation Plan:** docs/implementation-plan.md
- **Security Knowledge Base:** docs/knowledge-base.md

---

## Development Notes

- This project uses AI agents to assist with research, implementation, and maintenance
- Agents have access to WordPress documentation and can search/update their knowledge base
- Contributions and ideas welcome — open an issue to discuss

---

## Quick Links

- Original WordPress: https://wordpress.org/
- WordPress Trac: https://core.trac.wordpress.org/
- Contributor Handbook: https://make.wordpress.org/core/handbook/