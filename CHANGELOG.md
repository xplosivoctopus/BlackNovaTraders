# Changelog

All notable project changes should be recorded here.

This file follows a simple chronological format rather than a strict release-tag workflow.

## 2026-04-11

### Security

- Enforced server-side CSRF validation for all `POST` submissions to `public/port2.php`, closing the commodity-trading write path that previously accepted missing or invalid CSRF tokens.

### Documentation

- Moved recent fix tracking out of `README.md` and into this changelog so the README stays focused on project overview, setup, and durable architecture/security notes.

## Earlier notable work

### Ranking and scoring

- Reworked ranking balance and leaderboard integrity so standings better reflect live net worth, bounty state, and reputation data.

### UX, social, and addon systems

- Added major cockpit UX, social/mail improvements, and addon infrastructure including the event calendar and ops beacon systems.
