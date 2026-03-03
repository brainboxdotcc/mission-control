import RFB from "@novnc/novnc";

const screen = document.getElementById("screen");

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function redirectHome() {
    window.location.assign("/");
}

let local_countdown_timer = null;
let local_countdown_ticks = 0;
let local_remaining_seconds = 0;
let local_total_seconds = 0;

function stopLocalCountdown() {
    if (local_countdown_timer) {
        clearInterval(local_countdown_timer);
        local_countdown_timer = null;
    }

    local_countdown_ticks = 0;
}

function startLocalCountdown(total_seconds, remaining_seconds) {
    stopLocalCountdown();

    local_total_seconds = total_seconds;
    local_remaining_seconds = Math.max(0, Math.floor(remaining_seconds));
    local_countdown_ticks = 0;

    // Tick at most 4 times (between 5s heartbeats), no client clock involved.
    local_countdown_timer = setInterval(() => {
        local_countdown_ticks += 1;

        local_remaining_seconds = Math.max(0, local_remaining_seconds - 1);
        updateDeadline(local_total_seconds, local_remaining_seconds);

        if (local_countdown_ticks >= 4 || local_remaining_seconds <= 0) {
            stopLocalCountdown();
        }
    }, 1000);
}

async function touchSession(leaseId, mode) {
    const resp = await fetch("/api/session/touch", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
        },
        credentials: "same-origin",
        body: JSON.stringify({ lease_id: leaseId, mode })
    });

    if (resp.status === 401 || resp.status === 403 || resp.status === 410) {
        redirectHome();
        return null;
    }

    if (!resp.ok) {
        return null;
    }

    const data = await resp.json();

    // Server is authoritative; update immediately from server values.
    stopLocalCountdown();
    updateDeadline(data.hard_limit, data.remaining);

    // Then locally tick for the next 4 seconds only.
    startLocalCountdown(data.hard_limit, data.remaining);

    return data;
}

function setStatus(state, text) {
    const tag = document.getElementById("vnc-status");
    const dot = document.getElementById("vnc-status-dot");
    const label = document.getElementById("vnc-status-text");

    if (!tag || !dot || !label) {
        return;
    }

    tag.dataset.state = state;
    label.textContent = text;

    dot.classList.remove("good", "bad", "warn");

    if (state === "connected") {
        dot.classList.add("good");
    } else if (state === "connecting" || state === "starting") {
        dot.classList.add("warn");
    } else {
        dot.classList.add("bad");
    }
}

function hookEvents(rfb, leaseId) {
    rfb.addEventListener("connect", () => {
        setStatus("connected", "Connected");
    });

    rfb.addEventListener("disconnect", () => {
        setStatus("disconnected", "Not connected");

        if (leaseId) {
            // If the VM was reaped/ended, bounce back to home.
            touchSession(leaseId, "heartbeat").catch(() => {
            });
        }
    });

    rfb.addEventListener("credentialsrequired", () => {
        setStatus("auth", "Auth required");
    });
}

async function connectWithRetry(screenEl, wsUrl, leaseId) {
    const delays = [250, 500, 1000, 1500, 2000, 3000, 3000];

    setStatus("connecting", "Connecting...");

    for (const delay of delays) {
        try {
            const rfb = new RFB(screenEl, wsUrl);
            rfb.scaleViewport = true;
            rfb.resizeSession = true;
            hookEvents(rfb, leaseId);
            return rfb;
        } catch (_) {
            setStatus("starting", "Starting...");
            await sleep(delay);
        }
    }

    // Last attempt without swallowing errors
    const rfb = new RFB(screenEl, wsUrl);
    rfb.scaleViewport = true;
    rfb.resizeSession = true;
    hookEvents(rfb, leaseId);
    return rfb;
}

function formatDuration(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    if (m > 0) {
        return `${m}m ${s.toString().padStart(2, "0")}s`;
    }

    return `${s}s`;
}

function updateDeadline(total, seconds) {
    const remainingEl = document.getElementById("time-remaining");
    const barEl = document.getElementById("time-bar");
    const warnEl = document.getElementById("session-warning");

    if (remainingEl) {
        remainingEl.textContent = formatDuration(seconds);
    }

    if (barEl) {
        const pct = Math.max(0, (seconds / total) * 100);
        barEl.style.width = pct + "%";

        if (pct < 25) {
            barEl.style.background = "#ffcc00";
        }
        if (pct < 10) {
            barEl.style.background = "#ff4444";
        }
    }

    if (warnEl) {
        warnEl.style.display = seconds <= 60 ? "block" : "none";
    }

    if (seconds <= 0) {
        // Display-only: server is authoritative for expiry.
        if (remainingEl) {
            remainingEl.textContent = "0s";
        }
        if (warnEl) {
            warnEl.style.display = "block";
        }
    }
}

const sessionCard = document.getElementById("session-card");
const leaseId = sessionCard ? sessionCard.dataset.leaseId : null;

// Poll the server, so we bounce quickly if the lease is ended by reaper/manual release.
if (leaseId) {
    touchSession(leaseId, "heartbeat").catch(() => {
    });

    setInterval(() => {
        touchSession(leaseId, "heartbeat").catch(() => {
        });
    }, 5000);
}

if (screen) {
    const wsUrl = screen.dataset.wsUrl;
    if (wsUrl) {
        connectWithRetry(screen, wsUrl, leaseId).catch(() => {
            setStatus("disconnected", "Not connected");
        });
    }
}
