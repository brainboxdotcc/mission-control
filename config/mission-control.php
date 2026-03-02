<?php

return [
    'limits' => [
        'hard_seconds' => (int) env('MISSION_CONTROL_HARD_SECONDS', 1800),
        'idle_seconds' => (int) env('MISSION_CONTROL_IDLE_SECONDS', 120),
    ],

    'paths' => [
        'base_image' => env('MISSION_CONTROL_BASE_IMAGE', storage_path('app/mission-control/base/harddisk0')),
        'overlay_dir' => env('MISSION_CONTROL_OVERLAY_DIR', storage_path('app/mission-control/overlays')),
        'log_dir' => env('MISSION_CONTROL_LOG_DIR', storage_path('app/mission-control/logs')),
    ],


    'qemu' => [
        'qemu_bin' => env('MISSION_CONTROL_QEMU_BIN', '/usr/bin/qemu-system-x86_64'),
        'qemu_img_bin' => env('MISSION_CONTROL_QEMU_IMG_BIN', '/usr/bin/qemu-img'),
        'ovmf_code' => env('MISSION_CONTROL_OVMF_CODE', '/usr/share/OVMF/OVMF_CODE.fd'),

        // core machine sizing
        'machine' => env('MISSION_CONTROL_QEMU_MACHINE', 'q35'),
        'accel' => env('MISSION_CONTROL_QEMU_ACCEL', 'kvm'),
        'cpu' => env('MISSION_CONTROL_QEMU_CPU', 'host'),
        'smp' => (int) env('MISSION_CONTROL_QEMU_SMP', 8),
        'mem_mb' => (int) env('MISSION_CONTROL_QEMU_MEM_MB', 4096),

        // boot
        'boot' => env('MISSION_CONTROL_QEMU_BOOT', 'c'),
        'no_shutdown' => (bool) env('MISSION_CONTROL_QEMU_NO_SHUTDOWN', true),

        // storage/controller model
        'storage' => [
            'controller' => env('MISSION_CONTROL_QEMU_STORAGE_CONTROLLER', 'ahci'), // ahci, virtio-scsi, etc
        ],

        // networking (default: user-mode, no dumping)
        'net' => [
            'enabled' => (bool) env('MISSION_CONTROL_QEMU_NET_ENABLED', true),
            'mode' => env('MISSION_CONTROL_QEMU_NET_MODE', 'user'), // user|none
            'nic' => env('MISSION_CONTROL_QEMU_NET_NIC', 'e1000'),
            'dump_enabled' => (bool) env('MISSION_CONTROL_QEMU_NET_DUMP_ENABLED', false),
        ],

        // logging
        'logging' => [
            'debugcon_enabled' => (bool) env('MISSION_CONTROL_QEMU_DEBUGCON_ENABLED', true),
            'internal_log_enabled' => (bool) env('MISSION_CONTROL_QEMU_INTERNAL_LOG_ENABLED', true),
            'internal_log_flags' => env('MISSION_CONTROL_QEMU_INTERNAL_LOG_FLAGS', 'guest_errors'),
        ],

        /*
         |--------------------------------------------------------------------------
         | Boot Mode Extensions
         |--------------------------------------------------------------------------
         |
         | disk   = normal overlay HDD boot (default, Retro Rocket)
         | cdrom  = attach ISO and boot from it
         | usb    = attach raw/IMG file as USB mass storage and boot
         |
         */
        'boot_mode' => env('MISSION_CONTROL_QEMU_BOOT_MODE', 'disk'),
        'cdrom_image' => env('MISSION_CONTROL_QEMU_CDROM_IMAGE'),
        'usb_image' => env('MISSION_CONTROL_QEMU_USB_IMAGE'),
        'usb_format' => env('MISSION_CONTROL_QEMU_USB_FORMAT', 'raw'),

        /*
         |--------------------------------------------------------------------------
         | Advanced
         |--------------------------------------------------------------------------
         |
         | JSON array of extra QEMU arguments.
         | Example:
         |   ["-display","none"]
         |
         */
        'extra_args_json' => env('MISSION_CONTROL_QEMU_EXTRA_ARGS_JSON', '[]'),
    ],

    'branding' => [
        'app_name' => env('MISSION_CONTROL_APP_NAME', 'OS Demo'),
        'tagline' => env('MISSION_CONTROL_TAGLINE', 'Try it now - Play with the OS in your browser'),
        'osname' => env('MISSION_CONTROL_OS_NAME', 'Dummy OS'),
        'title_line' => env('MISSION_CONTROL_TITLE_LINE', 'Take the OS for a spin!'),
        'url' => env('MISSION_CONTROL_URL', 'https://dummy.os'),
        'logo' => env('MISSION_CONTROL_LOGO', '/img/missioncontrol.png'),
    ],
];
