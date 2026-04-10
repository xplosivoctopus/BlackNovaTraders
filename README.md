# BlackNova Traders

Legacy PHP multiplayer space trading and combat game.

## Repo Notes

- Local database credentials are intentionally not tracked.
- Create `public/config/db_config.php` from `public/config/db_config.example.php`.
- Review `public/config/config.php` before publishing if you want to change game name, admin email, or other instance-specific settings.

## Local Setup

1. Create `public/config/db_config.php` from the example file.
2. Point it at your MySQL database.
3. Serve the `public/` directory with PHP.
4. Log in as an admin/developer account to access admin pages.

## Admin Access

Admin pages are session-protected. Log in normally, then use the `ADMIN` section in the main cockpit menu.
