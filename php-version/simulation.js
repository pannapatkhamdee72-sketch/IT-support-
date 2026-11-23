const storageKey = "it-support.php-simulation.v1";
const initialComputers = [
  ["pc-001", "BKK-OFFICE-001", "remaining"],
  ["pc-002", "BKK-OFFICE-002", "remaining"],
  ["pc-003", "BKK-OFFICE-003", "remaining"],
  ["pc-004", "BKK-OFFICE-004", "remaining"],
  ["pc-005", "BKK-OFFICE-005", "remaining"],
  ["pc-006", "BKK-OFFICE-006", "progress"],
  ["pc-007", "BKK-OFFICE-007", "progress"],
  ["pc-008", "BKK-OFFICE-008", "progress"],
  ["pc-009", "BKK-OFFICE-009", "completed"],
  ["pc-010", "BKK-OFFICE-010", "completed"],
  ["pc-011", "BKK-OFFICE-011", "completed"],
  ["pc-012", "BKK-OFFICE-012", "completed"],
].map(([id, name, status]) => ({ id, name, status }));
const roleLabels = { admin: "Admin", operator: "Operator", viewer: "Viewer" };
const statusLabels = { remaining: "Remaining", progress: "In progress", completed: "Completed" };
const app = document.querySelector("#app");
let state;
let currentRoute = "dashboard";
let errorMessage = "";
let noticeMessage = "";

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (character) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[character]);
}

function saveState() {
  try {
    localStorage.setItem(storageKey, JSON.stringify(state));
    return true;
  } catch (error) {
    errorMessage = `Could not save simulation data in this browser: ${error.message}`;
    return false;
  }
}

function loadState() {
  const saved = localStorage.getItem(storageKey);
  if (saved === null) {
    return { users: [], computers: initialComputers, currentUserId: null };
  }

  const parsed = JSON.parse(saved);
  if (
    !parsed || !Array.isArray(parsed.users) || !Array.isArray(parsed.computers)
    || !(parsed.currentUserId === null || typeof parsed.currentUserId === "string")
  ) {
    throw new Error("Saved simulation data has an unexpected format. Clear this site's local storage to restart.");
  }
  return parsed;
}

function currentUser() {
  return state.users.find((user) => user.id === state.currentUserId) || null;
}

function setRoute(route) {
  currentRoute = route;
  const hash = `#${route}`;
  if (location.hash !== hash) history.replaceState(null, "", hash);
}

function countStatuses() {
  return state.computers.reduce((counts, computer) => {
    if (Object.hasOwn(counts, computer.status)) counts[computer.status] += 1;
    return counts;
  }, { remaining: 0, progress: 0, completed: 0 });
}

function showNotice(message) {
  noticeMessage = message;
  errorMessage = "";
}

function showError(message) {
  errorMessage = message;
  noticeMessage = "";
}

function alertMarkup() {
  if (errorMessage) {
    return `<div class="auth-error" role="alert">${escapeHtml(errorMessage)}</div>`;
  }
  if (noticeMessage) {
    return `<div class="php-notice" role="status">${escapeHtml(noticeMessage)}</div>`;
  }
  return "";
}

function renderAuth() {
  const isSetup = state.users.length === 0;
  app.innerHTML = `
    <main class="auth-shell">
      <a class="brand auth-brand" href="#login">
        <span class="brand-mark" aria-hidden="true">IT</span>
        <span class="brand-name">IT Support <span>PHP Simulation</span></span>
      </a>
      <section class="auth-card" aria-labelledby="auth-title">
        <p class="eyebrow">${isSetup ? "INITIAL SETUP" : "SIMULATED SIGN IN"}</p>
        <h1 id="auth-title">${isSetup ? "Create admin account" : "Welcome back"}</h1>
        <p class="auth-description">${isSetup
          ? "Create the first administrator for this browser simulation."
          : "Sign in to the simulated computer update dashboard."}</p>
        <div class="simulation-notice" role="note">Simulation only — data is stored in this browser. This is not PHP execution or real authentication.</div>
        ${alertMarkup()}
        <form class="auth-form" data-form="${isSetup ? "setup" : "login"}">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" autocomplete="username" minlength="3" maxlength="32" pattern="[A-Za-z0-9._\\x2d]{3,32}" required autofocus>
          <label for="password">${isSetup ? "Create password" : "Password"}</label>
          <input id="password" name="password" type="password" autocomplete="${isSetup ? "new-password" : "current-password"}" maxlength="1024" ${isSetup ? 'minlength="12"' : ""} required>
          ${isSetup ? `
          <label for="password-confirmation">Confirm password</label>
          <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="1024" required>
          <p class="field-hint">Use 12–1024 characters. Usernames allow letters, numbers, dots, underscores, and hyphens.</p>` : ""}
          <button class="button button-primary auth-submit" type="submit">${isSetup ? "Create administrator" : "Sign in"}</button>
        </form>
        <p class="auth-footnote">Passwords are hashed for this local demo; do not use real credentials.</p>
      </section>
    </main>`;
}

