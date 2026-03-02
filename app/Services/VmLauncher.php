<?php

namespace App\Services;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

final class VmLauncher
{
    public function ensureDirsExist(): void
    {
        File::ensureDirectoryExists((string) config('mission-control.paths.overlay_dir'));
        File::ensureDirectoryExists((string) config('mission-control.paths.log_dir'));
    }

    public function createOverlay(VmLease $lease): string
    {
        $baseImage = (string) config('mission-control.paths.base_image');
        $overlayDir = (string) config('mission-control.paths.overlay_dir');

        $overlayPath = rtrim($overlayDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'lease-' . $lease->id . '.qcow2';

        $cmd = [
            (string) config('mission-control.qemu.qemu_img_bin'),
            'create',
            '-f', 'qcow2',
            '-o', 'backing_file=' . $baseImage . ',backing_fmt=raw',
            $overlayPath,
        ];

        $proc = new Process($cmd);
        $proc->setTimeout(20);
        $proc->run();

        if (!$proc->isSuccessful()) {
            throw new RuntimeException('qemu-img failed: ' . $proc->getErrorOutput());
        }

        return $overlayPath;
    }

    /**
     * Starts QEMU and returns the PID.
     */
    public function startQemu(VmLease $lease, VmSlot $slot, string $overlayPath): int
    {
        $logDir = (string) config('mission-control.paths.log_dir');

        $debugLog = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'debug-' . $lease->id . '.log';
        $qemuInternalLog = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'qemu-' . $lease->id . '.log';
        $launcherLog = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'launcher-' . $lease->id . '.log';
        $pidFile = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'qemu-' . $lease->id . '.pid';

        $netDump = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'net-' . $lease->id . '.dat';

        $qemuBin = (string) config('mission-control.qemu.qemu_bin');
        $ovmf = (string) config('mission-control.qemu.ovmf_code');

        $machine = (string) config('mission-control.qemu.machine', 'q35');
        $accel = (string) config('mission-control.qemu.accel', 'kvm');
        $cpu = (string) config('mission-control.qemu.cpu', 'host');
        $smp = (int) config('mission-control.qemu.smp', 8);
        $memMb = (int) config('mission-control.qemu.mem_mb', 4096);

        $noShutdown = (bool) config('mission-control.qemu.no_shutdown', true);

        $storageController = (string) config('mission-control.qemu.storage.controller', 'ahci');

        $netEnabled = (bool) config('mission-control.qemu.net.enabled', true);
        $netMode = (string) config('mission-control.qemu.net.mode', 'user');
        $netNic = (string) config('mission-control.qemu.net.nic', 'e1000');
        $netDumpEnabled = (bool) config('mission-control.qemu.net.dump_enabled', false);

        $debugConsoleEnabled = (bool) config('mission-control.qemu.logging.debugcon_enabled', true);
        $internalLogEnabled = (bool) config('mission-control.qemu.logging.internal_log_enabled', true);
        $internalLogFlags = (string) config('mission-control.qemu.logging.internal_log_flags', 'guest_errors');

        $extraArgsJson = (string) config('mission-control.qemu.extra_args_json', '[]');

        $bootMode = (string) config('mission-control.qemu.boot_mode', 'disk');
        $cdromImage = trim((string) config('mission-control.qemu.cdrom_image', ''));
        $usbImage = trim((string) config('mission-control.qemu.usb_image', ''));
        $usbFormat = (string) config('mission-control.qemu.usb_format', 'raw');

        $vncArg = $slot->bind_host . ':' . $slot->display . ',websocket=' . $slot->bind_host . ':' . $slot->ws_port;

        @unlink($pidFile);

        $cmd = [
            $qemuBin,
            '-machine', $machine . ',accel=' . $accel,
            '-cpu', $cpu,
            '--enable-kvm',
            '-smp', (string) $smp,
            '-m', (string) $memMb,
            '-drive', 'id=disk,file=' . $overlayPath . ',format=qcow2,if=none',
        ];

        if ($storageController === 'ahci') {
            $cmd[] = '-device';
            $cmd[] = 'ahci,id=ahci';

            $diskBootIndex = 1;
            if ($bootMode === 'cdrom' || $bootMode === 'usb') {
                $diskBootIndex = 2;
            }

            $cmd[] = '-device';
            $cmd[] = 'ide-hd,drive=disk,bus=ahci.0,bootindex=' . $diskBootIndex;
        }

        if ($bootMode === 'cdrom') {
            if ($cdromImage === '') {
                throw new RuntimeException('boot_mode=cdrom but mission-control.qemu.cdrom_image is empty.');
            }

            $cmd[] = '-drive';
            $cmd[] = 'if=none,id=cd0,file=' . $cdromImage . ',media=cdrom,readonly=on';

            if ($storageController === 'ahci') {
                $cmd[] = '-device';
                $cmd[] = 'ide-cd,drive=cd0,bus=ahci.0,bootindex=1';
            }
        }

        if ($bootMode === 'usb') {
            if ($usbImage === '') {
                throw new RuntimeException('boot_mode=usb but mission-control.qemu.usb_image is empty.');
            }

            $cmd[] = '-device';
            $cmd[] = 'qemu-xhci,id=xhci';

            $cmd[] = '-drive';
            $cmd[] = 'if=none,id=usbstick,file=' . $usbImage . ',format=' . $usbFormat;

            $cmd[] = '-device';
            $cmd[] = 'usb-storage,drive=usbstick,bootindex=1';
        }

        if ($noShutdown) {
            $cmd[] = '-no-shutdown';
        }

        if ($bootMode === 'disk') {
            $cmd[] = '-boot';
            $cmd[] = (string) config('mission-control.qemu.boot', 'c');
        }

        $cmd[] = '-vnc';
        $cmd[] = $vncArg;

        if ($debugConsoleEnabled) {
            $cmd[] = '-debugcon';
            $cmd[] = 'file:' . $debugLog;
        }

        if ($netEnabled && $netMode === 'user') {
            $cmd[] = '-netdev';
            $cmd[] = 'user,id=netuser';

            if ($netDumpEnabled) {
                $cmd[] = '-object';
                $cmd[] = 'filter-dump,id=dump,netdev=netuser,file=' . $netDump;
            }

            $cmd[] = '-device';
            $cmd[] = $netNic . ',netdev=netuser';
        }

        if ($internalLogEnabled) {
            $cmd[] = '-D';
            $cmd[] = $qemuInternalLog;

            if ($internalLogFlags !== '') {
                $cmd[] = '-d';
                $cmd[] = $internalLogFlags;
            }
        }

        $cmd[] = '-bios';
        $cmd[] = $ovmf;

        $cmd[] = '-daemonize';
        $cmd[] = '-pidfile';
        $cmd[] = $pidFile;

        $decoded = json_decode($extraArgsJson, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('MISSION_CONTROL_QEMU_EXTRA_ARGS_JSON must be a JSON array.');
        }

        foreach ($decoded as $item) {
            if (!is_string($item) || $item === '') {
                throw new RuntimeException('MISSION_CONTROL_QEMU_EXTRA_ARGS_JSON must contain only non-empty strings.');
            }

            if (in_array($item, ['-daemonize', '-pidfile', '-vnc', '-drive'], true)) {
                throw new RuntimeException('Extra QEMU args may not include: ' . $item);
            }

            $cmd[] = $item;
        }

        $proc = new Process($cmd);
        $proc->setTimeout(20);
        $proc->run(function (string $type, string $buffer) use ($launcherLog): void {
            file_put_contents($launcherLog, $buffer, FILE_APPEND);
        });

        if (!$proc->isSuccessful()) {
            throw new RuntimeException('QEMU failed to launch. See: ' . $launcherLog . ' and ' . $qemuInternalLog);
        }

        for ($i = 0; $i < 40; $i++) {
            if (is_file($pidFile)) {
                $pidText = trim((string) file_get_contents($pidFile));
                if ($pidText !== '' && ctype_digit($pidText)) {
                    $pid = (int) $pidText;
                    if ($pid > 0) {
                        return $pid;
                    }
                }
            }

            usleep(25000);
        }

        throw new RuntimeException('QEMU did not create pidfile: ' . $pidFile . ' (see ' . $launcherLog . ' / ' . $qemuInternalLog . ')');
    }
}
