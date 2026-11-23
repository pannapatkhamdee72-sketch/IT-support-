const form = document.querySelector("#api-form");
const endpointInput = document.querySelector("#api-url");
const refreshButton = form.querySelector("button");
const errorBanner = document.querySelector("#error-banner");
const errorMessage = document.querySelector("#error-message");
const computerList = document.querySelector("#computer-list");
const connectionLabel = document.querySelector("#connection-label");
const connectionDot = document.querySelector(".live-dot");
const demoButton = document.querySelector("#demo-button");
const listDescription = document.querySelector("#list-description");

const demoComputers = [
  { name: "BKK-OFFICE-001", status: "not_updated" },
  { name: "BKK-OFFICE-002", status: "not_updated" },
  { name: "BKK-OFFICE-003", status: "not_updated" },
  { name: "BKK-OFFICE-004", status: "not_updated" },
  { name: "BKK-OFFICE-005", status: "not_updated" },
  { name: "BKK-OFFICE-006", status: "in_progress" },
  { name: "BKK-OFFICE-007", status: "in_progress" },
  { name: "BKK-OFFICE-008", status: "in_progress" },
  { name: "BKK-OFFICE-009", status: "completed" },
  { name: "BKK-OFFICE-010", status: "completed" },
  { name: "BKK-OFFICE-011", status: "completed" },
  { name: "BKK-OFFICE-012", status: "completed" },
];

const statusGroups = {
  remaining: new Set([
    "pending",
    "not_updated",
    "notupdated",
    "outdated",
    "needs_update",
    "remaining",
    "not_started",
    "queued",
    "รอดำเนินการ",
    "ยังไม่ได้อัปเดต",
    "ยังไม่อัปเดต",
  ]),
  progress: new Set([
    "in_progress",
    "inprogress",
    "processing",
    "updating",
    "working",
    "กำลังดำเนินการ",
    "กำลังอัปเดต",
  ]),
  completed: new Set([
    "completed",
    "complete",
    "done",
    "finished",
    "success",
    "updated",
    "เสร็จสิ้น",
    "เสร็จแล้ว",
  ]),
};

const statusLabels = {
  remaining: "Remaining",
  progress: "In progress",
  completed: "Completed",
};

function getComputers(payload) {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (payload && typeof payload === "object") {
    for (const key of ["computers", "data", "devices", "results"]) {
      if (Array.isArray(payload[key])) {
        return payload[key];
      }
    }
  }

  throw new Error("The API response must be an array or contain a computers, data, devices, or results array.");
}

function getComputerStatus(computer) {
  if (!computer || typeof computer !== "object" || Array.isArray(computer)) {
    throw new Error("Every computer record must be a JSON object.");
  }

  if (computer.isUpdating === true || computer.is_updating === true) {
    return "progress";
  }

  if (computer.isUpdated === true || computer.is_updated === true) {
    return "completed";
  }

  const rawStatus = computer.status ?? computer.updateStatus ?? computer.update_status;
  if (typeof rawStatus === "boolean") {
    return rawStatus ? "completed" : "remaining";
  }

  const normalized = typeof rawStatus === "string"
    ? rawStatus.trim().toLocaleLowerCase().replace(/[\s-]+/g, "_")
    : "";

  for (const [status, values] of Object.entries(statusGroups)) {
    if (values.has(normalized)) {
      return status;
    }
  }

  return "remaining";
}

function getComputerName(computer, index) {
  for (const key of ["name", "hostname", "computerName", "computer_name", "deviceName", "device_name", "id"]) {
    const value = computer[key];
    if (typeof value === "string" || typeof value === "number") {
      return String(value);
    }
  }
  return `Computer ${index + 1}`;
}

function createStatusBadge(status) {
  const badge = document.createElement("span");
  badge.className = `status-badge status-${status}`;
  badge.textContent = statusLabels[status];
  return badge;
}