function renderDashboard(user) {
  const counts = countStatuses();
  const total = state.computers.length;
  const completionRate = total === 0 ? 0 : Math.round((counts.completed / total) * 100);
  const canUpdate = user.role === "admin" || user.role === "operator";
  const isAdmin = user.role === "admin";
  const rows = state.computers.map((computer) => `
    <tr>
      <td><span class="computer-name">${escapeHtml(computer.name)}</span></td>
      <td><span class="status-badge status-${escapeHtml(computer.status)}">${statusLabels[computer.status]}</span></td>
      ${canUpdate ? `<td>${computer.status === "completed"
        ? '<span class="already-complete">Done</span>'
        : `<button class="button button-small" type="button" data-action="complete" data-id="${escapeHtml(computer.id)}">Mark completed</button>`}</td>` : ""}
    </tr>`).join("");
  const bars = ["completed", "progress", "remaining"].map((status) => {
    const percentage = total === 0 ? 0 : (counts[status] / total) * 100;
    return `<div class="bar-row">
      <span class="bar-label">${statusLabels[status]}</span>
      <div class="bar-track"><span class="bar-fill bar-fill-${status}" style="width:${percentage}%"></span></div>
      <strong>${counts[status]}</strong>
    </div>`;
  }).join("");

  app.innerHTML = `
    <main class="page-shell">
      <header class="topbar">
        <a class="brand" href="#dashboard"><span class="brand-mark" aria-hidden="true">IT</span><span class="brand-name">IT Support <span>PHP Simulation</span></span></a>
        <div class="topbar-meta">
          <span class="live-dot demo" aria-hidden="true"></span>
          <span>${escapeHtml(user.username)} · ${roleLabels[user.role]}</span>
          ${isAdmin ? '<a class="header-link" href="#users">Manage users</a>' : ""}
          <button class="button button-secondary button-small" type="button" data-action="logout">Log out</button>
        </div>
      </header>
      <section class="page-heading" aria-labelledby="page-title">
        <div><p class="eyebrow">BROWSER-BASED SIMULATION</p><h1 id="page-title">Computer update status</h1><p class="page-description">Actions update sample data saved in this browser only.</p></div>
        ${isAdmin ? '<button class="button button-secondary" type="button" data-action="reset">Reset demo data</button>' : ""}
      </section>
      ${alertMarkup()}
      <section class="summary-section" aria-labelledby="summary-title">
        <div class="section-heading"><div><h2 id="summary-title">Update summary</h2><p>Counts calculated from local sample data</p></div><span class="last-updated">Local simulation</span></div>
        <div class="summary-grid">
          ${[
            ["remaining", "Not updated yet", "!"],
            ["progress", "Currently updating", "↻"],
            ["completed", "Update finished", "✓"],
            ["total", "All demo devices", "▦"],
          ].map(([key, caption, icon]) => `<article class="summary-card card-${key}">
            <div class="card-topline"><span class="card-label">${key === "total" ? "Total computers" : statusLabels[key]}</span><span class="card-icon" aria-hidden="true">${icon}</span></div>
            <p class="card-caption">${caption}</p><p class="card-value">${key === "total" ? total : counts[key]}</p><p class="card-unit">computers</p>
          </article>`).join("")}
        </div>
      </section>
      <section class="powerbi-section" aria-labelledby="report-title">
        <div class="section-heading powerbi-heading"><div><p class="eyebrow">BUSINESS INTELLIGENCE</p><h2 id="report-title">Report preview</h2><p>Local sample data overview</p></div><span class="report-source"><span class="report-source-dot" aria-hidden="true"></span>Simulation data</span></div>
        <div class="report-preview">
          <div class="report-toolbar"><span class="powerbi-mark" aria-hidden="true">▥</span><span>Computer Update Overview</span><span class="report-toolbar-label">SIMULATION</span></div>
          <div class="report-content"><div class="report-kpis">
            <article class="report-kpi"><span>Total computers</span><strong>${total}</strong></article>
            <article class="report-kpi"><span>Update completion</span><strong>${completionRate}%</strong></article>
            <article class="report-kpi"><span>Remaining</span><strong>${counts.remaining}</strong></article>
          </div><div class="report-chart-grid"><div class="report-chart">
            <div class="chart-heading"><strong>Computers by update status</strong><span>Current snapshot</span></div>
            <div class="bar-chart" role="img" aria-label="Computer counts by update status">${bars}</div>
            <div class="chart-legend"><span><i class="legend-dot legend-completed"></i>Completed</span><span><i class="legend-dot legend-progress"></i>In progress</span><span><i class="legend-dot legend-remaining"></i>Remaining</span></div>
          </div><div class="report-chart completion-chart">
            <div class="chart-heading"><strong>Completion rate</strong><span>Completed / total</span></div>
            <div class="completion-visual"><div class="completion-ring" style="--completion:${completionRate}%"><div class="completion-ring-center"><strong>${completionRate}%</strong><span>complete</span></div></div><p>Computers with completed updates</p></div>
          </div></div></div>
          <p class="report-disclaimer">Simulation only — this is not an embedded Power BI report.</p>
        </div>
      </section>
      <section class="devices-section" aria-labelledby="devices-title">
        <div class="section-heading device-heading"><div><h2 id="devices-title">Computer list</h2><p>${canUpdate ? "Use an action to update sample status" : "Your account has read-only access"}</p></div><span class="device-count">${total} devices</span></div>
        <div class="table-wrap"><table><thead><tr><th scope="col">Computer</th><th scope="col">Update status</th>${canUpdate ? '<th scope="col">Demo action</th>' : ""}</tr></thead><tbody>${rows}</tbody></table></div>
      </section>
      <footer class="page-footer">Simulation only. Data stays in this browser and does not change real computers.</footer>
    </main>`;
}

