# LUX Verified Video — Developer Documentation

⚠️ This document is for developers and maintainers only.

---

## Architectural Principles

1. **Single Source of Truth**
   - All videos are represented by the `lux_video` custom post type.
   - No parallel or shadow video models are allowed.

2. **Single Upload Path**
   - Exactly one frontend upload form.
   - Exactly one server-side handler.
   - UI is disposable; the handler is authoritative.

3. **Server-Side Enforcement Only**
   - Verification checks, permissions, and validation occur on the server.
   - JavaScript is never trusted.

---

## Upload Architecture

### Canonical Handler
```php
admin_post_luxvv_upload_video
