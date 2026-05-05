<?php

namespace Database\Seeders;

use App\Models\Camera;
use App\Models\Shift;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationNotification;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * ViolationSeeder — Demo data untuk reviewer.
 *
 * Menghasilkan violations di semua state (pending / validated / rejected / reported)
 * dan semua kombinasi tipe (APD / discipline), level (major / minor), zona, shift.
 *
 * Distribusi:
 *   - 8  violations status = pending     → Manager bisa langsung coba validasi
 *   - 6  violations status = validated   → HR bisa langsung coba generate report
 *   - 4  violations status = rejected    → False positive — tidak masuk laporan
 *   - 5  violations status = reported    → Sudah masuk laporan HR sebelumnya
 *
 * Total: 23 violations lintas 2 zona, 2 shift, 3 APD label + discipline.
 */
class ViolationSeeder extends Seeder
{
    public function run(): void
    {
        $manager  = User::where('role', 'manager')->first();
        $cam1     = Camera::where('dvr_channel', 'CH-01')->first(); // Forklift
        $cam2     = Camera::where('dvr_channel', 'CH-02')->first(); // Empty Box
        $shift1   = Shift::where('name', 'Shift 1')->first();      // 07:00–15:00
        $shift2   = Shift::where('name', 'Shift 2')->first();      // 15:00–23:00

        $now = Carbon::now();

        // ─────────────────────────────────────────────────────────────────────
        // HELPER — buat violation + notif sekaligus
        // ─────────────────────────────────────────────────────────────────────
        $make = function (array $attr) use ($manager): Violation {
            $v = Violation::create($attr);

            // Semua violations mendapat notif alert_manager
            ViolationNotification::create([
                'violation_id' => $v->id,
                'recipient_id' => null,
                'channel'      => 'telegram',
                'type'         => 'alert_manager',
                'status'       => 'sent',
                'sent_at'      => $v->detected_at,
            ]);

            // Violations yang sudah validated/reported juga dapat notif notify_hr
            if (in_array($v->status, ['validated', 'reported'])) {
                ViolationNotification::create([
                    'violation_id' => $v->id,
                    'recipient_id' => null,
                    'channel'      => 'telegram',
                    'type'         => 'notify_hr',
                    'status'       => 'sent',
                    'sent_at'      => $v->validated_at,
                ]);
            }

            return $v;
        };

        // ─────────────────────────────────────────────────────────────────────
        // PENDING — 8 violations
        // Manager login → bisa langsung validasi / tolak
        // ─────────────────────────────────────────────────────────────────────
        $pendingData = [
            // Forklift, Shift 1, APD major
            ['camera_id' => $cam1->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_helmet', 'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subHours(1)],
            ['camera_id' => $cam1->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_boots',  'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subHours(2)],
            // Forklift, Shift 1, APD minor
            ['camera_id' => $cam1->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_vest',   'level' => 'minor', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subHours(3)],
            // Empty Box, Shift 2, APD
            ['camera_id' => $cam2->id, 'shift_id' => $shift2->id, 'violation_type' => 'apd',       'apd_label' => 'no_vest',   'level' => 'minor', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subHours(4)],
            ['camera_id' => $cam2->id, 'shift_id' => $shift2->id, 'violation_type' => 'apd',       'apd_label' => 'no_boots',  'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subHours(5)],
            // Forklift, discipline (orang di luar shift)
            ['camera_id' => $cam1->id, 'shift_id' => null,        'violation_type' => 'discipline', 'apd_label' => null,        'level' => null,    'is_outside_shift' => true,  'detected_at' => $now->copy()->subHours(6)],
            // Hari kemarin
            ['camera_id' => $cam1->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_helmet', 'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(1)->setTime(9, 15)],
            ['camera_id' => $cam2->id, 'shift_id' => null,        'violation_type' => 'discipline', 'apd_label' => null,        'level' => null,    'is_outside_shift' => true,  'detected_at' => $now->copy()->subDays(1)->setTime(23, 45)],
        ];

        foreach ($pendingData as $d) {
            $make(array_merge($d, [
                'confidence' => round(rand(70, 97) / 100, 4),
                'image_path' => 'violations/demo-' . rand(1, 5) . '.jpg',
                'status'     => 'pending',
            ]));
        }

        // ─────────────────────────────────────────────────────────────────────
        // VALIDATED — 6 violations
        // HR login → bisa langsung generate report untuk periode ini
        // ─────────────────────────────────────────────────────────────────────
        $validatedData = [
            ['camera_id' => $cam1->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_helmet', 'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(2)->setTime(8, 0),  'person_name' => 'Budi Santoso',   'validation_notes' => 'Helmet terlepas saat mengambil barang.'],
            ['camera_id' => $cam1->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_boots',  'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(2)->setTime(10, 0), 'person_name' => 'Agus Purnomo',   'validation_notes' => 'Memakai sandal biasa, bukan safety boots.'],
            ['camera_id' => $cam1->id, 'shift_id' => $shift2->id, 'violation_type' => 'apd',       'apd_label' => 'no_vest',   'level' => 'minor', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(3)->setTime(16, 0), 'person_name' => 'Eko Prasetyo',   'validation_notes' => null],
            ['camera_id' => $cam2->id, 'shift_id' => $shift2->id, 'violation_type' => 'apd',       'apd_label' => 'no_boots',  'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(3)->setTime(17, 0), 'person_name' => 'Hendra Wijaya',  'validation_notes' => 'Sudah diperingatkan 2x sebelumnya.'],
            ['camera_id' => $cam2->id, 'shift_id' => null,        'violation_type' => 'discipline', 'apd_label' => null,        'level' => null,    'is_outside_shift' => true,  'detected_at' => $now->copy()->subDays(4)->setTime(2, 30),  'person_name' => 'Ahmad Fauzi',    'validation_notes' => 'Terdeteksi di area pabrik pukul 02:30, di luar jam operasional.'],
            ['camera_id' => $cam1->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_helmet', 'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(5)->setTime(9, 0),  'person_name' => 'Slamet Riyadi',  'validation_notes' => null],
        ];

        foreach ($validatedData as $d) {
            $detectedAt = $d['detected_at'];
            $validatedAt = (clone $detectedAt)->addMinutes(rand(15, 120));
            $notes  = $d['validation_notes'];
            $person = $d['person_name'];
            unset($d['validation_notes'], $d['person_name']);

            $make(array_merge($d, [
                'confidence'       => round(rand(75, 98) / 100, 4),
                'image_path'       => 'violations/demo-' . rand(1, 5) . '.jpg',
                'status'           => 'validated',
                'validated_by'     => $manager->id,
                'validated_at'     => $validatedAt,
                'person_name'      => $person,
                'validation_notes' => $notes,
            ]));
        }

        // ─────────────────────────────────────────────────────────────────────
        // REJECTED — 4 violations (false positive)
        // ─────────────────────────────────────────────────────────────────────
        $rejectedData = [
            ['camera_id' => $cam1->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_helmet', 'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(6)->setTime(10, 0),  'validation_notes' => 'False positive — orang sedang menyimpan helm sebentar.'],
            ['camera_id' => $cam2->id, 'shift_id' => $shift2->id, 'violation_type' => 'apd',       'apd_label' => 'no_vest',   'level' => 'minor', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(7)->setTime(15, 30), 'validation_notes' => 'False positive — rompi terlihat putih oleh kamera (saturasi tinggi).'],
            ['camera_id' => $cam1->id, 'shift_id' => null,        'violation_type' => 'discipline', 'apd_label' => null,        'level' => null,    'is_outside_shift' => true,  'detected_at' => $now->copy()->subDays(7)->setTime(1, 0),   'validation_notes' => 'False positive — petugas keamanan ronde malam.'],
            ['camera_id' => $cam1->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_boots',  'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(8)->setTime(13, 0),  'validation_notes' => 'False positive — bayangan tiang menghalangi gambar.'],
        ];

        foreach ($rejectedData as $d) {
            $notes = $d['validation_notes'];
            unset($d['validation_notes']);

            $make(array_merge($d, [
                'confidence'       => round(rand(70, 78) / 100, 4),
                'image_path'       => 'violations/demo-' . rand(1, 5) . '.jpg',
                'status'           => 'rejected',
                'validated_by'     => $manager->id,
                'validated_at'     => $d['detected_at']->copy()->addMinutes(rand(10, 60)),
                'validation_notes' => $notes,
            ]));
        }

        // ─────────────────────────────────────────────────────────────────────
        // REPORTED — 5 violations (sudah diproses HR)
        // Dashboard KPI akan menghitung ini juga
        // ─────────────────────────────────────────────────────────────────────
        $reportedData = [
            ['camera_id' => $cam1->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_helmet', 'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(15)->setTime(8, 0),  'person_name' => 'Doni Susanto'],
            ['camera_id' => $cam1->id, 'shift_id' => $shift2->id, 'violation_type' => 'apd',       'apd_label' => 'no_vest',   'level' => 'minor', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(15)->setTime(16, 0), 'person_name' => 'Rudi Hariyanto'],
            ['camera_id' => $cam2->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_boots',  'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(20)->setTime(9, 0),  'person_name' => 'Teguh Wibowo'],
            ['camera_id' => $cam2->id, 'shift_id' => null,        'violation_type' => 'discipline', 'apd_label' => null,        'level' => null,    'is_outside_shift' => true,  'detected_at' => $now->copy()->subDays(20)->setTime(3, 0),  'person_name' => 'Satpam Area B'],
            ['camera_id' => $cam1->id, 'shift_id' => $shift1->id, 'violation_type' => 'apd',       'apd_label' => 'no_helmet', 'level' => 'major', 'is_outside_shift' => false, 'detected_at' => $now->copy()->subDays(25)->setTime(10, 0), 'person_name' => 'Wahyu Nugroho'],
        ];

        foreach ($reportedData as $d) {
            $person = $d['person_name'];
            unset($d['person_name']);

            $make(array_merge($d, [
                'confidence'   => round(rand(80, 97) / 100, 4),
                'image_path'   => 'violations/demo-' . rand(1, 5) . '.jpg',
                'status'       => 'reported',
                'validated_by' => $manager->id,
                'validated_at' => $d['detected_at']->copy()->addHours(2),
                'person_name'  => $person,
            ]));
        }
    }
}