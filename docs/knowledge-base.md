# WordPress Security Knowledge Base

> Research agent knowledge base for AI Security integration into WordPress core.
> Last updated: 2026-03-25

---

## 1. WordPress Core Security Architecture

### 1.1 Key Security Files

| File | Purpose |
|------|---------|
| `wp-includes/pluggable.php` | Functions that can be overridden by plugins (wp_authenticate, wp_logout, etc.) |
| `wp-includes/capabilities.php` | User capabilities and roles system |
| `wp-includes/class-phpass.php` | Password hashing |
| `wp-includes/error-protection.php` | Error handling and protection |
| `wp-includes/pluggable-deprecated.php` | Deprecated pluggable functions |

### 1.2 Authentication System

```php
// Key hooks for authentication
add_action('wp_login', 'your_login_handler', 10, 2);
add_action('wp_logout', 'your_logout_handler');
add_action('wp_authenticate', 'your_auth_handler', 10, 2);

// Check capabilities
current_user_can('manage_options');
wp_verify_nonce($_REQUEST['_wpnonce'], 'your-action');
```

### 1.3 Sanitization Functions

WordPress provides extensive sanitization:

```php
sanitize_text_field($input);
sanitize_email($email);
esc_url($url);
esc_html($text);
esc_attr($attr);
wp_unslash($value);
absint($value);
```

### 1.4 Request Lifecycle Security Hooks

```php
// Load sequence hooks
add_action('init', 'security_init', 1);           // First hook
add_action('wp_loaded', 'security_loaded');       // All plugins loaded
add_action('wp', 'security_wp');                   // Query parsed

// Request analysis hooks
add_action('wp_head', 'security_head');
add_filter('request', 'security_filter_request');  // Modify WP_Query

// Authentication hooks
add_action('wp_login', $handler, $priority, $args);
add_action('wp_logout', $handler);
add_action('wp_authenticate', $handler, $priority, $args);

// Data sanitization hooks
add_filter('sanitize_comment_author', $handler);
add_filter('sanitize_title', $handler);
```

### 1.5 Nonce System

```php
// Create nonce field
wp_nonce_field('my_action', '_wpnonce');

// Verify nonce
wp_verify_nonce($_REQUEST['_wpnonce'], 'my_action');

// Check admin referer
check_admin_referer('my_action');

// Create URL with nonce
$url = wp_nonce_url($url, 'my_action', '_wpnonce');
```

---

## 2. WordPress AI Integration (Already in Core!)

WordPress already has AI integration: `/wp-includes/ai-client/`

### 2.1 Existing AI Client

```php
// Available classes
WP_AI_Client::get_instance();
WP_AI_Client_Ability_Function_Resolver
WP_AI_Client_Prompt_Builder
```

This can be leveraged for our security features!

### 2.2 Adapters Directory

Contains adapters for different AI providers - could add security-specific prompts.

---

## 3. Existing Security Plugin Patterns

### 3.1 Wordfence Patterns
- Request scanning at `init` priority 1
- Login attempt monitoring
- Real-time traffic analysis
- Malware scanning

### 3.2 Sucuri Patterns  
- Server-side scanning
- Audit logging
- Hardening recommendations

### 3.3 Common Detection Methods

```php
// Brute force detection
add_action('wp_login_failed', function($username) {
    // Track failed attempts, block after threshold
});

// Rate limiting
add_action('init', function() {
    // Check request frequency, block abusive IPs
});

// Request inspection
add_filter('request', function($query) {
    // Analyze query vars for malicious patterns
});
```

---

## 4. API Reference for Security Integration

### 4.1 Admin Menu Registration

```php
// Add admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'AI Security',           // Page title
        'AI Security',           // Menu title
        'manage_options',        // Capability
        'ai-security',           // Menu slug
        'ai_security_page',     // Callback
        'dashicons-shield',     // Icon
        2                        // Position
    );
});
```

### 4.2 Settings API

```php
// Register settings
register_setting('ai_security_group', 'ai_security_options');

// Add settings section
add_settings_section('ai_security_main', 'Main Settings', $callback, 'ai-security');

// Add settings field
add_settings_field('ai_security_api_key', 'API Key', $callback, 'ai-security', 'ai_security_main');
```

### 4.3 AJAX for logged-in users

```php
add_action('wp_ajax_ai_security_scan', 'handle_security_scan');
add_action('wp_ajax_nopriv_ai_security_scan', 'handle_security_scan');
```

---

## 5. Architecture for Core Integration

### 5.1 Integration Strategy

Since this is being integrated into core (not a plugin), we can:

1. **Direct file access** - Analyze plugin/theme files directly via PHP
2. **Core hooks** - Use priority 1 hooks for earliest possible detection
3. **Database access** - Direct query access for audit logs
4. **WP_Http extension** - Modify outbound requests for DDoS detection

### 5.2 Proposed Directory Structure

```
wp-includes/
  ai-security/
    class-wp-ai-security-client.php
    class-wp-security-analyzer.php
    class-wp-threat-detector.php
    class-wp-firewall.php
    class-wp-audit-logger.php
```

### 5.3 Key Classes to Create

1. **WP_AI_Security_Client** - Wrapper for AI calls with caching/rate limiting
2. **WP_Security_Analyzer** - Code scanning, vulnerability detection
3. **WP_Threat_Detector** - Real-time threat monitoring
4. **WP_Firewall** - Request filtering and blocking
5. **WP_Audit_Logger** - Security event logging

---

## 6. WordPress Security Resources

### 6.1 Official Documentation
- [Plugin Security Handbook](https://developer.wordpress.org/plugins/security/)
- [Security Best Practices](https://developer.wordpress.org/plugins/security/security-best-practices/)
- [Theme Security](https://developer.wordpress.org/theme-security/)
- [WordPress Security Team](https://make.wordpress.org/core/team/)

### 6.2 Key Hooks for Security
- `init` - Request initialization
- `wp_loaded` - All plugins loaded
- `wp_authenticate` - User authentication
- `wp_login` - Successful login
- `wp_login_failed` - Failed login attempt
- `wp_logout` - User logout
- `admin_init` - Admin page initialization
- `shutdown` - Before output (for logging)

---

## 7. Next Steps for Implementation

1. ✅ Project setup (README, repo config)
2. ⏳ Create AI Security client class
3. ⏳ Implement basic threat detection hooks
4. ⏳ Build admin UI for configuration
5. ⏳ Add plugin/theme scanning capability
6. ⏳ Implement real-time monitoring

---

*This knowledge base will be updated as research continues.*