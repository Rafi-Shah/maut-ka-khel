<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

requireAdminAuth();

$pdo = getDB();
$username = getLoggedInAdminUsername();

// Initial data for fast server-side rendering
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsStmt->fetchAll() as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

$coursesStmt = $pdo->query("SELECT c.*, COUNT(t.id) as task_count FROM courses c LEFT JOIN tasks t ON c.code = t.course_code GROUP BY c.id ORDER BY c.sort_order ASC, c.id ASC");
$courses = $coursesStmt->fetchAll();

$tasksStmt = $pdo->query("SELECT t.*, c.name as course_name, c.color as course_color, c.dim_color as course_dim_color 
                          FROM tasks t 
                          LEFT JOIN courses c ON t.course_code = c.code 
                          ORDER BY t.deadline_date ASC, t.id ASC");
$tasks = $tasksStmt->fetchAll();

$totalTasks = count($tasks);
$doneTasks = count(array_filter($tasks, fn($t) => (int)$t['is_done'] === 1));
$pendingTasks = $totalTasks - $doneTasks;
$totalCourses = count($courses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Portal — Maut Ka Khel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --paper: #0c110e;
      --card: #151d18;
      --card-hover: #1c2720;
      --card-border: rgba(232, 228, 216, 0.12);
      --text: #e8e4d8;
      --text-dim: #9b9788;
      --amber: #e3a13d;
      --amber-dim: rgba(227, 161, 61, 0.14);
      --amber-hover: #f3ad43;
      --done: #6f9e78;
      --done-dim: #1c2820;
      --danger: #e06c75;
      --danger-dim: rgba(224, 108, 117, 0.15);
      --line: rgba(232, 228, 216, 0.1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--paper);
      color: var(--text);
      font-family: 'Space Grotesk', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    body::before {
      content: "";
      position: fixed; inset: 0;
      background: radial-gradient(ellipse at 50% -10%, rgba(227,161,61,0.06), transparent 50%);
      pointer-events: none;
      z-index: 0;
    }

    /* Header Nav */
    header.portal-nav {
      position: sticky; top: 0; z-index: 100;
      background: rgba(12, 17, 14, 0.88);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--line);
      padding: 0.9rem 6vw;
      display: flex; justify-content: space-between; align-items: center;
      gap: 1.5rem; flex-wrap: wrap;
    }
    .nav-brand {
      display: flex; align-items: center; gap: 0.75rem;
    }
    .brand-dot {
      width: 10px; height: 10px; border-radius: 50%;
      background: var(--amber); box-shadow: 0 0 10px var(--amber);
    }
    .brand-title {
      font-family: 'Fraunces', serif; font-size: 1.35rem; font-weight: 500;
      color: var(--text);
    }
    .brand-tag {
      font-family: 'JetBrains Mono', monospace; font-size: 0.65rem;
      letter-spacing: 0.12em; text-transform: uppercase;
      padding: 0.2rem 0.55rem; border-radius: 4px;
      background: var(--amber-dim); color: var(--amber);
      border: 1px solid rgba(227, 161, 61, 0.25);
    }
    .nav-actions {
      display: flex; align-items: center; gap: 0.8rem;
    }
    .btn {
      font-family: 'Space Grotesk', sans-serif;
      font-size: 0.85rem; font-weight: 500;
      padding: 0.55rem 1rem; border-radius: 6px;
      display: inline-flex; align-items: center; gap: 0.45rem;
      cursor: pointer; text-decoration: none;
      border: 1px solid var(--card-border);
      transition: all 0.2s ease;
      background: transparent; color: var(--text);
    }
    .btn:hover {
      border-color: var(--amber); color: var(--amber);
    }
    .btn-primary {
      background: var(--amber); color: #121814;
      border-color: var(--amber); font-weight: 600;
    }
    .btn-primary:hover {
      background: var(--amber-hover); color: #0d120f;
      transform: translateY(-1px);
    }
    .btn-danger {
      color: var(--danger); border-color: rgba(224, 108, 117, 0.3);
    }
    .btn-danger:hover {
      background: var(--danger-dim); border-color: var(--danger);
    }

    /* Main Container */
    main.portal-main {
      position: relative; z-index: 1;
      padding: 2.2rem 6vw 5rem;
      flex: 1; max-width: 1350px; margin: 0 auto; width: 100%;
    }

    /* Stats Ribbon */
    .stats-ribbon {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.2rem;
      margin-bottom: 2.5rem;
    }
    .stat-card {
      background: var(--card);
      border: 1px solid var(--card-border);
      border-radius: 10px;
      padding: 1.25rem 1.4rem;
      display: flex; flex-direction: column;
      position: relative; overflow: hidden;
    }
    .stat-card::after {
      content: ""; position: absolute;
      left: 0; top: 0; bottom: 0; width: 3px;
      background: var(--stat-color, var(--amber));
    }
    .stat-val {
      font-family: 'Fraunces', serif;
      font-size: 2.1rem; font-weight: 500;
      color: var(--text); line-height: 1.1;
    }
    .stat-name {
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.72rem; letter-spacing: 0.08em;
      text-transform: uppercase; color: var(--text-dim);
      margin-top: 0.4rem;
    }

    /* Tabs Bar */
    .tabs-bar {
      display: flex; gap: 0.5rem;
      border-bottom: 1px solid var(--line);
      margin-bottom: 1.8rem;
      overflow-x: auto;
      scrollbar-width: none;
    }
    .tab-btn {
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.8rem; letter-spacing: 0.04em;
      padding: 0.75rem 1.2rem;
      border: none; background: none; color: var(--text-dim);
      border-bottom: 2px solid transparent;
      cursor: pointer; display: flex; align-items: center; gap: 0.5rem;
      transition: all 0.2s ease; white-space: nowrap;
    }
    .tab-btn:hover { color: var(--text); }
    .tab-btn.active {
      color: var(--amber); border-bottom-color: var(--amber);
      font-weight: 600;
    }
    .tab-count {
      font-size: 0.68rem; padding: 0.15rem 0.45rem;
      border-radius: 100px; background: rgba(232, 228, 216, 0.08);
      color: var(--text-dim);
    }
    .tab-btn.active .tab-count {
      background: var(--amber-dim); color: var(--amber);
    }

    /* Sections */
    .section-content { display: none; }
    .section-content.active { display: block; }

    /* Actions Header */
    .controls-header {
      display: flex; justify-content: space-between; align-items: center;
      gap: 1rem; flex-wrap: wrap; margin-bottom: 1.4rem;
    }
    .search-filter-row {
      display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
    }
    .search-input {
      background: #111713;
      border: 1px solid var(--card-border);
      border-radius: 6px;
      padding: 0.55rem 0.9rem;
      color: var(--text); font-family: inherit; font-size: 0.88rem;
      min-width: 240px; outline: none;
    }
    .search-input:focus { border-color: var(--amber); }
    .filter-select {
      background: #111713;
      border: 1px solid var(--card-border);
      border-radius: 6px;
      padding: 0.55rem 0.8rem;
      color: var(--text); font-family: inherit; font-size: 0.85rem;
      outline: none; cursor: pointer;
    }
    .filter-select:focus { border-color: var(--amber); }

    /* Task Table */
    .table-container {
      background: var(--card);
      border: 1px solid var(--card-border);
      border-radius: 10px;
      overflow: hidden;
    }
    table.data-table {
      width: 100%; border-collapse: collapse; text-align: left;
    }
    table.data-table th {
      background: rgba(0, 0, 0, 0.25);
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.72rem; letter-spacing: 0.08em;
      text-transform: uppercase; color: var(--text-dim);
      padding: 0.85rem 1.1rem;
      border-bottom: 1px solid var(--line);
    }
    table.data-table td {
      padding: 0.9rem 1.1rem;
      border-bottom: 1px solid var(--line);
      font-size: 0.9rem; vertical-align: middle;
    }
    table.data-table tr:last-child td { border-bottom: none; }
    table.data-table tr:hover td { background: rgba(255, 255, 255, 0.02); }

    /* Course Pill & Badges */
    .course-pill {
      display: inline-flex; align-items: center; gap: 0.45rem;
      font-family: 'JetBrains Mono', monospace; font-size: 0.72rem;
      padding: 0.25rem 0.65rem; border-radius: 100px;
      font-weight: 500;
    }
    .status-badge {
      font-family: 'JetBrains Mono', monospace; font-size: 0.7rem;
      padding: 0.22rem 0.55rem; border-radius: 100px;
      display: inline-flex; align-items: center; gap: 0.35rem;
    }
    .status-badge.done {
      background: var(--done-dim); color: var(--done);
    }
    .status-badge.pending {
      background: rgba(227, 161, 61, 0.12); color: var(--amber);
    }
    .status-badge.urgent {
      background: rgba(224, 108, 117, 0.15); color: #ff9c8f;
    }

    .done-toggle-btn {
      width: 24px; height: 24px; border-radius: 6px;
      border: 1.5px solid var(--card-border);
      background: transparent; color: transparent;
      display: inline-flex; align-items: center; justify-content: center;
      cursor: pointer; transition: all 0.2s ease;
      font-size: 0.75rem;
    }
    .done-toggle-btn:hover { border-color: var(--amber); }
    .done-toggle-btn.is-checked {
      background: var(--done); border-color: var(--done); color: #0c110e;
    }

    /* Course Grid */
    .courses-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1.25rem;
    }
    .course-item-card {
      background: var(--card);
      border: 1px solid var(--card-border);
      border-radius: 10px; padding: 1.35rem;
      display: flex; flex-direction: column; gap: 1rem;
      border-left: 4px solid var(--c-color);
      transition: transform 0.2s ease;
    }
    .course-item-card:hover { transform: translateY(-2px); }
    .course-card-top {
      display: flex; justify-content: space-between; align-items: flex-start;
    }
    .course-code-badge {
      font-family: 'JetBrains Mono', monospace; font-size: 0.75rem;
      color: var(--c-color); background: var(--c-dim);
      padding: 0.2rem 0.5rem; border-radius: 4px;
      letter-spacing: 0.05em; text-transform: uppercase;
    }
    .course-name-text {
      font-size: 1.1rem; font-weight: 600; color: var(--text);
      margin-top: 0.4rem;
    }

    /* Settings Form */
    .settings-panel {
      background: var(--card);
      border: 1px solid var(--card-border);
      border-radius: 10px; padding: 2rem;
      max-width: 680px;
    }
    .form-group {
      margin-bottom: 1.4rem;
    }
    .form-group label {
      display: block; font-family: 'JetBrains Mono', monospace;
      font-size: 0.76rem; letter-spacing: 0.08em;
      text-transform: uppercase; color: var(--text-dim);
      margin-bottom: 0.45rem;
    }
    .form-control {
      width: 100%; background: #111713;
      border: 1px solid var(--card-border);
      border-radius: 6px; padding: 0.75rem 0.95rem;
      color: var(--text); font-family: inherit; font-size: 0.92rem;
      outline: none; transition: border-color 0.2s ease;
    }
    .form-control:focus { border-color: var(--amber); }
    textarea.form-control { min-height: 85px; resize: vertical; }

    /* Modal Backdrop & Window */
    .modal-backdrop {
      position: fixed; inset: 0; background: rgba(0, 0, 0, 0.75);
      backdrop-filter: blur(5px); z-index: 999;
      display: none; align-items: center; justify-content: center;
      padding: 1.5rem;
    }
    .modal-backdrop.open { display: flex; }
    .modal-dialog {
      background: var(--card);
      border: 1px solid var(--card-border);
      border-radius: 12px; width: 100%; max-width: 500px;
      padding: 1.8rem; box-shadow: 0 25px 60px rgba(0,0,0,0.6);
      position: relative;
      animation: modalPop 0.25s ease-out;
    }
    @keyframes modalPop {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
    .modal-header {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 1.4rem;
    }
    .modal-title {
      font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 500;
      color: var(--text);
    }
    .modal-close {
      background: none; border: none; color: var(--text-dim);
      font-size: 1.3rem; cursor: pointer; padding: 0.2rem;
    }
    .modal-close:hover { color: var(--text); }
    .modal-footer {
      display: flex; justify-content: flex-end; gap: 0.65rem;
      margin-top: 1.6rem;
    }

    /* Toast Notification */
    .toast-container {
      position: fixed; bottom: 24px; right: 24px; z-index: 10000;
      display: flex; flex-direction: column; gap: 0.6rem;
    }
    .toast-msg {
      background: #17221b;
      border: 1px solid var(--amber);
      color: var(--text);
      border-radius: 8px;
      padding: 0.8rem 1.2rem;
      font-family: 'JetBrains Mono', monospace; font-size: 0.8rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
      animation: toastIn 0.3s ease;
      display: flex; align-items: center; gap: 0.6rem;
    }
    .toast-msg.error {
      border-color: var(--danger);
    }
    @keyframes toastIn {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
      header.portal-nav { padding: 0.9rem 1.2rem; }
      main.portal-main { padding: 1.5rem 1.2rem 4rem; }
      .search-input { width: 100%; min-width: 0; }
      .table-container { overflow-x: auto; }
      table.data-table { min-width: 620px; }
    }
  </style>
</head>
<body>

<!-- Nav -->
<header class="portal-nav">
  <div class="nav-brand">
    <span class="brand-dot"></span>
    <span class="brand-title">Maut Ka Khel</span>
    <span class="brand-tag">Control Portal</span>
  </div>

  <div class="nav-actions">
    <a href="../index.php" target="_blank" class="btn" title="Open public view in new tab">
      <span>👁️</span>
      <span>View Website</span>
    </a>
    <div style="font-family:'JetBrains Mono',monospace; font-size:0.75rem; color:var(--text-dim); margin:0 0.4rem;">
      Logged in as: <strong style="color:var(--amber);"><?= htmlspecialchars($username) ?></strong>
    </div>
    <a href="logout.php" class="btn btn-danger" title="Logout">
      <span>🚪</span>
      <span>Logout</span>
    </a>
  </div>
</header>

<main class="portal-main">

  <!-- Stats Ribbon -->
  <div class="stats-ribbon">
    <div class="stat-card" style="--stat-color: var(--amber);">
      <div class="stat-val" id="statRemainingCount"><?= $pendingTasks ?></div>
      <div class="stat-name">Remaining Deadlines</div>
    </div>
    <div class="stat-card" style="--stat-color: var(--done);">
      <div class="stat-val" id="statCompletedCount"><?= $doneTasks ?></div>
      <div class="stat-name">Completed Deadlines</div>
    </div>
    <div class="stat-card" style="--stat-color: #3fa796;">
      <div class="stat-val" id="statTotalCount"><?= $totalTasks ?></div>
      <div class="stat-name">Total Deadlines</div>
    </div>
    <div class="stat-card" style="--stat-color: #8f7fe8;">
      <div class="stat-val" id="statCoursesCount"><?= $totalCourses ?></div>
      <div class="stat-name">Total Courses</div>
    </div>
  </div>

  <!-- Tabs Navigation -->
  <div class="tabs-bar">
    <button class="tab-btn active" data-tab="tab-tasks">
      <span>📋 Deadlines & Routine</span>
      <span class="tab-count" id="taskTabBadge"><?= $totalTasks ?></span>
    </button>
    <button class="tab-btn" data-tab="tab-courses">
      <span>🏷️ Course Management</span>
      <span class="tab-count" id="courseTabBadge"><?= $totalCourses ?></span>
    </button>
    <button class="tab-btn" data-tab="tab-settings">
      <span>⚙️ Site Settings</span>
    </button>
    <button class="tab-btn" data-tab="tab-security">
      <span>🔒 Password & Account</span>
    </button>
  </div>

  <!-- TAB 1: Tasks / Deadlines -->
  <section id="tab-tasks" class="section-content active">
    <div class="controls-header">
      <div class="search-filter-row">
        <input type="text" id="taskSearch" class="search-input" placeholder="🔍 Search deadline or course...">
        
        <select id="courseFilter" class="filter-select">
          <option value="">All Courses</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)</option>
          <?php endforeach; ?>
        </select>

        <select id="statusFilter" class="filter-select">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="done">Completed (Done)</option>
        </select>
      </div>

      <button class="btn btn-primary" onclick="openAddTaskModal()">
        <span>➕ Add New Deadline</span>
      </button>
    </div>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 50px;">Done</th>
            <th>Due Date</th>
            <th>Course</th>
            <th>Task Title</th>
            <th>Status</th>
            <th style="text-align: right; width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody id="tasksTableBody">
          <!-- Populated by JavaScript -->
        </tbody>
      </table>
    </div>
  </section>

  <!-- TAB 2: Courses -->
  <section id="tab-courses" class="section-content">
    <div class="controls-header">
      <div>
        <h2 style="font-family:'Fraunces',serif; font-size:1.4rem;">Courses & Categories</h2>
        <p style="font-size:0.85rem; color:var(--text-dim);">Add new courses, set unique badge colors, and manage course categories.</p>
      </div>
      <button class="btn btn-primary" onclick="openAddCourseModal()">
        <span>➕ Add New Course</span>
      </button>
    </div>

    <div class="courses-grid" id="coursesGrid">
      <!-- Populated by JavaScript -->
    </div>
  </section>

  <!-- TAB 3: Site Settings -->
  <section id="tab-settings" class="section-content">
    <div class="settings-panel">
      <h2 style="font-family:'Fraunces',serif; font-size:1.4rem; margin-bottom: 0.4rem;">Website Settings</h2>
      <p style="font-size:0.85rem; color:var(--text-dim); margin-bottom: 1.8rem;">Customize the semester header, main headline, and visitor interaction permissions.</p>

      <form id="settingsForm" onsubmit="saveSettings(event)">
        <div class="form-group">
          <label for="eyebrowInput">Semester / Term Header (Eyebrow)</label>
          <input type="text" id="eyebrowInput" class="form-control" value="<?= htmlspecialchars($settings['eyebrow'] ?? 'Summer term · Aug – Sep 2026') ?>" placeholder="e.g. Summer term · Aug – Sep 2026">
        </div>

        <div class="form-group">
          <label for="heroTitleInput">Main Headline Title (HTML allowed, e.g. &lt;em&gt;)</label>
          <input type="text" id="heroTitleInput" class="form-control" value="<?= htmlspecialchars($settings['hero_title'] ?? 'Everything due,<br>in <em>one line</em>.') ?>" placeholder="e.g. Everything due,<br>in <em>one line</em>.">
        </div>

        <div class="form-group">
          <label for="heroSubtitleInput">Subtitle / Description</label>
          <input type="text" id="heroSubtitleInput" class="form-control" value="<?= htmlspecialchars($settings['hero_subtitle'] ?? 'Semester routine & deadline ledger tracker') ?>">
        </div>

        <div class="form-group">
          <label>Can visitors on the public website toggle task completion?</label>
          <div style="display:flex; gap:1.2rem; align-items:center; margin-top:0.4rem;">
            <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; text-transform:none; font-family:inherit; color:var(--text);">
              <input type="radio" name="allow_public_toggle" value="1" <?= ($settings['allow_public_toggle'] ?? '1') === '1' ? 'checked' : '' ?>>
              <span>Yes (public toggles save & sync for everyone)</span>
            </label>
            <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; text-transform:none; font-family:inherit; color:var(--text);">
              <input type="radio" name="allow_public_toggle" value="0" <?= ($settings['allow_public_toggle'] ?? '1') === '0' ? 'checked' : '' ?>>
              <span>No (controlled via Admin Portal only)</span>
            </label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top: 0.8rem;">
          <span>Save Settings</span>
        </button>
      </form>
    </div>
  </section>

  <!-- TAB 4: Security & Account -->
  <section id="tab-security" class="section-content">
    <div class="settings-panel">
      <h2 style="font-family:'Fraunces',serif; font-size:1.4rem; margin-bottom: 0.4rem;">Admin Credentials</h2>
      <p style="font-size:0.85rem; color:var(--text-dim); margin-bottom: 1.8rem;">Update your administrator username and login password.</p>

      <form id="securityForm" onsubmit="updateCredentials(event)">
        <div class="form-group">
          <label for="currentPassword">Current Password</label>
          <input type="password" id="currentPassword" class="form-control" required placeholder="Enter current password">
        </div>

        <div class="form-group">
          <label for="newUsername">New Username</label>
          <input type="text" id="newUsername" class="form-control" value="<?= htmlspecialchars($username) ?>" required>
        </div>

        <div class="form-group">
          <label for="newPassword">New Password (leave empty to keep current password)</label>
          <input type="password" id="newPassword" class="form-control" placeholder="Minimum 5 characters">
        </div>

        <div class="form-group">
          <label for="confirmNewPassword">Confirm New Password</label>
          <input type="password" id="confirmNewPassword" class="form-control" placeholder="Re-enter new password">
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top: 0.8rem;">
          <span>Update Account</span>
        </button>
      </form>
    </div>
  </section>

