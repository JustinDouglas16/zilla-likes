# ZillaLikes

A modern, lightweight like button plugin for WordPress. Let visitors like
your posts and pages with a single click — no account required.

## Features

- **Like / unlike toggle** — users can like and unlike posts
- **No jQuery dependency** — vanilla JavaScript with optimistic UI updates
- **REST API powered** — clean endpoints with nonce-based security
- **Batched hydration** — a single API call loads all like states on page
  load, cache-friendly
- **Persistent tracking** — custom database table tracks likes per user
  (logged-in) or per IP (guests)
- **Accessible** — semantic `<button>` elements with `aria-label` attributes
- **Customizable** — disable CSS, set postfix labels, exclude specific posts
- **Widget included** — display your most liked posts in any sidebar
- **Shortcode & template tag** — place the like button anywhere

## Requirements

- WordPress 5.9+
- PHP 7.4+

## Installation

1. Download or clone this repository into `wp-content/plugins/zilla-likes/`
2. Activate the plugin through **Plugins → Installed Plugins**
3. The database table is created automatically on activation
4. Configure settings under **Settings → ZillaLikes**

## Usage

### Automatic

By default, the like button is appended to posts and pages automatically.
Control this behavior in **Settings → ZillaLikes**.

### Shortcode

Use the shortcode in any post or page editor:

```text
[zilla_likes]
```

Target a specific post by ID:

```text
[zilla_likes id="42"]
```

### Template Tag

Place the like button directly in your theme templates:

```php
<?php if (function_exists('zilla_likes')) zilla_likes(); ?>
```

Pass a specific post ID:

```php
<?php zilla_likes(42); ?>
```

## Settings

Navigate to **Settings → ZillaLikes** in the WordPress admin.

| Setting | Description |
|---|---|
| **Add to posts** | Automatically append the like button to single posts |
| **Add to pages** | Automatically append the like button to pages |
| **Add to archives** | Show on archive, home, search, and excerpt views |
| **Exclude from** | Comma-separated list of post/page IDs to skip |
| **Disable CSS** | Skip loading the bundled stylesheet for custom styling |
| **Zero likes text** | Label when a post has 0 likes (default: "Likes") |
| **One like text** | Label when a post has 1 like (default: "Like") |
| **Multiple likes text** | Label when a post has 2+ likes (default: "Likes") |

## REST API

All endpoints are under the `zilla-likes/v1` namespace. Nonce
authentication is handled automatically via `X-WP-Nonce` headers.

### Toggle a like

```text
POST /wp-json/zilla-likes/v1/like/{post_id}
```

**Response:**

```json
{
  "count": 5,
  "liked": true,
  "postfix": "Likes"
}
```

### Get like state for a single post

```text
GET /wp-json/zilla-likes/v1/likes/{post_id}
```

**Response:**

```json
{
  "count": 5,
  "liked": false,
  "postfix": "Likes"
}
```

### Batch get like states

```text
GET /wp-json/zilla-likes/v1/likes?ids=1,2,3
```

**Response:**

```json
{
  "1": { "count": 5, "liked": true, "postfix": "Likes" },
  "2": { "count": 0, "liked": false, "postfix": "Likes" },
  "3": { "count": 1, "liked": false, "postfix": "Like" }
}
```

> Batch requests are capped at 100 IDs.

## Widget

A **ZillaLikes – Most Liked** widget is available under
**Appearance → Widgets**. It displays a ranked list of your most liked
posts.

Widget options:

- **Title** — widget heading
- **Description** — optional text above the list
- **Number of posts** — how many to show (1–20)
- **Show like count** — display the count next to each post title

## Custom Styling

Disable the built-in CSS in settings, then target these classes:

```css
/* The button */
.zilla-likes { }

/* Liked state */
.zilla-likes.is-liked { }

/* Loading state (during API call) */
.zilla-likes.is-loading { }

/* The heart SVG */
.zilla-likes-icon { }

/* The numeric count */
.zilla-likes-count { }

/* The postfix label */
.zilla-likes-postfix { }
```

## File Structure

```text
zilla-likes/
├── zilla-likes.php          # Plugin entry point and template tag
├── src/
│   ├── Plugin.php            # Main plugin class, hooks, lifecycle
│   ├── Settings.php          # Admin settings page and options
│   ├── LikeHandler.php       # Like logic, DB queries, rendering
│   ├── RestApi.php           # REST API endpoints
│   └── Widget.php            # Most liked posts widget
├── assets/
│   ├── js/
│   │   └── zilla-likes.js   # Vanilla JS – hydration, toggle, UI
│   └── css/
│       └── zilla-likes.css  # Default button and animation styles
└── languages/                # Translation files
```

## Uninstallation

When the plugin is deleted through the WordPress admin, it cleans up
after itself:

- Drops the `wp_zilla_likes` database table
- Removes the `zilla_likes_settings` option
- Deletes all `_zilla_likes` post meta entries

## License

GPLv2 or later.
