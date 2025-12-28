
LUX Verified Video

Verified Creator Video Platform for WordPress
Version: 3.5.x
Author: Lucifer Cruz Studios
Requires: WordPress 6.8+, PHP 8.1+

Overview

LUX Verified Video is a full creator-verification, video hosting, analytics, and payout system built on WordPress and powered by Bunny.net Stream.

This plugin is designed for platforms that require:

Verified creators only

Real engagement analytics (not fake views)

Tier-based creator payouts

Visual admin dashboards

Secure video delivery

REST + AI automation support

This is not a simple video embed plugin.
It is a platform layer.

Core Features
Creator Verification

Verification request workflow

Admin approval / rejection

Server-side enforcement

Verified users only can upload videos

Bunny.net Stream Integration

Secure server-side uploads

No API keys exposed to browser

Automatic CDN playback

Video status lifecycle tracking

Analytics Engine

Impression tracking

Play / pause / progress events

Views counted at ≥20 seconds

Watch time accumulation

Completion & retention milestones

Daily rollups + live dashboard

Payout System

Tiered CPM payout model

Weekly payout calculations

Admin review + mark paid

Immutable audit trail

Admin Dashboard

Visual charts (JS-driven)

Videos table

Events (raw analytics)

Verification queue

Payout management

Settings panel

AI control panel

REST + AI Control

Dedicated REST namespace

External automation support

Replit / AI integrations

- Shortcodes:
  [luxvv_upload]
  [luxvv_verified_badge]
  [luxvv_creator_dashboard]

Requirements
Server

PHP 8.1+ (8.2 compatible)

MySQL 8+ (InnoDB)

WordPress 6.8+

REST API enabled

WP-Cron enabled (or real cron)

External Services

Bunny.net Stream account

Video Library ID

Stream API Key (AccessKey)

CDN hostname (vz-xxxxx.b-cdn.net)

Optional: Stream webhook

Installation

Upload the plugin to /wp-content/plugins/lux-verified-video

Activate the plugin in WordPress Admin

On activation:

Database tables are created

Versioning is initialized

Go to Admin → LUX Verified → Settings

Enter Bunny Stream credentials

Save settings

Plugin Structure
lux-verified-video/
├─ lux-verified-video.php
├─ includes/
│  ├─ class-install.php
│  ├─ class-plugin.php
│  ├─ class-settings.php
│  ├─ class-verification.php
│  ├─ class-admin.php
│  ├─ class-admin-menu.php
│  ├─ class-ai.php
│  ├─ class-rest-ai.php
│  ├─ class-repair.php
│  └─ class-helpers.php
├─ assets/
│  ├─ admin.css
│  ├─ admin-dashboard.js
│  └─ player-tracking.js

Database Tables

Created automatically on activation:

Table	Purpose
lux_videos	Video metadata
lux_video_events	Raw analytics events
lux_video_rollups	Daily aggregates
lux_verified_members	Verification workflow
lux_payouts	Weekly payouts
lux_payout_resets	Audit log

If these tables do not exist, analytics and payouts will not function.

Creator Verification Flow

User registers

Verification request is created

Admin approves or rejects

User meta luxvv_verified = 1 is set

Upload UI becomes available

Verification is enforced server-side.

Video Upload Flow

Verified creator uploads file

File is temporarily stored in WordPress

Server creates Bunny Stream video

File uploaded server-side using AccessKey

Status tracked (uploading → processing → ready)

CDN playback URL saved

Analytics Model
Raw Events (Instant)

Impression

Play

Pause

Progress

Completion

Exit <20s

View ≥20s

Rollups (Daily)

Views ≥20 seconds

Total watch time

Completion rate

Retention (25 / 50 / 75 / 100%)

CTR

Live Dashboard

JS charts

Auto-refresh

Admin-only

Payout System
Metric Used

Views ≥ 20 seconds only

Tiered CPM

Configured in Settings as JSON:

[
  {"min_views":0,"cpm_cents":350},
  {"min_views":10000,"cpm_cents":450},
  {"min_views":50000,"cpm_cents":600}
]

Cycle

Weekly calculation

Admin review

Mark paid

Audit preserved permanently

Admin Menus (Required)

Admin → LUX Verified

Dashboard

Videos

Events

Verification Requests

Payouts

Settings

AI Control

All menus are registered only on admin_menu.

REST API

Namespace:

/wp-json/luxvv/v1/


Used for:

External automation

AI integrations

Analytics access

Status checks

REST logic lives exclusively in class-rest-ai.php.

Known Pitfalls (Do Not Repeat)

❌ Registering menus in admin_init

❌ Conditional is_admin() wrappers

❌ Submenus before parent menu

❌ Missing database tables

❌ Client-side Bunny API keys

Validation Checklist

✔ Plugin activates cleanly
✔ Admin menus always appear
✔ Submenus attach correctly
✔ Videos upload to Bunny
✔ Analytics events recorded
✔ Dashboard charts populate
✔ Payouts calculate correctly
✔ Verification gates uploads
✔ REST endpoints respond
✔ No PHP warnings or notices

Philosophy

LUX Verified Video is built for:

Accuracy over vanity metrics

Security over convenience

Compliance over shortcuts

Scalability over hacks

If it appears “simple,” it’s because the complexity is handled correctly.

Roadmap (Optional)

Direct-to-Bunny resumable uploads (TUS)

Real-time minute-level analytics

Creator earnings dashboards

PMPro / WooCommerce gating

Exportable payout reports

White-label mode

License

Proprietary
© Lucifer Cruz Studios