function renderUsers(user) {
  if (user.role !== "admin") {
    setRoute("dashboard");
    render();
    return;
  }
  const rows = state.users.map((account) => {
    const isCurrent = account.id === user.id;
    const roles = Object.entries(roleLabels).map(([value, label]) => `
      <option value="${value}" ${account.role === value ? "selected" : ""}>${label}</option>`).join("");
    return `<tr>
      <td><span class="computer-name">${escapeHtml(account.username)}</span>${isCurrent ? ' <span class="you-label">You</span>' : ""}</td>
      <td>${isCurrent ? `<span class="role-badge">${roleLabels[account.role]}</span>` : `
        <form class="inline-role-form" data-form="role" data-id="${escapeHtml(account.id)}">
          <select name="role" aria-label="Role for ${escapeHtml(account.username)}">${roles}</select>
          <button class="button button-small" type="submit">Save role</button>
        </form>`}</td>
      <td>${escapeHtml(new Date(account.createdAt).toLocaleDateString())}</td>
      <td>${isCurrent ? '<span class="you-label">Current account protected</span>' : `
        <button class="button button-small button-danger" type="button" data-action="delete-user" data-id="${escapeHtml(account.id)}">Delete</button>`}</td>
    </tr>`;
  }).join("");

  app.innerHTML = `
    <main class="page-shell">
      <header class="topbar"><a class="brand" href="#dashboard"><span class="brand-mark" aria-hidden="true">IT</span><span class="brand-name">IT Support <span>PHP Simulation</span></span></a>
        <div class="account-actions"><span class="signed-in-as">Signed in as <strong>${escapeHtml(user.username)}</strong></span><button class="button button-secondary button-small" type="button" data-action="logout">Log out</button></div>
      </header>
      <section class="page-heading"><div><p class="eyebrow">ADMINISTRATION</p><h1>User management</h1><p class="page-description">Create simulated accounts and set their access level.</p></div><a class="button button-secondary back-button" href="#dashboard">Back to dashboard</a></section>
      ${alertMarkup()}
      <div class="simulation-notice" role="note">Simulation only — accounts and permissions are stored in this browser, not on a server.</div>
      <section class="devices-section user-create-section" aria-labelledby="create-title">
        <div class="section-heading"><div><h2 id="create-title">Add user</h2><p>Create a simulated account and assign its access level.</p></div></div>
        <form class="user-create-form" data-form="add-user">
          <label>Username<input name="username" type="text" minlength="3" maxlength="32" pattern="[A-Za-z0-9._\\x2d]{3,32}" autocomplete="off" required></label>
          <label>Password<input name="password" type="password" minlength="12" maxlength="1024" autocomplete="new-password" required></label>
          <label>Role<select name="role" required><option value="viewer">Viewer</option><option value="operator">Operator</option><option value="admin">Admin</option></select></label>
          <button class="button button-primary create-user-button" type="submit">Add account</button>
          <p class="field-hint">Usernames use 3–32 letters, numbers, dots, underscores, or hyphens. Passwords require 12–1024 characters.</p>
        </form>
      </section>
      <section class="devices-section user-list-section" aria-labelledby="users-title">
        <div class="section-heading device-heading"><div><h2 id="users-title">Accounts and permissions</h2><p>Admin: manage users · Operator: update demo statuses · Viewer: read-only</p></div><span class="device-count">${state.users.length} users</span></div>
        <div class="table-wrap"><table><thead><tr><th scope="col">Username</th><th scope="col">Role</th><th scope="col">Created</th><th scope="col">Actions</th></tr></thead><tbody>${rows}</tbody></table></div>
      </section>
      <footer class="page-footer">Simulation only — do not use real account credentials.</footer>
    </main>`;
}

