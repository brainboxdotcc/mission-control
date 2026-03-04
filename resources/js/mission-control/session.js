import RFB from "@novnc/novnc";

const screen = document.getElementById("screen");
let local_countdown_timer = null;
let local_countdown_ticks = 0;
let local_remaining_seconds = 0;
let local_total_seconds = 0;
let ctrlDown = false;
let altDown = false;

function setToggleState(button, isDown) {
    button.classList.toggle("is-toggled", isDown);
    button.setAttribute("aria-pressed", isDown ? "true" : "false");
}

function sendKeyEvent(keysym, code, isDown) {
    rfb.sendKey(keysym, code, isDown);
}

function toggleCtrl() {
    ctrlDown = !ctrlDown;
    sendKeyEvent(0xffe3, "ControlLeft", ctrlDown);
    setToggleState(ctrlButton, ctrlDown);
}

function toggleAlt() {
    altDown = !altDown;
    sendKeyEvent(0xffe9, "AltLeft", altDown);
    setToggleState(altButton, altDown);
}

function releaseModifiers() {
    if (ctrlDown) {
        ctrlDown = false;
        sendKeyEvent(0xffe3, "ControlLeft", false);
        setToggleState(ctrlButton, false);
    }

    if (altDown) {
        altDown = false;
        sendKeyEvent(0xffe9, "AltLeft", false);
        setToggleState(altButton, false);
    }
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function redirectHome() {
    window.location.assign("/");
}

function useSoftKeyboardUi() {
    const coarsePointer = window.matchMedia("(pointer: coarse)").matches;
    const noHover = window.matchMedia("(hover: none)").matches;

    return coarsePointer && noHover;
}

function sendCharToRfb(rfb, ch) {
    const codePoint = ch.codePointAt(0);
    if (codePoint == null) {
        return;
    }

    // X11 keysyms for Unicode are usually the Unicode code point for BMP chars.
    // This works well for typical ASCII/Latin input on consoles.
    rfb.sendKey(codePoint, null, true);
    rfb.sendKey(codePoint, null, false);
}

function setupSoftKeyboardUi(rfb) {
    if (!useSoftKeyboardUi()) {
        return;
    }

    if (document.getElementById("softKeyboardBar")) {
        return;
    }

    const keyboardInput = document.createElement("textarea");
    keyboardInput.id = "softKeyboardInput";
    keyboardInput.autocomplete = "off";
    keyboardInput.autocapitalize = "off";
    keyboardInput.spellcheck = false;
    keyboardInput.inputMode = "text";

    keyboardInput.style.position = "fixed";
    keyboardInput.style.left = "-10000px";
    keyboardInput.style.top = "0";
    keyboardInput.style.width = "1px";
    keyboardInput.style.height = "1px";
    keyboardInput.style.opacity = "0";

    document.body.appendChild(keyboardInput);

    let lastValue = "";

    function flushDiff() {
        const value = keyboardInput.value;

        if (value.length > lastValue.length && value.startsWith(lastValue)) {
            const added = value.slice(lastValue.length);

            for (const ch of added) {
                sendCharToRfb(rfb, ch);
            }

            lastValue = value;
            return;
        }

        if (value.length < lastValue.length && lastValue.startsWith(value)) {
            const deletes = lastValue.length - value.length;

            for (let i = 0; i < deletes; i += 1) {
                rfb.sendKey(0xff08, "Backspace", true);
                rfb.sendKey(0xff08, "Backspace", false);
            }

            lastValue = value;
            return;
        }

        lastValue = value;
    }

    keyboardInput.addEventListener("beforeinput", (e) => {
        if (e.inputType === "insertLineBreak") {
            rfb.sendKey(0xff0d, "Enter", true);
            rfb.sendKey(0xff0d, "Enter", false);
        }

        if (e.inputType === "deleteContentBackward") {
            rfb.sendKey(0xff08, "Backspace", true);
            rfb.sendKey(0xff08, "Backspace", false);
        }
    });

    keyboardInput.addEventListener("input", () => {
        flushDiff();

        if (keyboardInput.value.length > 64) {
            keyboardInput.value = "";
            lastValue = "";
        }
    });

    const keyboardBar = document.createElement("div");
    keyboardBar.id = "softKeyboardBar";

    const keyboardButton = document.createElement("button");
    keyboardButton.id = "softKeyboardButton";
    keyboardButton.type = "button";
    keyboardButton.textContent = "⌨";

    const ctrlButton = document.createElement("button");
    ctrlButton.id = "softCtrlButton";
    ctrlButton.type = "button";
    ctrlButton.textContent = "Ctrl";

    const altButton = document.createElement("button");
    altButton.id = "softAltButton";
    altButton.type = "button";
    altButton.textContent = "Alt";

    const escButton = document.createElement("button");
    escButton.id = "softEscButton";
    escButton.type = "button";
    escButton.textContent = "Esc";

    let ctrlDown = false;
    let altDown = false;

    function setToggleState(button, isDown) {
        button.classList.toggle("is-toggled", isDown);
        button.setAttribute("aria-pressed", isDown ? "true" : "false");
    }

    function sendKeyEvent(keysym, code, isDown) {
        rfb.sendKey(keysym, code, isDown);
    }

    function toggleCtrl() {
        ctrlDown = !ctrlDown;
        sendKeyEvent(0xffe3, "ControlLeft", ctrlDown);
        setToggleState(ctrlButton, ctrlDown);
    }

    function toggleAlt() {
        altDown = !altDown;
        sendKeyEvent(0xffe9, "AltLeft", altDown);
        setToggleState(altButton, altDown);
    }

    function releaseModifiers() {
        if (ctrlDown) {
            ctrlDown = false;
            sendKeyEvent(0xffe3, "ControlLeft", false);
            setToggleState(ctrlButton, false);
        }

        if (altDown) {
            altDown = false;
            sendKeyEvent(0xffe9, "AltLeft", false);
            setToggleState(altButton, false);
        }
    }

    keyboardButton.addEventListener("click", () => {
        keyboardInput.focus({ preventScroll: true });
    });

    ctrlButton.addEventListener("click", () => {
        toggleCtrl();
    });

    altButton.addEventListener("click", () => {
        toggleAlt();
    });

    escButton.addEventListener("click", () => {
        rfb.sendKey(0xff1b, "Escape", true);
        rfb.sendKey(0xff1b, "Escape", false);
        releaseModifiers();
    });

    keyboardBar.appendChild(keyboardButton);
    keyboardBar.appendChild(ctrlButton);
    keyboardBar.appendChild(altButton);
    keyboardBar.appendChild(escButton);

    document.body.appendChild(keyboardBar);

    rfb.addEventListener("disconnect", () => {
        releaseModifiers();
        keyboardBar.remove();
        keyboardInput.remove();
    });
}

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
            setupSoftKeyboardUi(rfb);
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
    setupSoftKeyboardUi(rfb);
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