</main>

<!-- Modal: Add/Edit Task -->
<div class="modal-backdrop" id="taskModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title" id="taskModalTitle">Add New Deadline</h3>
      <button class="modal-close" onclick="closeModal('taskModal')">✕</button>
    </div>

    <form id="taskForm" onsubmit="handleTaskSubmit(event)">
      <input type="hidden" id="taskId" value="">

      <div class="form-group">
        <label for="taskCourseSelect">Select Course</label>
        <select id="taskCourseSelect" class="form-control" required>
          <!-- Options populated from JS -->
        </select>
      </div>

      <div class="form-group">
        <label for="taskTitleInput">Task / Deadline Title</label>
        <input type="text" id="taskTitleInput" class="form-control" placeholder="e.g. Lab report 3, Presentation, CT-2" required>
      </div>

      <div class="form-group">
        <label for="taskDateInput">Due Date</label>
        <input type="date" id="taskDateInput" class="form-control" required>
      </div>

      <div class="form-group" style="display:flex; align-items:center; gap:0.6rem; margin-top:0.4rem;">
        <input type="checkbox" id="taskDoneInput" style="width:18px; height:18px; accent-color:var(--amber);">
        <label for="taskDoneInput" style="margin:0; text-transform:none; cursor:pointer;">Task already completed (Mark as Done)</label>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('taskModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="taskModalSaveBtn">Save Deadline</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Add/Edit Course -->
<div class="modal-backdrop" id="courseModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title" id="courseModalTitle">Add New Course</h3>
      <button class="modal-close" onclick="closeModal('courseModal')">✕</button>
    </div>

    <form id="courseForm" onsubmit="handleCourseSubmit(event)">
      <input type="hidden" id="courseId" value="">

      <div class="form-group">
        <label for="courseCodeInput">Course Code / Short Slug (e.g. chem, ml, dc)</label>
        <input type="text" id="courseCodeInput" class="form-control" placeholder="e.g. cse301, ai, se" required>
      </div>

      <div class="form-group">
        <label for="courseNameInput">Course Full Name</label>
        <input type="text" id="courseNameInput" class="form-control" placeholder="e.g. Machine Learning Lab" required>
      </div>

      <div class="form-group">
        <label for="courseColorInput">Badge Accent Color</label>
        <div style="display:flex; gap:0.6rem; align-items:center;">
          <input type="color" id="courseColorInput" value="#e3a13d" style="width:50px; height:38px; border:none; background:none; cursor:pointer;">
          <input type="text" id="courseColorHex" class="form-control" value="#e3a13d" style="width:120px; font-family:'JetBrains Mono',monospace;">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('courseModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="courseModalSaveBtn">Save Course</button>
      </div>
    </form>
  </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// State
let allTasks = [];
let allCourses = [];
let coursesMap = {};

// Tabs functionality
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.section-content').forEach(s => s.classList.remove('active'));

    btn.classList.add('active');
    const targetId = btn.getAttribute('data-tab');
    document.getElementById(targetId).classList.add('active');
  });
});