function render() {
  const user = currentUser();
  if (!user) {
    renderAuth();
  } else if (currentRoute === "users") {
    renderUsers(user);
  } else {
    currentRoute = "dashboard";
    renderDashboard(user);
  }
}

function validateUsername(username) {
  return /^[A-Za-z0-9._-]{3,32}$/.test(username);
}

function validatePassword(password) {
  return new TextEncoder().encode(password).length <= 1024 && Array.from(password).length >= 12;
}

function toHex(bytes) {
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function passwordHash(password, salt) {
  if (!crypto.subtle) throw new Error("Secure browser hashing is unavailable. Open this page in a modern browser on localhost.");
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(password),
    "PBKDF2",
    false,
    ["deriveBits"],
  );
  const bits = await crypto.subtle.deriveBits({
    name: "PBKDF2",
    salt: new Uint8Array(salt.match(/.{2}/g).map((byte) => Number.parseInt(byte, 16))),
    iterations: 150000,
    hash: "SHA-256",
  }, key, 256);
  return toHex(new Uint8Array(bits));
}

function createSalt() {
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  return toHex(bytes);
}

async function handleSubmit(form) {
  const formData = new FormData(form);
  const formType = form.dataset.form;
  if (formType === "setup") {
    const username = String(formData.get("username") || "").trim();
    const password = String(formData.get("password") || "");
    const confirmation = String(formData.get("password_confirmation") || "");
    if (!validateUsername(username)) throw new Error("Choose a username of 3–32 letters, numbers, dots, underscores, or hyphens.");
    if (!validatePassword(password)) throw new Error("Use a password with at least 12 characters and no more than 1024 bytes.");
    if (password !== confirmation) throw new Error("The password confirmation does not match.");
    const salt = createSalt();
    const user = { id: crypto.randomUUID(), username, role: "admin", createdAt: new Date().toISOString(), salt, passwordHash: await passwordHash(password, salt) };
    state.users.push(user);
    state.currentUserId = user.id;
    setRoute("dashboard");
    if (saveState()) showNotice("Administrator created in this browser simulation.");
  } else if (formType === "login") {
    const username = String(formData.get("username") || "").trim().toLowerCase();
    const password = String(formData.get("password") || "");
    const user = state.users.find((account) => account.username.toLowerCase() === username);
    if (!user || await passwordHash(password, user.salt) !== user.passwordHash) {
      throw new Error("Incorrect username or password.");
    }
    state.currentUserId = user.id;
    setRoute("dashboard");
    if (saveState()) showNotice("Signed in to the browser simulation.");
  } else if (formType === "add-user") {
    const username = String(formData.get("username") || "").trim();
    const password = String(formData.get("password") || "");
    const role = String(formData.get("role") || "");
    if (!validateUsername(username)) throw new Error("Choose a username of 3–32 letters, numbers, dots, underscores, or hyphens.");
    if (!validatePassword(password)) throw new Error("Use a password with at least 12 characters and no more than 1024 bytes.");
    if (!Object.hasOwn(roleLabels, role)) throw new Error("Choose a valid user role.");
    if (state.users.some((account) => account.username.toLowerCase() === username.toLowerCase())) throw new Error("That username is already in use.");
    const salt = createSalt();
    state.users.push({ id: crypto.randomUUID(), username, role, createdAt: new Date().toISOString(), salt, passwordHash: await passwordHash(password, salt) });
    if (saveState()) showNotice(`Account created for ${username}.`);
  } else if (formType === "role") {
    const target = state.users.find((account) => account.id === form.dataset.id);
    const role = String(formData.get("role") || "");
    if (!target || !Object.hasOwn(roleLabels, role)) throw new Error("Choose a valid account and role.");
    if (target.role === "admin" && role !== "admin" && state.users.filter((account) => account.role === "admin").length <= 1) {
      throw new Error("The last administrator must remain an admin.");
    }
    target.role = role;
    if (saveState()) showNotice("User role updated.");
  }
  render();
}

