# <img width="24" height="24" alt="image" src="https://github.com/user-attachments/assets/90da5bb3-1e4f-4f07-896b-751318e98490" /> Mission Control

## Launch a real operating system in the browser. Safely.

Mission Control is a Laravel 12 application that spins up temporary QEMU virtual machines, exposes them via WebSocket,
and renders them using noVNC - all inside your browser.

<img width="1411" height="1027" alt="image" src="https://github.com/user-attachments/assets/5acc878a-dc9a-45f9-a993-6cbfce3361b9" />

It was built to power the **Retro Rocket OS demo**, but it works for any QEMU-compatible OS.

No systemd.
No root.
No Docker required.
Just Laravel, QEMU and a web server.

---

# What It Does

When someone clicks "Start session", Mission Control picks a free VM slot, creates a temporary overlay disk, and launches QEMU locally.
The browser connects to it over WebSocket via VNC, and the operating system appears in the page.

Each session has strict idle and maximum runtime limits. When time runs out or the user leaves,
the virtual machine is stopped, its overlay disk is deleted, and the slot is freed for the next person.

Nothing persists. Every session is isolated, and the base image is never modified.

---

# Requirements

* Linux host
* PHP 8.3+
* Composer
* Node 18+
* MySQL / MariaDB
* QEMU installed
* Apache or Nginx with WebSocket proxy support

---

# Very Important: User & Permissions

Mission Control **must not**:

* Run as `root`
* Run as `www-data`

Create a dedicated user (example):

```bash
sudo adduser missionctl
sudo usermod -aG kvm missionctl
```

Your web server / PHP-FPM should run as this user.

This user must:

* Be able to execute `qemu-system-x86_64`
* Have write access to `storage/`
* Not have root privileges

This keeps QEMU contained and prevents privilege escalation.

---

# Installation

## 1. Clone

```bash
git clone https://github.com/brainboxdotcc/mission-control.git
cd mission-control
```

## 2. Install dependencies

```bash
composer install
npm install
npm run build
```

## 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Set Up the Scheduler (Required)

Mission Control relies on Laravel’s scheduler to:

* Reap expired leases
* Kill orphaned QEMU processes
* Clean up overlay disks

Without this, sessions will not be cleaned up automatically.

Add a cron entry for your **application user** (not root):

```bash
crontab -e
```

Add the following line:

```bash
* * * * * cd /path/to/mission-control && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/mission-control` with the full path to your installation.

This runs the scheduler once per minute and ensures expired sessions are reclaimed properly.

## 5. Edit the .env file

Example `.env`:

```env
APP_NAME="Mission Control"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://try.example.com

MISSION_CONTROL_HARD_SECONDS=1800
MISSION_CONTROL_IDLE_SECONDS=120

MISSION_CONTROL_BASE_IMAGE=/home/missionctl/base.img
MISSION_CONTROL_OVMF_CODE=/usr/share/OVMF/OVMF_CODE.fd

MISSION_CONTROL_QEMU_BIN=/usr/bin/qemu-system-x86_64
MISSION_CONTROL_QEMU_IMG_BIN=/usr/bin/qemu-img

MISSION_CONTROL_APP_NAME="Retro Rocket OS Demo"
MISSION_CONTROL_TAGLINE="Try it now - Play with the OS in your browser"
MISSION_CONTROL_OS_NAME="Retro Rocket"
MISSION_CONTROL_TITLE_LINE="Take Retro Rocket for a spin 🚀"
MISSION_CONTROL_URL="https://retrorocket.dev"
MISSION_CONTROL_LOGO="/img/logo.webp"

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=try
DB_USERNAME=try
DB_PASSWORD="xxxxxxx"
```

## 6. Run migrations:

```bash
php artisan migrate
```

---

# QEMU Configuration

Mission Control builds the QEMU command safely from structured configuration.

It always:

* Attaches a per-session overlay disk
* Binds VNC and WebSocket to localhost
* Detaches cleanly from PHP
* Uses OVMF firmware (UEFI)

You control behaviour via environment variables.

---

# Boot Modes

Mission Control supports three boot modes:

| Mode  | Description                     |
| ----- | ------------------------------- |
| disk  | Boot from overlay HDD (default) |
| cdrom | Boot from ISO                   |
| usb   | Boot from USB image             |

Overlay disks are **always attached**.
Boot mode only changes device priority.

---

## 1. HDD Boot (Default - Retro Rocket)

This is the standard configuration.

```env
MISSION_CONTROL_QEMU_BOOT_MODE=disk
MISSION_CONTROL_BASE_IMAGE=/home/missionctl/retro-rocket.img
```

This boots from the overlay disk based on your base image.

Nothing else required.

---

## 2. CD-ROM Boot (Installer / Live ISO)