function showToast(msg, isError = false) {
  const container = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast-msg' + (isError ? ' error' : '');
  t.innerHTML = `<span>${isError ? '❌' : '✓'}</span><span>${msg}</span>`;
  container.appendChild(t);
  setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateY(10px)';
    t.style.transition = 'all 0.3s ease';
    setTimeout(() => t.remove(), 300);
  }, 3500);
}

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

// Color picker sync
document.getElementById('courseColorInput').addEventListener('input', (e) => {
  document.getElementById('courseColorHex').value = e.target.value;
});
document.getElementById('courseColorHex').addEventListener('input', (e) => {
  if (/^#[0-9a-fA-F]{6}$/.test(e.target.value)) {
    document.getElementById('courseColorInput').value = e.target.value;
  }
});

// Load Data
async function loadData() {
  try {
    const [tasksRes, coursesRes] = await Promise.all([
      fetch('../api/tasks.php?action=list'),
      fetch('../api/courses.php?action=list')
    ]);

    const tasksData = await tasksRes.json();
    const coursesData = await coursesRes.json();

    if (tasksData.success) {
      allTasks = tasksData.tasks || [];
    }
    if (coursesData.success) {
      allCourses = coursesData.courses || [];
      coursesMap = coursesData.map || {};
    }

    updateCoursesUI();
    renderTasksTable();
    updateStatsRibbon();
  } catch (err) {
    console.error('Error fetching data:', err);
    showToast('Failed to load data!', true);
  }
}

