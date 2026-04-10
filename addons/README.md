# Addons

Each addon lives in its own folder under this directory.

Minimum structure:

```text
addons/
  your_addon/
    addon.json
    bootstrap.php
```

`addon.json` example:

```json
{
  "slug": "your_addon",
  "name": "Your Addon",
  "version": "1.0.0",
  "author": "Your Name",
  "description": "What the addon does.",
  "bootstrap": "bootstrap.php"
}
```

`bootstrap.php` can register runtime hooks:

```php
<?php
bnt_addon_register_hook('page_head', function () {
    return '<style>.my-addon{color:#fff;}</style>';
});

bnt_addon_register_hook('page_top', function () {
    return '<div class="my-addon">Addon is active.</div>';
});
```

Available hooks:

- `page_head`: inject styles, metadata, or scripts into `<head>`
- `page_top`: render content near the top of the page body
- `page_footer`: render content before the footer closes

Install flow:

1. Drop the addon folder into `addons/`
2. Open `Admin -> Addons`
3. Enable it

No core file edits are required for normal addon installs.

