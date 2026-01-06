# Changelog
All notable changes to this project will be documented in this file.

The format follows Keep a Changelog principles.
This project uses semantic versioning.

---

## [3.6.0] – Unreleased
### Added
- Single authoritative video upload handler (`admin_post_luxvv_upload_video`)
- Server-side file validation (size + MIME)
- Visible upload success and error redirects (no silent failures)
- Canonical upload UI enforcement (single upload form only)
- Improved verification gating enforcement (server-side only)
- Structured logging for upload lifecycle events

### Changed
- Removed unused AJAX upload handlers
- Normalized upload architecture to a single submission path
- Refactored upload logic to be handler-driven, not UI-driven
- Hardened verification checks prior to upload handling

### Removed
- Duplicate or dead upload UI paths
- Client-side assumptions about upload success

---

## [3.5.7] – 2026-01-05
### Added
- Bunny Stream integration scaffolding
- Creator analytics dashboards
- Daily analytics rollups
- Tier-based payout calculations
- REST namespace `/luxvv/v1/`

### Fixed
- Admin menu registration order
- Verification enforcement inconsistencies
- Analytics rollup edge cases

---

## [3.5.0] – Initial Platform Release
### Added
- Creator verification workflow
- Video metadata storage
- Raw analytics event tracking
- Admin dashboards
- Plugin activation installer with DB schema

---
