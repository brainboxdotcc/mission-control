# <img width="24" height="24" alt="image" src="https://github.com/user-attachments/assets/90da5bb3-1e4f-4f07-896b-751318e98490" /> Mission Control

## Launch a real operating system in the browser. Safely.

Mission Control is a Laravel 12 application that spins up temporary QEMU virtual machines, exposes them via WebSocket, and renders them using noVNC - all inside your browser.

<img width="1411" height="1027" alt="image" src="https://github.com/user-attachments/assets/5acc878a-dc9a-45f9-a993-6cbfce3361b9" />

It was built to power the **Retro Rocket OS demo**, but it works for any QEMU-compatible OS.

No systemd.
No root.
No Docker required.
Just Laravel, QEMU and a web server.

---

# What It Does

When someone clicks **Start session**, Mission Control selects a free VM slot, creates a temporary overlay disk, and launches QEMU locally.

The browser connects via WebSocket to a localhost VNC server, and the guest operating system appears inside the page via noVNC.

Each session has strict idle and maximum runtime limits. When time runs out or the user leaves, the virtual machine is terminated, its overlay disk is deleted, and the slot is freed for the next visitor.

Nothing persists between sessions. Each run is isolated, and the base disk image is never modified.

---

# Requirements

* Linux host
* PHP 8.3+
* Composer
* Node 18+
* MySQL / MariaDB
* QEMU 10.1
* Apache 2.4 with WebSocket proxy support

---

# Very Important: User & Permissions

Mission Control **must not** run as `root` or run as `www-data`

Create a dedicated user:

```bash
sudo adduser missionctl
sudo usermod -aG kvm missionctl
```

Your web PHP-FPM pool should run as this user. This user must be able to execute QEMU, and have write access to `storage/`.

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

---

## 4. Run database migrations

Mission Control requires its database tables before it can allocate VM slots or track leases.

Run:

```bash
php artisan migrate
php artisan db:seed
```

After migrations complete, the application is ready to start launching sessions.

---

## 5. Scheduler

Mission Control relies on Laravel's scheduler to perform periodic maintenance tasks.

Add a cron entry for your **application user** (not root):

```bash
* * * * * cd /path/to/mission-control && php artisan schedule:run >> /dev/null 2>&1
```

---

# Configure Environment

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

---

# QEMU Configuration

Mission Control builds the QEMU command safely from structured configuration.

It always attaches a per-session overlay disk, binds VNC and WebSocket to localhost and uses OVMF firmware (UEFI)

Configuration is controlled through environment variables.

---

# Boot Modes

Mission Control supports three boot modes.

| Mode  | Description                     |
| ----- | ------------------------------- |
| disk  | Boot from overlay HDD (default) |
| cdrom | Boot from ISO                   |
| usb   | Boot from USB image             |

Overlay disks are **always attached**. Boot mode only changes device priority.

---

## HDD Boot (Default for Retro Rocket)

```env
MISSION_CONTROL_QEMU_BOOT_MODE=disk
MISSION_CONTROL_BASE_IMAGE=/home/missionctl/retro-rocket.img
```

The overlay disk is created from the base image and used as the primary boot device.

---

## CD-ROM Boot (Installer / Live ISO)

```env
MISSION_CONTROL_QEMU_BOOT_MODE=cdrom
MISSION_CONTROL_QEMU_CDROM_IMAGE=/home/missionctl/debian.iso
```

Behaviour:

* ISO attached as CD device
* Overlay disk remains attached
* CD receives boot priority
* Overlay becomes install target

Suitable for Linux installers or live systems.

---

## USB Boot

```env
MISSION_CONTROL_QEMU_BOOT_MODE=usb
MISSION_CONTROL_QEMU_USB_IMAGE=/home/missionctl/os-usb.img
MISSION_CONTROL_QEMU_USB_FORMAT=raw
```

Behaviour:

