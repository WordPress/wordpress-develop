# AI Connector Pro - Plugin Specification

**Version:** 1.0  
**Date:** 2026-03-25  
**Plugin Name:** AI Connector Pro  
**Purpose:** Unified plugin to add multiple AI providers to WordPress

---

## Overview

This plugin provides a unified interface for connecting WordPress to multiple AI providers (Anthropic, Google, OpenAI, DeepSeek, OpenRouter, Ollama, xAI/Grok). It registers each provider with WordPress's AI Client and Connectors systems.

---

## Supported Providers

| Provider | API | Models | Status |
|----------|-----|--------|--------|
| **OpenAI** | `api.openai.com` | GPT-4o, GPT-4o Mini, o1, o1-mini | ✅ In core (template) |
| **Anthropic** | `api.anthropic.com` | Claude 3.5 Sonnet, 3 Opus, 3 Haiku | ✅ In core (template) |
| **Google** | `generativelanguage.googleapis.com` | Gemini 2.0, 1.5 Pro/Flash | ✅ In core (template) |
| **DeepSeek** | `api.deepseek.com` | DeepSeek Chat, Coder | 🆕 New |
| **OpenRouter** | `openrouter.ai` | Aggregates 100+ models | 🆕 New |
| **Ollama** | localhost | Local models (Llama, Mistral, etc.) | 🆕 New |
| **xAI** | `api.x.ai` | Grok-2, Grok-2 Vision | 🆕 New |
| **Mistral** | `api.mistral.ai` | Codestral, Mistral Large | 🆕 New |

---

## Architecture

```
ai-connector-pro/
├── ai-connector-pro.php          # Main plugin file
├── includes/
│   ├── class-ai-connector-pro.php    # Main plugin class
│   ├── providers/
│   │   ├── abstract-provider.php      # Base provider class
│   │   ├── class-deepseek.php         # DeepSeek provider
│   │   ├── class-openrouter.php       # OpenRouter provider
│   │   ├── class-ollama.php           # Ollama (local)
│   │   ├── class-xai.php              # xAI/Grok provider
│   │   └── class-mistral.php          # Mistral provider
│   ├── class-settings.php             # Admin settings
│   └── class-connector.php            # WP Connector integration
└── readme.txt
```

---

## Provider Implementation Pattern

Each provider implements `ProviderInterface` and provides:

### 1. Metadata
```php
public static function metadata(): ProviderMetadata {
    return new ProviderMetadata(
        id: 'deepseek',
        name: 'DeepSeek',
        type: ProviderTypeEnum::CLOUD,
        credentialsUrl: 'https://platform.deepseek.com/api-keys',
        authenticationMethod: new RequestAuthenticationMethod(
            RequestAuthenticationMethod::API_KEY
        ),
        description: 'Open-source AI models for code and chat'
    );
}
```

### 2. Model Factory
```php
public static function model(string $modelId, ?ModelConfig $config = null): ModelInterface {
    // Return appropriate model class based on modelId
}
```

### 3. Availability Checker
```php
public static function availability(): ProviderAvailabilityInterface {
    // Check if API is reachable
}
```

### 4. Model Metadata Directory
```php
public static function modelMetadataDirectory(): ModelMetadataDirectoryInterface {
    // Return directory of available models
}
```

---

## Key Features

### 1. Multi-Provider Support
- All providers registered with WordPress AI Client
- Users select provider in admin settings
- Fallback to backup provider if one fails

### 2. Unified API Key Management
- Secure storage in wp_options (encrypted)
- Individual keys per provider
- Auto-detect existing keys from separate plugins

### 3. Model Selection
- Dropdown in settings for each provider
- Shows available models per provider
- Default model configuration

### 4. Connection Testing
- Test button in settings
- Validates API key works
- Shows available models

### 5. Cost Tracking (Future)
- Track API usage per provider
- Budget alerts
- Usage reports

---

## Admin Settings Page

```
Settings → AI Connectors
├── Provider Status (which providers are configured)
├── OpenAI Settings
│   ├── API Key [______________]
│   ├── Default Model [GPT-4o]
│   └── [Test Connection]
├── Anthropic Settings
│   ├── API Key [______________]
│   ├── Default Model [Claude 3.5 Sonnet]
│   └── [Test Connection]
├── DeepSeek Settings
│   ├── API Key [______________]
│   ├── Default Model [DeepSeek Chat]
│   └── [Test Connection]
├── OpenRouter Settings
│   ├── API Key [______________]
│   └── [Test Connection]
├── Ollama Settings
│   ├── Endpoint [http://localhost:11434]
│   ├── Available Models (auto-detected)
│   └── [Test Connection]
├── xAI Settings
│   ├── API Key [______________]
│   ├── Default Model [Grok-2]
│   └── [Test Connection]
└── Save Changes
```

---

## Integration Points

### 1. Register with WP AI Client
```php
add_action('wp_ai_client_registry_init', function($registry) {
    // Register each provider class
    $registry->registerProvider(\AIConnectorPro\Providers\DeepSeek::class);
    $registry->registerProvider(\AIConnectorPro\Providers\OpenRouter::class);
    // ...
});
```

### 2. Register with Connectors API
```php
// Connectors are auto-created from AI Client providers
// Custom settings UI can override descriptions
```

### 3. Settings API
```php
// Store API keys securely
register_setting('ai_connector_pro', 'ai_connector_pro_keys');
```

---

## Security Considerations

1. **API Key Encryption** - Use `wp_encrypt()` / `wp_decrypt()` for storage
2. **Capability Check** - Only `manage_options` can configure
3. **Output Escaping** - All settings escaped in admin UI
4. **Nonce Verification** - All form submissions verified

---

## Implementation Phases

### Phase 1: Core Plugin Structure
- [ ] Plugin scaffolding
- [ ] Base provider abstract class
- [ ] Settings page framework

### Phase 2: New Providers
- [ ] DeepSeek implementation
- [ ] OpenRouter implementation  
- [ ] Ollama implementation
- [ ] xAI implementation
- [ ] Mistral implementation

### Phase 3: Advanced Features
- [ ] Connection testing
- [ ] Model auto-detection
- [ ] Usage tracking

---

## Testing

- Unit tests for each provider
- Integration tests with WordPress AI Client
- Manual testing with real API keys (sandbox mode)

---

## References

- WordPress AI Client: `wp-includes/ai-client/`
- WordPress Connectors: `wp-includes/connectors.php`
- Provider Interface: `wp-includes/php-ai-client/src/Providers/Contracts/ProviderInterface.php`
- Core provider examples: Built-in templates (Anthropic, Google, OpenAI)

---

*This spec is a living document.*