<?php

namespace Tests\Unit;

use App\Models\QcInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Memastikan PIN PIC QC selalu tersimpan ter-hash dan hanya diverifikasi
 * lewat Hash::check (tidak ada lagi jalur perbandingan teks biasa).
 */
class QcInspectorPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_pin_tersimpan_terhash_dan_diverifikasi_dengan_benar(): void
    {
        $inspector = QcInspector::create([
            'employee_id' => 'QC-01',
            'name' => 'Inspektur A',
            'pin' => '123456',
            'status' => 'aktif',
        ]);

        // PIN tidak boleh tersimpan sebagai teks biasa.
        $this->assertNotSame('123456', $inspector->pin);
        // PIN benar diterima, PIN salah ditolak.
        $this->assertTrue($inspector->verifyPin('123456'));
        $this->assertFalse($inspector->verifyPin('000000'));
    }

    public function test_pin_plaintext_lama_tidak_lagi_diterima(): void
    {
        $inspector = QcInspector::create([
            'employee_id' => 'QC-02',
            'name' => 'Inspektur B',
            'pin' => '654321',
            'status' => 'aktif',
        ]);

        // Paksa nilai plaintext langsung ke DB (melewati setter) untuk meniru data lama.
        DB::table('qc_inspectors')->where('id', $inspector->id)->update(['pin' => '654321']);
        $inspector->refresh();

        // Setelah fallback dicabut, PIN teks biasa harus ditolak.
        $this->assertFalse($inspector->verifyPin('654321'));
    }
}
