@extends('layouts.app')

@section('title', 'Mission Control – Session')

@section('header_right')
    <span class="tag" id="vnc-status" data-state="disconnected">
        <span class="dot bad" id="vnc-status-dot"></span>
        <span id="vnc-status-text">Not connected</span>
    </span>
@endsection

@section('content')
    <div class="card" style="margin-bottom: 16px;" data-hard-deadline="{{ $hard_deadline }}" data-lease-id="{{ $lease_id }}" id="session-card">
        <h2 style="margin:0 0 8px 0; font-size: 18px;">Your session</h2>

        <p style="margin:0 0 12px 0;">
            You have <strong id="time-remaining">-</strong> remaining.
        </p>

        <div class="progress" style="height:6px; background:#222; border-radius:4px; overflow:hidden;">
            <div id="time-bar" style="height:100%; width:100%; background:#3cb371;"></div>
        </div>

        <p id="session-warning" style="margin:10px 0 0 0; display:none; color:#ffcc00;">
            Your session is about to expire.
        </p>
    </div>

    <div
        id="screen"
        class="terminal"
        data-ws-url="{{ request()->getSchemeAndHttpHost() }}{{ $ws_path }}">
    </div>

    @vite('resources/js/mission-control/session.js')

    @push('scripts')
        <script>
            (() => {
                const lease_id = @json($lease_id);

                let last_send_ms = 0;

                function post_json(url, payload) {
                    return fetch(url, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "Accept": "application/json"
                        },
                        body: JSON.stringify(payload),
                        credentials: "same-origin"
                    });
                }

                function touch(mode) {
                    const now = Date.now();
                    if (mode === "input") {
                        if (now - last_send_ms < 3000) {
                            return;
                        }
                        last_send_ms = now;
                    }

                    post_json("/api/session/touch", { lease_id, mode })
                        .catch(() => {});
                }

                function on_input() {
                    touch("input");
                }

                window.addEventListener("keydown", on_input, { passive: true });
                window.addEventListener("mousedown", on_input, { passive: true });
                window.addEventListener("mousemove", on_input, { passive: true });
                window.addEventListener("wheel", on_input, { passive: true });
                window.addEventListener("touchstart", on_input, { passive: true });
                window.addEventListener("pointerdown", on_input, { passive: true });

                // Heartbeat proves the client is alive; doesn't extend idle by policy above.
                setInterval(() => {
                    touch("heartbeat");
                }, 15000);

                // Best-effort release.
                window.addEventListener("beforeunload", () => {
                    try {
                        navigator.sendBeacon(
                            "/api/session/release",
                            new Blob([JSON.stringify({ lease_id })], { type: "application/json" })
                        );
                    } catch (_) {
                    }
                });
            })();
        </script>
    @endpush
@endsection