function renderComputers(computers, source = "api") {
  const counts = { remaining: 0, progress: 0, completed: 0 };
  const fragment = document.createDocumentFragment();

  computers.forEach((computer, index) => {
    const status = getComputerStatus(computer);
    counts[status] += 1;

    const row = document.createElement("tr");
    const nameCell = document.createElement("td");
    const statusCell = document.createElement("td");
    const name = document.createElement("span");
    name.className = "computer-name";
    name.textContent = getComputerName(computer, index);
    nameCell.append(name);
    statusCell.append(createStatusBadge(status));
    row.append(nameCell, statusCell);
    fragment.append(row);
  });

  if (computers.length === 0) {
    const row = document.createElement("tr");
    row.className = "empty-row";
    const cell = document.createElement("td");
    cell.colSpan = 2;
    cell.textContent = "No computers were reported by the API.";
    row.append(cell);
    fragment.append(row);
  }

  computerList.replaceChildren(fragment);
  document.querySelector("#remaining-count").textContent = counts.remaining.toLocaleString();
  document.querySelector("#progress-count").textContent = counts.progress.toLocaleString();
  document.querySelector("#completed-count").textContent = counts.completed.toLocaleString();
  document.querySelector("#total-count").textContent = computers.length.toLocaleString();
  document.querySelector("#device-count").textContent = `${computers.length.toLocaleString()} devices`;
  document.querySelector("#last-updated").textContent = `Last updated ${new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}`;
  listDescription.textContent = source === "demo"
    ? "Sample data for preview — not connected to a server"
    : "Details reported by the server API";
  document.querySelector("#page-footer").textContent = source === "demo"
    ? "Demo preview only — no real server data is being used."
    : "Data is loaded directly from your configured server API.";
  updateReportPreview(counts, computers.length, source);
}

function updateReportPreview(counts, total, source) {
  const completionRate = total === 0 ? 0 : Math.round((counts.completed / total) * 100);
  const sourceLabel = source === "demo" ? "Demo data" : "API data";

  document.querySelector("#report-source").textContent = sourceLabel;
  document.querySelector("#report-total").textContent = total.toLocaleString();
  document.querySelector("#report-completion").textContent = `${completionRate}%`;
  document.querySelector("#report-remaining").textContent = counts.remaining.toLocaleString();
  document.querySelector("#completion-percent").textContent = `${completionRate}%`;
  document.querySelector("#completion-ring").style.setProperty("--completion", `${completionRate}%`);

  for (const status of ["completed", "progress", "remaining"]) {
    const count = counts[status];
    const percentage = total === 0 ? 0 : (count / total) * 100;
    document.querySelector(`#bar-${status}`).style.width = `${percentage}%`;
    document.querySelector(`#bar-count-${status}`).textContent = count.toLocaleString();
  }
}

async function loadComputers(endpoint) {
  errorBanner.hidden = true;
  refreshButton.disabled = true;
  refreshButton.querySelector(".button-icon").textContent = "…";

  try {
    const response = await fetch(endpoint, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });

    if (!response.ok) {
      throw new Error(`The server returned ${response.status} ${response.statusText}.`);
    }

    const payload = await response.json();
    const computers = getComputers(payload);
    renderComputers(computers);
    connectionLabel.textContent = "API connected";
    connectionDot.classList.add("connected");
    connectionDot.classList.remove("demo");
  } catch (error) {
    errorMessage.textContent = error instanceof Error ? error.message : "An unexpected error occurred.";
    errorBanner.hidden = false;
    connectionLabel.textContent = "API connection failed";
    connectionDot.classList.remove("connected");
  } finally {
    refreshButton.disabled = false;
    refreshButton.querySelector(".button-icon").textContent = "↻";
  }
}

form.addEventListener("submit", (event) => {
  event.preventDefault();
  const endpoint = endpointInput.value.trim();
  if (endpoint) {
    loadComputers(endpoint);
  }
});

demoButton.addEventListener("click", () => {
  errorBanner.hidden = true;
  renderComputers(demoComputers, "demo");
  connectionLabel.textContent = "Demo data loaded";
  connectionDot.classList.remove("connected");
  connectionDot.classList.add("demo");
});