app.addEventListener("submit", async (event) => {
  const form = event.target.closest("form[data-form]");
  if (!form) return;
  event.preventDefault();
  errorMessage = "";
  noticeMessage = "";
  try {
    await handleSubmit(form);
  } catch (error) {
    showError(error.message);
    render();
  }
});

app.addEventListener("click", (event) => {
  const button = event.target.closest("[data-action]");
  if (!button) return;
  const user = currentUser();
  const { action, id } = button.dataset;
  errorMessage = "";
  noticeMessage = "";
  if (action === "logout") {
    state.currentUserId = null;
    setRoute("login");
    if (saveState()) showNotice("Signed out of the browser simulation.");
  } else if (action === "complete" && user && ["admin", "operator"].includes(user.role)) {
    const computer = state.computers.find((item) => item.id === id);
    if (computer) {
      computer.status = "completed";
      if (saveState()) showNotice(`Marked ${computer.name} as completed.`);
    }
  } else if (action === "reset" && user && user.role === "admin") {
    state.computers = initialComputers.map((computer) => ({ ...computer }));
    if (saveState()) showNotice("Demo data has been reset.");
  } else if (action === "delete-user" && user && user.role === "admin") {
    const target = state.users.find((account) => account.id === id);
    if (!target || target.id === user.id) {
      showError("You cannot delete the account you are currently using.");
    } else if (target.role === "admin" && state.users.filter((account) => account.role === "admin").length <= 1) {
      showError("The last administrator account cannot be deleted.");
    } else if (window.confirm(`Delete the account "${target.username}"?`)) {
      state.users = state.users.filter((account) => account.id !== id);
      if (saveState()) showNotice("Account deleted.");
    }
  }
  render();
});

window.addEventListener("hashchange", () => {
  currentRoute = location.hash === "#users" ? "users" : "dashboard";
  errorMessage = "";
  noticeMessage = "";
  render();
});

try {
  state = loadState();
  currentRoute = location.hash === "#users" ? "users" : "dashboard";
  render();
} catch (error) {
  app.innerHTML = `<main class="auth-shell"><section class="auth-card"><h1>Simulation could not start</h1><div class="auth-error" role="alert">${escapeHtml(error.message)}</div><p class="page-description">Clear the local storage entry for <code>${storageKey}</code> to restart.</p></section></main>`;
}
