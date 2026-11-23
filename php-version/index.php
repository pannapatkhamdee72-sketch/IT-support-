<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
$currentUser = requireLogin();
$canManageUsers = ($currentUser['role'] ?? '') === 'admin';
$canUpdateComputers = in_array($currentUser['role'] ?? '', ['admin', 'operator'], true);

$initialComputers = [
    ['id' => 'pc-001', 'name' => 'BKK-OFFICE-001', 'status' => 'remaining'],
    ['id' => 'pc-002', 'name' => 'BKK-OFFICE-002', 'status' => 'remaining'],
    ['id' => 'pc-003', 'name' => 'BKK-OFFICE-003', 'status' => 'remaining'],
    ['id' => 'pc-004', 'name' => 'BKK-OFFICE-004', 'status' => 'remaining'],
    ['id' => 'pc-005', 'name' => 'BKK-OFFICE-005', 'status' => 'remaining'],
    ['id' => 'pc-006', 'name' => 'BKK-OFFICE-006', 'status' => 'progress'],
    ['id' => 'pc-007', 'name' => 'BKK-OFFICE-007', 'status' => 'progress'],
    ['id' => 'pc-008', 'name' => 'BKK-OFFICE-008', 'status' => 'progress'],
    ['id' => 'pc-009', 'name' => 'BKK-OFFICE-009', 'status' => 'completed'],
    ['id' => 'pc-010', 'name' => 'BKK-OFFICE-010', 'status' => 'completed'],
    ['id' => 'pc-011', 'name' => 'BKK-OFFICE-011', 'status' => 'completed'],
    ['id' => 'pc-012', 'name' => 'BKK-OFFICE-012', 'status' => 'completed'],
];

if (!isset($_SESSION['computers']) || !is_array($_SESSION['computers'])) {
    $_SESSION['computers'] = $initialComputers;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrfToken()) {
        http_response_code(400);
        $error = 'This request could not be verified. Reload the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'reset' && $canManageUsers) {
            $_SESSION['computers'] = $initialComputers;
            $_SESSION['notice'] = 'Demo data has been reset.';
            header('Location: index.php', true, 303);
            exit;
        }

        if ($action === 'complete' && $canUpdateComputers) {
            $computerId = $_POST['computer_id'] ?? '';
            $computerIndex = null;
            foreach ($_SESSION['computers'] as $index => $computer) {
                if (is_array($computer) && ($computer['id'] ?? null) === $computerId) {
                    $computerIndex = $index;
                    break;
                }
            }

            if ($computerIndex !== null) {
                $_SESSION['computers'][$computerIndex]['status'] = 'completed';
                $_SESSION['notice'] = 'Update marked as completed for ' . $_SESSION['computers'][$computerIndex]['name'] . '.';
                header('Location: index.php', true, 303);
                exit;
            }
        }

        if (($action === 'reset' && !$canManageUsers) || ($action === 'complete' && !$canUpdateComputers)) {
            http_response_code(403);
            $error = 'Your account does not have permission to perform this action.';
        } else {
            http_response_code(400);
            $error = 'The requested demo action is invalid.';
        }
    }
}

if (isset($_SESSION['notice']) && is_string($_SESSION['notice'])) {
    $notice = $_SESSION['notice'];
    unset($_SESSION['notice']);
}

$computers = $_SESSION['computers'];
$counts = ['remaining' => 0, 'progress' => 0, 'completed' => 0];
foreach ($computers as $computer) {
    if (isset($counts[$computer['status'] ?? ''])) {
        $counts[$computer['status']]++;
    }
}