```env
MISSION_CONTROL_QEMU_BOOT_MODE=cdrom
MISSION_CONTROL_QEMU_CDROM_IMAGE=/home/missionctl/debian.iso
```

Behaviour:

* ISO attached as CD device
* Overlay disk remains attached
* CD gets boot priority
* Overlay becomes install target

Perfect for:

* Linux installers
* Live ISOs
* Rescue environments

---

## 3. USB Boot

```env
MISSION_CONTROL_QEMU_BOOT_MODE=usb
MISSION_CONTROL_QEMU_USB_IMAGE=/home/missionctl/os-usb.img
MISSION_CONTROL_QEMU_USB_FORMAT=raw
```

Behaviour:

* USB image attached as mass storage
* Overlay disk still attached
* USB boots first

Useful for:

* Prebuilt USB-style OS images
* Embedded-style systems
* Custom test builds

---

# VM Sizing & Behaviour

You can control machine parameters:

```env
MISSION_CONTROL_QEMU_MACHINE=q35
MISSION_CONTROL_QEMU_ACCEL=kvm
MISSION_CONTROL_QEMU_CPU=host
MISSION_CONTROL_QEMU_SMP=4
MISSION_CONTROL_QEMU_MEM_MB=2048
```

Networking (default: user-mode NAT):

```env
MISSION_CONTROL_QEMU_NET_ENABLED=true
MISSION_CONTROL_QEMU_NET_MODE=user
MISSION_CONTROL_QEMU_NET_NIC=e1000
```

Logging options:

```env
MISSION_CONTROL_QEMU_DEBUGCON_ENABLED=true
MISSION_CONTROL_QEMU_INTERNAL_LOG_ENABLED=true
MISSION_CONTROL_QEMU_INTERNAL_LOG_FLAGS=guest_errors
```

---

# Advanced: Extra QEMU Arguments

For advanced use only.

Provide additional arguments as a JSON array:

```env
MISSION_CONTROL_QEMU_EXTRA_ARGS_JSON=["-display","none"]
```

Notes:

* Must be valid JSON
* Must be an array of strings
* Cannot override overlay, pidfile, or VNC arguments
* Intended for advanced experimentation

If you don’t need it, leave it as:

```env
MISSION_CONTROL_QEMU_EXTRA_ARGS_JSON=[]
```

---

# WebSocket Forwarding

Each slot exposes a local WebSocket port:

| Slot | Port |
| ---- | ---- |
| 0    | 5701 |
| 1    | 5702 |
| 2    | 5703 |

You must proxy `/vnc/{slot}/` to the matching port.

---

## Apache

Enable:

```bash
a2enmod proxy
a2enmod proxy_wstunnel
```

VirtualHost:

```apache
ProxyPreserveHost On

ProxyPass        /vnc/0/  ws://127.0.0.1:5701/
ProxyPassReverse /vnc/0/  ws://127.0.0.1:5701/

ProxyPass        /vnc/1/  ws://127.0.0.1:5702/
ProxyPassReverse /vnc/1/  ws://127.0.0.1:5702/

ProxyPass        /vnc/2/  ws://127.0.0.1:5703/
ProxyPassReverse /vnc/2/  ws://127.0.0.1:5703/
```

---

## Nginx

```nginx
location /vnc/0/ {
    proxy_pass http://127.0.0.1:5701/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}

location /vnc/1/ {
    proxy_pass http://127.0.0.1:5702/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}

location /vnc/2/ {
    proxy_pass http://127.0.0.1:5703/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}
```

---

# Runtime Limits

```env
MISSION_CONTROL_HARD_SECONDS=1800
MISSION_CONTROL_IDLE_SECONDS=120
```

When time expires:

* QEMU receives SIGKILL
* Overlay disk is deleted
* Slot is freed

No state survives.

---

# Production Advice

* Use HTTPS
* Disable `APP_DEBUG`
* Bind QEMU to `127.0.0.1` only
* Firewall ports 5701-5703
* Monitor storage usage

---

# What This Is Not

Mission Control is deliberately simple and VPS friendly. It does not rely on systemd units, background supervisors, 
container orchestration, or root-level processes. There is no Docker requirement, no service manager layer, and no hidden
daemon doing lifecycle work behind the scenes. Everything runs directly under your application user and is managed by
Laravel itself.

It is also not a static-site project. Because it launches QEMU locally and proxies WebSockets to it, Mission Control
cannot run on platforms such as GitHub Pages or other static-only hosting providers. It requires a real Linux server
with QEMU installed and the ability to execute system binaries.

If you need something that runs purely in the browser with no backend, this is not that. Mission Control is a
server-side VM launcher with a browser front end - intentionally straightforward, but still a real hypervisor-backed
system.

---

# Typical Use Cases

* OS demos
* Retro computing platforms
* Teaching sandboxes
* CTF labs
* Installer testing
* Browser-based experimentation
