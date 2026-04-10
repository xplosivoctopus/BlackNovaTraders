<?php

function bnt_event_calendar_table(): string
{
    global $db;

    return $db->prefix . 'addon_event_calendar_events';
}

function bnt_event_calendar_ensure_table(): void
{
    global $db;

    static $initialized = false;
    if ($initialized) {
        return;
    }

    $table = bnt_event_calendar_table();
    $db->Execute(
        "CREATE TABLE IF NOT EXISTS {$table} (" .
        "event_id int unsigned NOT NULL AUTO_INCREMENT," .
        "title varchar(150) NOT NULL," .
        "summary varchar(255) NOT NULL default ''," .
        "body text NOT NULL," .
        "starts_at datetime NULL," .
        "ends_at datetime NULL," .
        "visibility enum('draft','published') NOT NULL default 'draft'," .
        "cta_label varchar(60) NOT NULL default ''," .
        "cta_url varchar(255) NOT NULL default ''," .
        "created_at datetime NOT NULL," .
        "updated_at datetime NOT NULL," .
        "PRIMARY KEY (event_id)" .
        ")"
    );

    $initialized = true;
}

function bnt_event_calendar_fetch_all(bool $includeDrafts = false): array
{
    global $db;

    bnt_event_calendar_ensure_table();

    $table = bnt_event_calendar_table();
    $sql = "SELECT * FROM {$table}";
    $params = array();
    if (!$includeDrafts) {
        $sql .= " WHERE visibility='published'";
    }
    $sql .= " ORDER BY COALESCE(starts_at, created_at) ASC, event_id ASC";

    $result = $db->Execute($sql, $params);
    if (!$result) {
        return array();
    }

    $events = array();
    while (!$result->EOF) {
        $events[] = $result->fields;
        $result->MoveNext();
    }

    return $events;
}

function bnt_event_calendar_fetch_one(int $eventId): ?array
{
    global $db;

    bnt_event_calendar_ensure_table();

    $result = $db->Execute(
        "SELECT * FROM " . bnt_event_calendar_table() . " WHERE event_id=? LIMIT 1",
        array($eventId)
    );

    if (!$result || $result->EOF) {
        return null;
    }

    return $result->fields;
}

function bnt_event_calendar_normalize_datetime(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return gmdate('Y-m-d H:i:s', $timestamp);
}

function bnt_event_calendar_status(array $event): string
{
    if (($event['visibility'] ?? 'draft') !== 'published') {
        return 'Draft';
    }

    $now = time();
    $start = !empty($event['starts_at']) ? strtotime((string) $event['starts_at'] . ' UTC') : null;
    $end = !empty($event['ends_at']) ? strtotime((string) $event['ends_at'] . ' UTC') : null;

    if ($start !== null && $start > $now) {
        return 'Upcoming';
    }

    if ($end !== null && $end < $now) {
        return 'Completed';
    }

    return 'Active';
}

function bnt_event_calendar_format_datetime(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return 'TBA';
    }

    $timestamp = strtotime($value . ' UTC');
    if ($timestamp === false) {
        return $value;
    }

    return gmdate('M j, Y \a\t H:i', $timestamp) . ' UTC';
}

