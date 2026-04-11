# BlackNova Traders

BlackNova Traders is a heavily overhauled revival of the classic browser-based multiplayer space trading and combat game.

This codebase has been brought forward to run cleanly on current PHP and MySQL, with major modernization work across the stack:

- A complete gameplay/runtime overhaul to keep the project viable on current infrastructure
- Database access modernization through PDO-backed compatibility work, making the project more portable and easier to adapt to other database engines
- Significant security tightening across authentication, admin access, request handling, and legacy unsafe code paths
- UX upgrades to make the game more usable and maintainable while preserving the original game structure
- Ranking and scoring corrections so leaderboard data reflects live net worth, real bounty state, and reputation more accurately

## Current State

This is not just an archival snapshot. The game has been actively updated to:

- run on modern PHP versions
- work correctly with current MySQL versions
- support a more versatile database layer via PDO compatibility work
- harden legacy admin and session flows
- improve the in-game interface and overall usability
- repair legacy ranking math and leaderboard consistency issues

## Repository Notes

- Local database credentials are intentionally not tracked.
- Create `public/config/db_config.php` from `public/config/db_config.example.php`.
- `ANALYSIS.md` is intentionally excluded from the public repo.
- Review `public/config/config.php` before deployment if you want to change instance-specific settings such as game name, admin email, paths, and gameplay defaults.

## Local Setup

1. Create `public/config/db_config.php` from `public/config/db_config.example.php`.
2. Point it at your database.
3. Serve the `public/` directory with PHP.
4. Review `public/config/config.php` for site-specific settings.
5. Create or use an account that is listed as an admin/developer in the game config.

## Admin Access

Admin access is now session-based.

- Log in normally through the game.
- If your account has an `admin` or `developer` role, the main cockpit will show an `ADMIN` section.
- Use that in-game admin menu to access administration, setup info, scheduler, performance tools, universe creation, and control panels.

The old public shared-password style admin flow is no longer the primary access model.

## Security and Modernization Highlights

- Legacy trust in ambient request state has been reduced
- Admin-only tools are protected behind authenticated admin/developer sessions
- CSRF protections were added to sensitive POST flows
- Password handling has been modernized
- Several legacy SQL paths were tightened and modernized
- Setup and diagnostic pages were updated for current PHP behavior

## License

This project is distributed under the GNU Affero General Public License v3. See [LICENSE](LICENSE).