* USB mass-storage device attached
* Overlay disk remains attached
* USB device receives boot priority

Useful for prebuilt appliance-style OS images.

---

# VM Sizing & Behaviour

Machine configuration:

```env
MISSION_CONTROL_QEMU_MACHINE=q35
MISSION_CONTROL_QEMU_ACCEL=kvm
MISSION_CONTROL_QEMU_CPU=host
MISSION_CONTROL_QEMU_SMP=4
MISSION_CONTROL_QEMU_MEM_MB=2048
```

Networking (default user-mode NAT):

```env
MISSION_CONTROL_QEMU_NET_ENABLED=true
MISSION_CONTROL_QEMU_NET_MODE=user
MISSION_CONTROL_QEMU_NET_NIC=e1000
```

Logging:

```env
MISSION_CONTROL_QEMU_DEBUGCON_ENABLED=true
MISSION_CONTROL_QEMU_INTERNAL_LOG_ENABLED=true
MISSION_CONTROL_QEMU_INTERNAL_LOG_FLAGS=guest_errors
```

---

# Advanced: Extra QEMU Arguments

For advanced use cases.

Arguments must be provided as a JSON array:

```env
MISSION_CONTROL_QEMU_EXTRA_ARGS_JSON=["-display","none"]
```

The extra arguments must be avalid JSON array of strings and cannot override internal arguments such as overlay disks.

---

# Apache Configuration

Enable required modules:

```bash
a2enmod proxy
a2enmod proxy_wstunnel
```

Mission Control proxies WebSocket traffic to QEMU's VNC server via Apache.

---

# Runtime Limits

```env
MISSION_CONTROL_HARD_SECONDS=1800
MISSION_CONTROL_IDLE_SECONDS=120
```

When limits are exceeded:

* The VM process is terminated
* Overlay disk is deleted
* The VM slot is freed

No state survives between sessions.

---

# Operational Commands

Mission Control includes several Artisan commands for operational management.

All commands use the standard `app:` namespace.

---

## Clean Up Expired Sessions

```
php artisan app:reap
```

Performs automated cleanup tasks:

* Terminates expired leases
* Detects missing or crashed QEMU processes
* Frees slots stuck in `in_use` state
* Deletes overlay disks

This command is executed automatically by Laravel's scheduler.

---

## View System Status

```
php artisan app:stat
```

Displays:

* Total VM slots
* Slots currently in use
* Free capacity
* Active leases
* Remaining runtime per slot

Useful for quick operational checks.

---

## List Leases

```
php artisan app:leases
```

Shows currently active VM leases.

Options may include viewing ended leases or JSON output depending on the installation.

---

## Terminate a Lease

```
php artisan app:kill {leaseId}
```

Immediately terminates a running session.

Actions performed:

* Sends a termination signal to QEMU
* Deletes the overlay disk
* Marks the lease as ended
* Frees the associated slot

---

## Terminate a Slot

```
php artisan app:slotkill {slot}
```

Kills whichever lease is currently running in a given VM slot.

This is useful when you know the slot number but not the lease ID.

---

## Configure VM Slot Count

```
php artisan app:slots:set {count}
```

Ensures the system contains slots `1..count`.

This command **only creates missing slots** and does not delete existing rows.
Historical lease records remain intact.

---

# What This Is Not

Mission Control is deliberately simple and VPS-friendly. It does not rely on systemd units, background supervisors,
container orchestration, or root-level services. There is no daemon managing lifecycle events behind the scenes.

Everything runs directly under your application user and is managed by Laravel itself. Mission Control is also
**not a static-site project**. Because it launches QEMU locally and proxies WebSocket traffic, it requires a real
Linux host capable of executing system binaries.

If you need a purely client-side emulator, this project is not designed for that.

---

# Typical Use Cases

* OS demonstrations
* Retro computing environments
* Teaching sandboxes
* CTF platforms
* Installer testing
* Browser-based operating system experimentation