function updateStatsRibbon() {
  const total = allTasks.length;
  const done = allTasks.filter(t => t.is_done).length;
  const remaining = total - done;

  document.getElementById('statTotalCount').textContent = total;
  document.getElementById('statCompletedCount').textContent = done;
  document.getElementById('statRemainingCount').textContent = remaining;
  document.getElementById('statCoursesCount').textContent = allCourses.length;

  document.getElementById('taskTabBadge').textContent = total;
  document.getElementById('courseTabBadge').textContent = allCourses.length;
}

function updateCoursesUI() {
  // Update Course Select in Task Modal
  const sel = document.getElementById('taskCourseSelect');
  const filterSel = document.getElementById('courseFilter');
  const currentFilter = filterSel.value;

  sel.innerHTML = '';
  filterSel.innerHTML = '<option value="">All Courses</option>';

  allCourses.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.code;
    opt.textContent = `${c.name} (${c.code})`;
    sel.appendChild(opt);

    const filterOpt = document.createElement('option');
    filterOpt.value = c.code;
    filterOpt.textContent = `${c.name} (${c.code})`;
    filterSel.appendChild(filterOpt);
  });
  filterSel.value = currentFilter;

  // Render Courses Grid
  const grid = document.getElementById('coursesGrid');
  grid.innerHTML = '';

  allCourses.forEach(c => {
    const count = allTasks.filter(t => t.course_code === c.code).length;
    const card = document.createElement('div');
    card.className = 'course-item-card';
    card.style.setProperty('--c-color', c.color);
    card.style.setProperty('--c-dim', c.dim_color || 'rgba(255,255,255,0.05)');

    card.innerHTML = `
      <div class="course-card-top">
        <span class="course-code-badge">${c.code}</span>
        <div style="display:flex; gap:0.4rem;">
          <button class="btn" style="padding:0.3rem 0.6rem; font-size:0.75rem;" onclick="openEditCourseModal(${c.id})">Edit</button>
          <button class="btn btn-danger" style="padding:0.3rem 0.6rem; font-size:0.75rem;" onclick="deleteCourse(${c.id}, '${c.code}')">Delete</button>
        </div>
      </div>
      <div>
        <div class="course-name-text">${escapeHtml(c.name)}</div>
        <div style="font-family:'JetBrains Mono',monospace; font-size:0.75rem; color:var(--text-dim); margin-top:0.3rem;">
          ${count} active task(s)
        </div>
      </div>
      <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.72rem; font-family:'JetBrains Mono',monospace; color:var(--text-dim); margin-top:auto;">
        <span style="width:14px; height:14px; border-radius:50%; background:${c.color}; display:inline-block;"></span>
        <span>${c.color}</span>
      </div>
    `;
    grid.appendChild(card);
  });
}