function bnt_event_calendar_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function bnt_event_calendar_render_styles(): void
{
    static $rendered = false;
    if ($rendered) {
        return;
    }

    $rendered = true;

    echo <<<HTML
<style>
.liveops-shell {
  color: #dbefff;
}
.liveops-hero {
  padding: 24px 26px;
  margin-bottom: 18px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: linear-gradient(135deg, rgba(4,15,30,0.98), rgba(8,26,50,0.96));
  box-shadow: 0 10px 36px rgba(0,0,0,0.42);
}
.liveops-eyebrow {
  color: #7edfff;
  font-size: 11px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  margin-bottom: 8px;
}
.liveops-title {
  margin: 0 0 8px;
  font-size: 32px;
  color: #f2fbff;
}
.liveops-copy {
  margin: 0;
  line-height: 1.6;
  color: rgba(220, 238, 248, 0.9);
}
.liveops-grid {
  display: grid;
  grid-template-columns: 1.3fr 0.9fr;
  gap: 16px;
}
.liveops-panel {
  border: 1px solid rgba(0, 238, 255, 0.14);
  background: rgba(5, 16, 29, 0.96);
  padding: 18px;
}
.liveops-section-title {
  margin: 0 0 14px;
  font-size: 18px;
  color: #f2fbff;
}
.liveops-event {
  padding: 14px 0;
  border-top: 1px solid rgba(0, 238, 255, 0.08);
}
.liveops-event:first-child {
  border-top: 0;
  padding-top: 0;
}
.liveops-event__header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 6px;
}
.liveops-event__name {
  margin: 0;
  font-size: 20px;
  color: #ebfbff;
}
.liveops-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 5px 10px;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  border-radius: 999px;
}
.liveops-pill--active { background: rgba(0,255,136,0.14); color: #7cf0b6; }
.liveops-pill--upcoming { background: rgba(0,238,255,0.12); color: #7edfff; }
.liveops-pill--completed { background: rgba(245,158,11,0.1); color: #f8c765; }
.liveops-pill--draft { background: rgba(255,51,85,0.1); color: #ff96a8; }
.liveops-meta {
  font-size: 12px;
  color: rgba(170, 200, 225, 0.76);
  margin-bottom: 8px;
}
.liveops-summary {
  font-size: 14px;
  color: #eefbff;
  margin-bottom: 8px;
}
.liveops-body {
  line-height: 1.65;
  color: rgba(219,239,255,0.9);
}
.liveops-action {
  display: inline-block;
  margin-top: 12px;
  padding: 9px 12px;
  border: 1px solid rgba(0, 238, 255, 0.18);
  background: rgba(8, 29, 48, 0.96);
  color: #eaf9ff;
  text-decoration: none;
}
.liveops-empty {
  color: rgba(170, 200, 225, 0.72);
  font-style: italic;
}
.liveops-mini-list {
  display: grid;
  gap: 12px;
}
.liveops-mini-card {
  border: 1px solid rgba(0, 238, 255, 0.1);
  padding: 12px 14px;
  background: rgba(3, 11, 21, 0.9);
}
.liveops-mini-card h4 {
  margin: 0 0 4px;
  color: #effaff;
  font-size: 15px;
}
.liveops-mini-card p {
  margin: 0;
  color: rgba(219,239,255,0.86);
  line-height: 1.5;
  font-size: 13px;
}
.liveops-admin-form {
  display: grid;
  gap: 12px;
  margin-bottom: 18px;
}
.liveops-admin-row {
  display: grid;
  gap: 6px;
}
.liveops-admin-row input,
.liveops-admin-row textarea,
.liveops-admin-row select {
  width: 100%;
  box-sizing: border-box;
  padding: 10px 12px;
  background: rgba(4, 14, 26, 0.92);
  border: 1px solid rgba(0, 238, 255, 0.14);
  color: #eefbff;
}
.liveops-admin-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.liveops-admin-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.liveops-admin-actions button,
.liveops-admin-actions a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 10px 12px;
  border: 1px solid rgba(0, 238, 255, 0.18);
  background: rgba(8, 29, 48, 0.96);
  color: #eaf9ff;
  text-decoration: none;
  cursor: pointer;
}
.liveops-admin-actions .danger {
  border-color: rgba(255, 51, 85, 0.2);
  color: #ff9dad;
}
.liveops-admin-list {
  display: grid;
  gap: 12px;
}
.liveops-banner {
  width: min(1100px, calc(100% - 24px));
  margin: 10px auto 14px;
  border: 1px solid rgba(0, 238, 255, 0.18);
  background: linear-gradient(180deg, rgba(8, 25, 40, 0.97), rgba(4, 11, 20, 0.98));
  box-shadow: 0 0 18px rgba(0, 238, 255, 0.08);
}
.liveops-banner__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 12px 14px;
}
.liveops-banner__eyebrow {
  font-size: 10px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #7edfff;
  margin-bottom: 4px;
}
.liveops-banner__title {
  color: #eefbff;
  font-size: 15px;
  font-weight: 700;
}
.liveops-banner__meta {
  font-size: 12px;
  color: rgba(180, 210, 230, 0.8);
}
@media (max-width: 920px) {
  .liveops-grid,
  .liveops-admin-grid {
    grid-template-columns: 1fr;
  }
  .liveops-banner__inner {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
HTML;
}

function bnt_event_calendar_partition_events(array $events): array
{
    $partitioned = array(
        'active' => array(),
        'upcoming' => array(),
        'completed' => array(),
        'draft' => array(),
    );

    foreach ($events as $event) {
        $status = strtolower(bnt_event_calendar_status($event));
        if (!isset($partitioned[$status])) {
            $status = 'draft';
        }
        $partitioned[$status][] = $event;
    }

    return $partitioned;
}

function bnt_event_calendar_render_event_card(array $event): void
{
    $status = bnt_event_calendar_status($event);
    $statusClass = 'liveops-pill--' . strtolower($status);
    $title = bnt_event_calendar_escape((string) $event['title']);
    $summary = bnt_event_calendar_escape((string) ($event['summary'] ?? ''));
    $body = nl2br(bnt_event_calendar_escape((string) ($event['body'] ?? '')));
    $startsAt = bnt_event_calendar_format_datetime($event['starts_at'] ?? null);
    $endsAt = bnt_event_calendar_format_datetime($event['ends_at'] ?? null);
    $ctaLabel = trim((string) ($event['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($event['cta_url'] ?? ''));

    echo "<article class='liveops-event'>";
    echo "<div class='liveops-event__header'>";
    echo "<h3 class='liveops-event__name'>{$title}</h3>";
    echo "<span class='liveops-pill {$statusClass}'>" . bnt_event_calendar_escape($status) . "</span>";
    echo "</div>";
    echo "<div class='liveops-meta'>Starts {$startsAt} · Ends {$endsAt}</div>";
    if ($summary !== '') {
        echo "<div class='liveops-summary'>{$summary}</div>";
    }
    if ($body !== '') {
        echo "<div class='liveops-body'>{$body}</div>";
    }
    if ($ctaLabel !== '' && $ctaUrl !== '') {
        echo "<a class='liveops-action' href='" . bnt_event_calendar_escape($ctaUrl) . "'>" . bnt_event_calendar_escape($ctaLabel) . "</a>";
    }
    echo "</article>";
}

function bnt_event_calendar_render_player_page(): void
{
    $events = bnt_event_calendar_fetch_all(false);
    $partitioned = bnt_event_calendar_partition_events($events);

    bnt_event_calendar_render_styles();

    echo "<div class='liveops-shell'>";
    echo "<section class='liveops-hero'>";
    echo "<div class='liveops-eyebrow'>Live Ops</div>";
    echo "<h1 class='liveops-title'>Event Calendar</h1>";
    echo "<p class='liveops-copy'>Track active events, upcoming operations, and limited-time server happenings from one place. This is the shared schedule players can check before logging serious turns.</p>";
    echo "</section>";

    echo "<div class='liveops-grid'>";
    echo "<section class='liveops-panel'>";
    echo "<h2 class='liveops-section-title'>Active & Upcoming</h2>";
    if (empty($partitioned['active']) && empty($partitioned['upcoming'])) {
        echo "<p class='liveops-empty'>No live or upcoming events have been published yet.</p>";
    } else {
        foreach ($partitioned['active'] as $event) {
            bnt_event_calendar_render_event_card($event);
        }
        foreach ($partitioned['upcoming'] as $event) {
            bnt_event_calendar_render_event_card($event);
        }
    }
    echo "</section>";

    echo "<aside class='liveops-panel'>";
    echo "<h2 class='liveops-section-title'>Recently Completed</h2>";
    if (empty($partitioned['completed'])) {
        echo "<p class='liveops-empty'>No completed events to show yet.</p>";
    } else {
        echo "<div class='liveops-mini-list'>";
        $recentCompleted = array_slice(array_reverse($partitioned['completed']), 0, 5);
        foreach ($recentCompleted as $event) {
            echo "<section class='liveops-mini-card'>";
            echo "<h4>" . bnt_event_calendar_escape((string) $event['title']) . "</h4>";
            echo "<p>Ended " . bnt_event_calendar_format_datetime($event['ends_at'] ?? null) . "</p>";
            if (!empty($event['summary'])) {
                echo "<p style='margin-top:6px;'>" . bnt_event_calendar_escape((string) $event['summary']) . "</p>";
            }
            echo "</section>";
        }
        echo "</div>";
    }
    echo "</aside>";
    echo "</div>";
    echo "</div>";
}

function bnt_event_calendar_render_admin_page(): void
{
    global $db;

    bnt_event_calendar_render_styles();
    bnt_event_calendar_ensure_table();

    $message = null;
    $messageClass = 'ok';
    $editing = null;
    $editId = (int) ($_GET['edit'] ?? 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        bnt_require_csrf();
        $action = trim((string) ($_POST['event_action'] ?? ''));

        if ($action === 'save') {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $summary = trim((string) ($_POST['summary'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            $startsAt = bnt_event_calendar_normalize_datetime($_POST['starts_at'] ?? null);
            $endsAt = bnt_event_calendar_normalize_datetime($_POST['ends_at'] ?? null);
            $visibility = (($_POST['visibility'] ?? 'draft') === 'published') ? 'published' : 'draft';
            $ctaLabel = trim((string) ($_POST['cta_label'] ?? ''));
            $ctaUrl = trim((string) ($_POST['cta_url'] ?? ''));

            if ($title === '') {
                $message = 'Title is required.';
                $messageClass = 'err';
            } elseif ($startsAt !== null && $endsAt !== null && strtotime($endsAt . ' UTC') < strtotime($startsAt . ' UTC')) {
                $message = 'End time must be after start time.';
                $messageClass = 'err';
            } else {
                if ($eventId > 0) {
                    $result = $db->Execute(
                        "UPDATE " . bnt_event_calendar_table() . " SET title=?, summary=?, body=?, starts_at=?, ends_at=?, visibility=?, cta_label=?, cta_url=?, updated_at=UTC_TIMESTAMP() WHERE event_id=?",
                        array($title, $summary, $body, $startsAt, $endsAt, $visibility, $ctaLabel, $ctaUrl, $eventId)
                    );
                    db_op_result($db, $result, __LINE__, __FILE__);
                    $message = 'Event updated.';
                } else {
                    $result = $db->Execute(
                        "INSERT INTO " . bnt_event_calendar_table() . " (title, summary, body, starts_at, ends_at, visibility, cta_label, cta_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                        array($title, $summary, $body, $startsAt, $endsAt, $visibility, $ctaLabel, $ctaUrl)
                    );
                    db_op_result($db, $result, __LINE__, __FILE__);
                    $message = 'Event created.';
                }
            }
        } elseif ($action === 'delete') {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            if ($eventId > 0) {
                $result = $db->Execute("DELETE FROM " . bnt_event_calendar_table() . " WHERE event_id=?", array($eventId));
                db_op_result($db, $result, __LINE__, __FILE__);
                $message = 'Event deleted.';
                $messageClass = 'warn';
                if ($editId === $eventId) {
                    $editId = 0;
                }
            }
        }
    }

    if ($editId > 0) {
        $editing = bnt_event_calendar_fetch_one($editId);
    }

    $events = array_reverse(bnt_event_calendar_fetch_all(true));

    $current = array(
        'event_id' => $editing['event_id'] ?? 0,
        'title' => $editing['title'] ?? '',
        'summary' => $editing['summary'] ?? '',
        'body' => $editing['body'] ?? '',
        'starts_at' => isset($editing['starts_at']) && $editing['starts_at'] ? str_replace(' ', 'T', substr((string) $editing['starts_at'], 0, 16)) : '',
        'ends_at' => isset($editing['ends_at']) && $editing['ends_at'] ? str_replace(' ', 'T', substr((string) $editing['ends_at'], 0, 16)) : '',
        'visibility' => $editing['visibility'] ?? 'draft',
        'cta_label' => $editing['cta_label'] ?? '',
        'cta_url' => $editing['cta_url'] ?? '',
    );

    echo "<div class='liveops-shell'>";
    echo "<section class='liveops-hero'>";
    echo "<div class='liveops-eyebrow'>Addon Admin</div>";
    echo "<h1 class='liveops-title'>Event Calendar Control</h1>";
    echo "<p class='liveops-copy'>Schedule events, write operation briefings, and decide when they go live for players. Published events appear immediately in the Event Calendar addon and the live banner hook.</p>";
    echo "</section>";

    if ($message !== null) {
        $color = $messageClass === 'err' ? '#ff95a8' : ($messageClass === 'warn' ? '#f8c765' : '#7cf0b6');
        $border = $messageClass === 'err' ? '#ff3355' : ($messageClass === 'warn' ? '#f59e0b' : '#00ff88');
        echo "<div style='border-left:3px solid {$border}; background:rgba(4,14,26,0.85); color:{$color}; padding:12px 14px; margin-bottom:16px;'>" . bnt_event_calendar_escape($message) . "</div>";
    }

    echo "<div class='liveops-grid'>";
    echo "<section class='liveops-panel'>";
    echo "<h2 class='liveops-section-title'>" . ($current['event_id'] ? 'Edit Event' : 'Create Event') . "</h2>";
    echo "<form class='liveops-admin-form' method='post' action='" . bnt_event_calendar_escape(bnt_addon_url('event_calendar', 'admin', $current['event_id'] ? array('edit' => (int) $current['event_id']) : array())) . "'>";
    echo bnt_csrf_input();
    echo "<input type='hidden' name='event_action' value='save'>";
    echo "<input type='hidden' name='event_id' value='" . (int) $current['event_id'] . "'>";
    echo "<label class='liveops-admin-row'>Title<input type='text' name='title' maxlength='150' value='" . bnt_event_calendar_escape((string) $current['title']) . "'></label>";
    echo "<label class='liveops-admin-row'>Summary<input type='text' name='summary' maxlength='255' value='" . bnt_event_calendar_escape((string) $current['summary']) . "'></label>";
    echo "<label class='liveops-admin-row'>Briefing<textarea name='body' rows='8'>" . bnt_event_calendar_escape((string) $current['body']) . "</textarea></label>";
    echo "<div class='liveops-admin-grid'>";
    echo "<label class='liveops-admin-row'>Starts At (UTC)<input type='datetime-local' name='starts_at' value='" . bnt_event_calendar_escape((string) $current['starts_at']) . "'></label>";
    echo "<label class='liveops-admin-row'>Ends At (UTC)<input type='datetime-local' name='ends_at' value='" . bnt_event_calendar_escape((string) $current['ends_at']) . "'></label>";
    echo "</div>";
    echo "<div class='liveops-admin-grid'>";
    echo "<label class='liveops-admin-row'>Visibility<select name='visibility'><option value='draft'" . ($current['visibility'] === 'draft' ? ' selected' : '') . ">Draft</option><option value='published'" . ($current['visibility'] === 'published' ? ' selected' : '') . ">Published</option></select></label>";
    echo "<label class='liveops-admin-row'>Call To Action Label<input type='text' name='cta_label' maxlength='60' value='" . bnt_event_calendar_escape((string) $current['cta_label']) . "'></label>";
    echo "</div>";
    echo "<label class='liveops-admin-row'>Call To Action URL<input type='text' name='cta_url' maxlength='255' value='" . bnt_event_calendar_escape((string) $current['cta_url']) . "'></label>";
    echo "<div class='liveops-admin-actions'>";
    echo "<button type='submit'>Save Event</button>";
    echo "<a href='" . bnt_event_calendar_escape(bnt_addon_url('event_calendar', 'admin')) . "'>New Event</a>";
    echo "<a href='" . bnt_event_calendar_escape(bnt_addon_url('event_calendar', 'index')) . "'>View Player Page</a>";
    echo "</div>";
    echo "</form>";
    echo "</section>";

    echo "<aside class='liveops-panel'>";
    echo "<h2 class='liveops-section-title'>Existing Events</h2>";
    if (empty($events)) {
        echo "<p class='liveops-empty'>No events created yet.</p>";
    } else {
        echo "<div class='liveops-admin-list'>";
        foreach ($events as $event) {
            echo "<section class='liveops-mini-card'>";
            echo "<h4>" . bnt_event_calendar_escape((string) $event['title']) . "</h4>";
            echo "<p>" . bnt_event_calendar_escape(bnt_event_calendar_status($event)) . " · Starts " . bnt_event_calendar_escape(bnt_event_calendar_format_datetime($event['starts_at'] ?? null)) . "</p>";
            if (!empty($event['summary'])) {
                echo "<p style='margin-top:6px;'>" . bnt_event_calendar_escape((string) $event['summary']) . "</p>";
            }
            echo "<div class='liveops-admin-actions' style='margin-top:10px;'>";
            echo "<a href='" . bnt_event_calendar_escape(bnt_addon_url('event_calendar', 'admin', array('edit' => (int) $event['event_id']))) . "'>Edit</a>";
            echo "<form method='post' action='" . bnt_event_calendar_escape(bnt_addon_url('event_calendar', 'admin')) . "' style='margin:0;'>";
            echo bnt_csrf_input();
            echo "<input type='hidden' name='event_action' value='delete'>";
            echo "<input type='hidden' name='event_id' value='" . (int) $event['event_id'] . "'>";
            echo "<button class='danger' type='submit'>Delete</button>";
            echo "</form>";
            echo "</div>";
            echo "</section>";
        }
        echo "</div>";
    }
    echo "</aside>";
    echo "</div>";
    echo "</div>";
}

function bnt_event_calendar_get_featured_event(): ?array
{
    $events = bnt_event_calendar_fetch_all(false);
    $active = null;
    $upcoming = null;

    foreach ($events as $event) {
        $status = bnt_event_calendar_status($event);
        if ($status === 'Active' && $active === null) {
            $active = $event;
        } elseif ($status === 'Upcoming' && $upcoming === null) {
            $upcoming = $event;
        }
    }

    return $active ?? $upcoming;
}

function bnt_event_calendar_render_banner(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (empty($_SESSION['logged_in'])) {
        return '';
    }

    $event = bnt_event_calendar_get_featured_event();
    if ($event === null) {
        return '';
    }

    $status = bnt_event_calendar_status($event);

    ob_start();
    bnt_event_calendar_render_styles();
    echo "<section class='liveops-banner' aria-label='Live ops banner'>";
    echo "<div class='liveops-banner__inner'>";
    echo "<div>";
    echo "<div class='liveops-banner__eyebrow'>{$status} Operation</div>";
    echo "<div class='liveops-banner__title'>" . bnt_event_calendar_escape((string) $event['title']) . "</div>";
    echo "<div class='liveops-banner__meta'>Starts " . bnt_event_calendar_escape(bnt_event_calendar_format_datetime($event['starts_at'] ?? null)) . "</div>";
    echo "</div>";
    echo "<a class='liveops-action' href='" . bnt_event_calendar_escape(bnt_addon_url('event_calendar', 'index')) . "'>Open Event Calendar</a>";
    echo "</div>";
    echo "</section>";

    return (string) ob_get_clean();
}