$total = count($computers);
$completionRate = $total === 0 ? 0 : (int) round(($counts['completed'] / $total) * 100);
$statusLabels = [
    'remaining' => 'Remaining',
    'progress' => 'In progress',
    'completed' => 'Completed',
];
$lastUpdated = date('M j, Y · H:i:s');
$csrfToken = csrfToken();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f7fb">
    <title>IT Support | PHP Dashboard Demo</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="php.css">
  </head>
  <body>
    <main class="page-shell">
      <header class="topbar">
        <a class="brand" href="index.php" aria-label="PHP demo dashboard home">
          <span class="brand-mark" aria-hidden="true">IT</span>
          <span class="brand-name">IT Support <span>PHP Demo</span></span>
        </a>
        <div class="topbar-meta">
          <span class="live-dot demo" aria-hidden="true"></span>
          <span><?= e($currentUser['username']) ?> · <?= e(USER_ROLES[$currentUser['role']] ?? 'User') ?></span>
          <?php if ($canManageUsers): ?>
          <a class="header-link" href="users.php">Manage users</a>
          <?php endif; ?>
          <form method="post" action="logout.php" class="logout-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <button class="button button-secondary button-small" type="submit">Log out</button>
          </form>
        </div>
      </header>

      <section class="page-heading" aria-labelledby="page-title">
        <div>
          <p class="eyebrow">SERVER-SIDE RENDERING</p>
          <h1 id="page-title">Computer update status</h1>
          <p class="page-description">PHP renders this page and processes demo actions on the server.</p>
        </div>
        <?php if ($canManageUsers): ?>
        <form class="reset-form" method="post">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <button class="button button-secondary" type="submit" name="action" value="reset">Reset demo data</button>
        </form>
        <?php endif; ?>
      </section>

      <?php if ($notice !== ''): ?>
      <section class="php-notice" role="status"><?= e($notice) ?></section>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
      <section class="error-banner" role="alert">
        <span class="error-icon" aria-hidden="true">!</span>
        <div><strong>Unable to process request</strong><p><?= e($error) ?></p></div>
      </section>
      <?php endif; ?>

      <section class="summary-section" aria-labelledby="summary-title">
        <div class="section-heading">
          <div>
            <h2 id="summary-title">Update summary</h2>
            <p>Counts calculated by PHP from session demo data</p>
          </div>
          <span class="last-updated">Rendered <?= e($lastUpdated) ?></span>
        </div>
        <div class="summary-grid">
          <article class="summary-card card-remaining">
            <div class="card-topline"><span class="card-label">Remaining</span><span class="card-icon" aria-hidden="true">!</span></div>
            <p class="card-caption">Not updated yet</p>
            <p class="card-value"><?= number_format($counts['remaining']) ?></p>
            <p class="card-unit">computers</p>
          </article>
          <article class="summary-card card-progress">
            <div class="card-topline"><span class="card-label">In progress</span><span class="card-icon" aria-hidden="true">↻</span></div>
            <p class="card-caption">Currently updating</p>
            <p class="card-value"><?= number_format($counts['progress']) ?></p>
            <p class="card-unit">computers</p>
          </article>
          <article class="summary-card card-completed">
            <div class="card-topline"><span class="card-label">Completed</span><span class="card-icon" aria-hidden="true">✓</span></div>
            <p class="card-caption">Update finished</p>
            <p class="card-value"><?= number_format($counts['completed']) ?></p>
            <p class="card-unit">computers</p>
          </article>
          <article class="summary-card card-total">
            <div class="card-topline"><span class="card-label">Total computers</span><span class="card-icon" aria-hidden="true">▦</span></div>
            <p class="card-caption">All demo devices</p>
            <p class="card-value"><?= number_format($total) ?></p>
            <p class="card-unit">computers</p>
          </article>
        </div>
      </section>

      <section class="powerbi-section" aria-labelledby="report-title">
        <div class="section-heading powerbi-heading">
          <div>
            <p class="eyebrow">BUSINESS INTELLIGENCE</p>
            <h2 id="report-title">Power BI-style report preview</h2>
            <p>Server-rendered report example using the same session data</p>
          </div>
          <span class="report-source"><span class="report-source-dot" aria-hidden="true"></span>PHP session data</span>
        </div>
        <div class="report-preview">
          <div class="report-toolbar">
            <span class="powerbi-mark" aria-hidden="true">▥</span>
            <span>Computer Update Overview</span>
            <span class="report-toolbar-label">PHP REPORT PREVIEW</span>
          </div>
          <div class="report-content">
            <div class="report-kpis">
              <article class="report-kpi"><span>Total computers</span><strong><?= number_format($total) ?></strong></article>
              <article class="report-kpi"><span>Update completion</span><strong><?= $completionRate ?>%</strong></article>
              <article class="report-kpi"><span>Remaining</span><strong><?= number_format($counts['remaining']) ?></strong></article>
            </div>
            <div class="report-chart-grid">
              <div class="report-chart">
                <div class="chart-heading"><strong>Computers by update status</strong><span>Current snapshot</span></div>
                <div class="bar-chart" role="img" aria-label="Computer counts by update status">
                  <?php foreach (['completed', 'progress', 'remaining'] as $status): ?>
                  <?php $percentage = $total === 0 ? 0 : ($counts[$status] / $total) * 100; ?>
                  <div class="bar-row">
                    <span class="bar-label"><?= e($statusLabels[$status]) ?></span>
                    <div class="bar-track"><span class="bar-fill bar-fill-<?= e($status) ?>" style="width: <?= e((string) $percentage) ?>%"></span></div>
                    <strong><?= number_format($counts[$status]) ?></strong>
                  </div>
                  <?php endforeach; ?>
                </div>
                <div class="chart-legend">
                  <span><i class="legend-dot legend-completed"></i>Completed</span>
                  <span><i class="legend-dot legend-progress"></i>In progress</span>
                  <span><i class="legend-dot legend-remaining"></i>Remaining</span>
                </div>
              </div>
              <div class="report-chart completion-chart">
                <div class="chart-heading"><strong>Completion rate</strong><span>Completed / total</span></div>
                <div class="completion-visual">
                  <div class="completion-ring" style="--completion: <?= $completionRate ?>%">
                    <div class="completion-ring-center"><strong><?= $completionRate ?>%</strong><span>complete</span></div>
                  </div>
                  <p>Computers with completed updates</p>
                </div>
              </div>
            </div>
          </div>
          <p class="report-disclaimer">Demo only — the report is rendered by PHP and is not an embedded Power BI Service report.</p>
        </div>
      </section>

      <section class="devices-section" aria-labelledby="devices-title">
        <div class="section-heading device-heading">
          <div><h2 id="devices-title">Computer list</h2><p><?= $canUpdateComputers ? 'Use an action to update the demo status' : 'Your account has read-only access' ?></p></div>
          <span class="device-count"><?= number_format($total) ?> devices</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th scope="col">Computer</th><th scope="col">Update status</th><?php if ($canUpdateComputers): ?><th scope="col">Demo action</th><?php endif; ?></tr>
            </thead>
            <tbody>
              <?php foreach ($computers as $computer): ?>
              <?php $status = $computer['status']; ?>
              <tr>
                <td><span class="computer-name"><?= e($computer['name']) ?></span></td>
                <td><span class="status-badge status-<?= e($status) ?>"><?= e($statusLabels[$status]) ?></span></td>
                <?php if ($canUpdateComputers): ?><td>
                  <?php if ($status !== 'completed'): ?>
                  <form method="post" class="row-action">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="computer_id" value="<?= e($computer['id']) ?>">
                    <button class="button button-small" type="submit" name="action" value="complete">Mark completed</button>
                  </form>
                  <?php else: ?>
                  <span class="already-complete">Done</span>
                  <?php endif; ?>
                </td><?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <footer class="page-footer">PHP renders the HTML on each request. Demo status is stored in this browser session; no real computers are changed.</footer>
    </main>
  </body>
</html>