function renderTasksTable() {
  const tbody = document.getElementById('tasksTableBody');
  const search = document.getElementById('taskSearch').value.toLowerCase().trim();
  const courseFilter = document.getElementById('courseFilter').value;
  const statusFilter = document.getElementById('statusFilter').value;

  const filtered = allTasks.filter(t => {
    if (search) {
      const matchTitle = (t.title || '').toLowerCase().includes(search);
      const matchCourse = (t.course_name || t.course_code || '').toLowerCase().includes(search);
      if (!matchTitle && !matchCourse) return false;
    }
    if (courseFilter && t.course_code !== courseFilter) return false;
    if (statusFilter === 'pending' && t.is_done) return false;
    if (statusFilter === 'done' && !t.is_done) return false;
    return true;
  });

  if (filtered.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-dim);">No deadlines found</td></tr>`;
    return;
  }

  const today = new Date();
  today.setHours(0,0,0,0);

  tbody.innerHTML = filtered.map(t => {
    const course = coursesMap[t.course_code] || { name: t.course_code, color: '#e3a13d', dim: 'rgba(227,161,61,0.1)' };
    
    // Calculate days left
    const taskDate = new Date(t.deadline_date + 'T00:00:00');
    const diffDays = Math.round((taskDate - today) / (1000 * 60 * 60 * 24));

    let statusHtml = '';
    if (t.is_done) {
      statusHtml = `<span class="status-badge done">✓ Completed</span>`;
    } else if (diffDays < 0) {
      const pastDays = Math.abs(diffDays);
      statusHtml = `<span class="status-badge urgent">Overdue (${pastDays} day${pastDays === 1 ? '' : 's'} ago)</span>`;
    } else if (diffDays === 0) {
      statusHtml = `<span class="status-badge urgent">Due Today</span>`;
    } else if (diffDays === 1) {
      statusHtml = `<span class="status-badge urgent">Due Tomorrow (1 day left)</span>`;
    } else {
      statusHtml = `<span class="status-badge pending">${diffDays} days left</span>`;
    }

    return `
      <tr style="${t.is_done ? 'opacity: 0.65;' : ''}">
        <td>
          <button class="done-toggle-btn ${t.is_done ? 'is-checked' : ''}" onclick="toggleTaskDone(${t.id}, ${!t.is_done})" title="Toggle Done">✓</button>
        </td>
        <td style="font-family:'JetBrains Mono',monospace; font-size:0.85rem; white-space:nowrap;">
          <strong>${formatDate(t.deadline_date)}</strong>
        </td>
        <td>
          <span class="course-pill" style="background:${course.dim}; color:${course.color}; border:1px solid ${course.color}40;">
            <span style="width:6px; height:6px; border-radius:50%; background:${course.color};"></span>
            ${escapeHtml(course.name || t.course_code)}
          </span>
        </td>
        <td>
          <div style="font-weight:500; font-size:0.95rem; ${t.is_done ? 'text-decoration:line-through;' : ''}">${escapeHtml(t.title)}</div>
        </td>
        <td>${statusHtml}</td>
        <td style="text-align: right; white-space: nowrap;">
          <button class="btn" style="padding:0.35rem 0.65rem; font-size:0.75rem;" onclick="openEditTaskModal(${t.id})">Edit</button>
          <button class="btn btn-danger" style="padding:0.35rem 0.65rem; font-size:0.75rem;" onclick="deleteTask(${t.id})">Delete</button>
        </td>
      </tr>
    `;
  }).join('');
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const parts = dateStr.split('-');
  if (parts.length !== 3) return dateStr;
  const d = new Date(parts[0], parts[1] - 1, parts[2]);
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return `${d.getDate()} ${months[d.getMonth()]}, ${d.getFullYear()}`;
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>'"]/g, 
    tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
  );
}

