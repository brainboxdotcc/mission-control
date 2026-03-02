@extends('layouts.app')

@php($os = config('mission-control.branding.osname'))

@section('title', "$os - Try it now")

@section('content')
    <div class="grid cols-2">
        <div class="card">
            <h2 style="margin:0 0 10px 0; font-size: 18px;">
                {{ config('mission-control.branding.title_line') }}
            </h2>

            <div style="display:flex; gap: 14px; align-items: flex-start; margin: 0 0 12px 0;" class="mc-hero">
                <img
                    src="{{ config('mission-control.branding.logo') }}"
                    alt="Logo"
                    style="flex: 0 0 auto; width: 96px; height: 96px; border-radius: 14px; image-rendering: auto;"
                >

                <div style="min-width: 0;">
                    <p class="note" style="margin:0 0 14px 0;">
                        This launches a real, temporary {{$os}} system right here in your browser.
                        No install. No setup. Just click and explore.
                    </p>

                    <p class="note" style="margin:0 0 0 0;">
                        It may take a few seconds to start (sometimes 10–15 seconds if the server’s busy).
                        Once it’s ready, the screen will appear, and you can begin typing straight away.
                    </p>
                </div>
            </div>

            <div style="margin-left: auto; margin-right: auto; width: 16em; display:flex; gap: 10px; flex-wrap: wrap; align-items: center; padding-top: 3em; padding-bottom: 3em">
                <form method="post" action="{{ route('try.start') }}" id="try-form">
                    @csrf
                    <button class="btn primary" type="submit" id="try-button">
                        Start session
                    </button>
                </form>

                <a class="btn" href=" {{ config('mission-control.branding.url') }}" target="_blank" rel="noreferrer">
                    Learn more
                </a>
            </div>

            <div style="margin-top: 14px; padding: 10px 12px; border-radius: 10px; background: rgba(255,255,255,0.03);">
                <strong>Tip:</strong> click inside the screen to focus it, then type as normal.
                If keys feel "stuck", click outside the screen and then back in again.
            </div>
        </div>

        <div class="card">
            <h3 style="margin:0 0 10px 0; font-size: 16px;">
                What to expect
            </h3>

            <ul style="margin:0; padding-left: 18px; line-height: 1.6;">
                <li>
                    Each session runs for up to <strong>30 minutes</strong>.
                </li>
                <li>
                    If you leave it idle for a couple of minutes, it will end automatically so someone else can try.
                </li>
                <li>
                    Every session starts fresh - anything you change disappears when you close it.
                </li>
                <li>
                    Up to <strong>3 people</strong> can run a session at the same time.
                    If it’s full, just try again shortly.
                </li>
            </ul>

            <div style="margin-top: 14px;">
                <h3 style="margin:0 0 8px 0; font-size: 16px;">
                    Not sure what to do?
                </h3>
                <ol style="margin:0; padding-left: 18px; line-height: 1.6;">
                    <li>Start a session.</li>
                    <li>Look around and experiment.</li>
                    <li>If you get lost, just refresh and begin again.</li>
                </ol>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById("try-form");
            const button = document.getElementById("try-button");

            if (!form || !button) {
                return;
            }

            form.addEventListener("submit", () => {
                button.disabled = true;
                button.dataset.originalText = button.textContent;
                button.textContent = "Starting...";
            });
        })();
    </script>
    <style>
        @media (max-width: 720px) {
            .mc-hero {
                flex-direction: column;
                align-items: flex-start;
            }
            .mc-hero img {
                width: 80px !important;
                height: 80px !important;
            }
        }
    </style>
@endpush

