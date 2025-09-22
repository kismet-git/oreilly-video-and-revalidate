# O’Reilly Video & Revalidate (MU)

Registers the `oreilly/kaltura-video` Gutenberg block and wires WordPress to a Next.js
App-Router site with ISR + on-publish revalidation.

## Features
- Consent-gated, lazy-loaded container for Kaltura embeds
- GA4 data attributes: data-video="kaltura", data-entryid
- `transition_post_status` → POST `{ path, secret }` to Next `/api/revalidate`
- WPGraphQL fields exposing block attributes (`kalturaBlocks`)
- Multisite-safe, minimal enqueue, sanitized/escaped output

## Requirements
- WordPress 6.x, PHP 8.2+
- WPGraphQL active
- Next.js 15 with `/api/revalidate` and ISR

## Install (MU)
Copy folder to `wp-content/mu-plugins/oreilly-video-and-revalidate/`.
Loader `wp-content/mu-plugins/oreilly-video-and-revalidate.php` autoloads it.

## Configure (wp-config.php)
define('REVALIDATE_SECRET', 'dev-secret-change-me');
define('VERCEL_REVALIDATE_URL', 'http://localhost:3001/api/revalidate');

## Default path mapping
`/articles/{post_name}` — override with:
add_filter('oreilly_revalidate_path', function($path, $post){ return '/articles/'.$post->post_name; }, 10, 2);

## GraphQL example
{
  postBy(slug: "kaltura-test") {
    slug
    kalturaBlocks { partnerId entryId poster autoplay consentRequired }
  }
}