// Filter listeners
document.getElementById('taskSearch').addEventListener('input', renderTasksTable);
document.getElementById('courseFilter').addEventListener('change', renderTasksTable);
document.getElementById('statusFilter').addEventListener('change', renderTasksTable);

// Task Modal Handlers
function openAddTaskModal() {
  document.getElementById('taskModalTitle').textContent = 'Add New Deadline';
  document.getElementById('taskId').value = '';
  document.getElementById('taskTitleInput').value = '';
  document.getElementById('taskDateInput').value = new Date().toISOString().split('T')[0];
  document.getElementById('taskDoneInput').checked = false;
  if (allCourses.length > 0) {
    document.getElementById('taskCourseSelect').value = allCourses[0].code;
  }
  document.getElementById('taskModal').classList.add('open');
}

function openEditTaskModal(id) {
  const t = allTasks.find(item => item.id === id);
  if (!t) return;

  document.getElementById('taskModalTitle').textContent = 'Edit Deadline';
  document.getElementById('taskId').value = t.id;
  document.getElementById('taskCourseSelect').value = t.course_code;
  document.getElementById('taskTitleInput').value = t.title;
  document.getElementById('taskDateInput').value = t.deadline_date;
  document.getElementById('taskDoneInput').checked = !!t.is_done;

  document.getElementById('taskModal').classList.add('open');
}

async function handleTaskSubmit(e) {
  e.preventDefault();
  const id = document.getElementById('taskId').value;
  const course_code = document.getElementById('taskCourseSelect').value;
  const title = document.getElementById('taskTitleInput').value.trim();
  const deadline_date = document.getElementById('taskDateInput').value;
  const is_done = document.getElementById('taskDoneInput').checked ? 1 : 0;

  const action = id ? 'update' : 'create';
  const payload = { action, id, course_code, title, deadline_date, is_done };

  try {
    const res = await fetch('../api/tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await res.json();
    if (result.success) {
      showToast(result.message || 'Saved successfully!');
      closeModal('taskModal');
      await loadData();
    } else {
      showToast(result.message || 'An error occurred!', true);
    }
  } catch (err) {
    showToast('Server is not responding!', true);
  }
}

async function toggleTaskDone(id, is_done) {
  try {
    const res = await fetch('../api/tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle_done', id, is_done: is_done ? 1 : 0 })
    });
    const result = await res.json();
    if (result.success) {
      const task = allTasks.find(t => t.id === id);
      if (task) task.is_done = is_done;
      renderTasksTable();
      updateStatsRibbon();
      showToast(is_done ? 'Task marked as completed' : 'Task marked as pending');
    }
  } catch (err) {
    showToast('Failed to update status!', true);
  }
}

async function deleteTask(id) {
  if (!confirm('Are you sure you want to delete this deadline?')) return;
  try {
    const res = await fetch('../api/tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', id })
    });
    const result = await res.json();
    if (result.success) {
      showToast('Deadline deleted successfully!');
      await loadData();
    } else {
      showToast(result.message, true);
    }
  } catch (err) {
    showToast('Failed to delete deadline!', true);
  }
}

// Course Modal Handlers
function openAddCourseModal() {
  document.getElementById('courseModalTitle').textContent = 'Add New Course';
  document.getElementById('courseId').value = '';
  document.getElementById('courseCodeInput').value = '';
  document.getElementById('courseCodeInput').disabled = false;
  document.getElementById('courseNameInput').value = '';
  document.getElementById('courseColorInput').value = '#e3a13d';
  document.getElementById('courseColorHex').value = '#e3a13d';
  document.getElementById('courseModal').classList.add('open');
}

function openEditCourseModal(id) {
  const c = allCourses.find(item => item.id === id);
  if (!c) return;

  document.getElementById('courseModalTitle').textContent = 'Edit Course';
  document.getElementById('courseId').value = c.id;
  document.getElementById('courseCodeInput').value = c.code;
  document.getElementById('courseCodeInput').disabled = false;
  document.getElementById('courseNameInput').value = c.name;
  document.getElementById('courseColorInput').value = c.color;
  document.getElementById('courseColorHex').value = c.color;

  document.getElementById('courseModal').classList.add('open');
}

async function handleCourseSubmit(e) {
  e.preventDefault();
  const id = document.getElementById('courseId').value;
  const code = document.getElementById('courseCodeInput').value.trim();
  const name = document.getElementById('courseNameInput').value.trim();
  const color = document.getElementById('courseColorHex').value.trim();

  const action = id ? 'update' : 'create';
  const payload = { action, id, code, name, color };

  try {
    const res = await fetch('../api/courses.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await res.json();
    if (result.success) {
      showToast(result.message || 'Course saved successfully!');
      closeModal('courseModal');
      await loadData();
    } else {
      showToast(result.message, true);
    }
  } catch (err) {
    showToast('Server error!', true);
  }
}

async function deleteCourse(id, code) {
  const count = allTasks.filter(t => t.course_code === code).length;
  let force = false;
  if (count > 0) {
    if (!confirm(`Warning: There are ${count} active deadline(s) linked to this course. Deleting this course will also delete all its deadlines. Are you sure?`)) {
      return;
    }
    force = true;
  } else {
    if (!confirm('Are you sure you want to delete this course?')) return;
  }

  try {
    const res = await fetch('../api/courses.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', id, force })
    });
    const result = await res.json();
    if (result.success) {
      showToast('Course deleted successfully!');
      await loadData();
    } else {
      showToast(result.message, true);
    }
  } catch (err) {
    showToast('Failed to delete course!', true);
  }
}

// Settings Handlers
async function saveSettings(e) {
  e.preventDefault();
  const eyebrow = document.getElementById('eyebrowInput').value.trim();
  const hero_title = document.getElementById('heroTitleInput').value.trim();
  const hero_subtitle = document.getElementById('heroSubtitleInput').value.trim();
  const allow_public_toggle = document.querySelector('input[name="allow_public_toggle"]:checked').value;

  try {
    const res = await fetch('../api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update',
        eyebrow,
        hero_title,
        hero_subtitle,
        allow_public_toggle
      })
    });
    const result = await res.json();
    if (result.success) {
      showToast('Website settings saved successfully!');
    } else {
      showToast(result.message, true);
    }
  } catch (err) {
    showToast('Failed to save settings!', true);
  }
}

// Security / Password Handlers
async function updateCredentials(e) {
  e.preventDefault();
  const current_password = document.getElementById('currentPassword').value;
  const new_username = document.getElementById('newUsername').value.trim();
  const new_password = document.getElementById('newPassword').value;
  const confirm = document.getElementById('confirmNewPassword').value;

  if (new_password && new_password !== confirm) {
    showToast('New passwords do not match!', true);
    return;
  }

  try {
    const res = await fetch('../api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_credentials',
        current_password,
        new_username,
        new_password
      })
    });
    const result = await res.json();
    if (result.success) {
      showToast(result.message);
      document.getElementById('currentPassword').value = '';
      document.getElementById('newPassword').value = '';
      document.getElementById('confirmNewPassword').value = '';
    } else {
      showToast(result.message, true);
    }
  } catch (err) {
    showToast('Failed to update password!', true);
  }
}

// Init
loadData();
</script>

</body>
</html>
