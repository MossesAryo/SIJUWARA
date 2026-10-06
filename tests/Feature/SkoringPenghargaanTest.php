<?php

namespace Tests\Feature;

use App\Models\aspek_penilaian;
use App\Models\jurusan;
use App\Models\kelas;
use App\Models\penilaian;
use App\Models\siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SkoringPenghargaanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: siswa, 2: aspek_penilaian}
     */
    private function buatData(): array
    {
        $user = User::create([
            'username' => 'wakasek1',
            'email' => 'wakasek1@example.com',
            'role' => 1,
            'password' => 'password',
        ]);

        DB::table('wakasek')->insert([
            'nip_wakasek' => 111,
            'username' => 'wakasek1',
            'nama_wakasek' => 'Wakasek Satu',
        ]);

        jurusan::create(['id_jurusan' => 'RPL', 'nama_jurusan' => 'Rekayasa Perangkat Lunak']);
        kelas::create(['id_kelas' => 'X-RPL-1', 'nama_kelas' => 'X RPL 1', 'id_jurusan' => 'RPL']);

        $siswa = siswa::create([
            'nis' => 1001,
            'id_kelas' => 'X-RPL-1',
            'id_jurusan' => 'RPL',
            'nama_siswa' => 'Budi',
            'status' => 'aktif',
            'poin_apresiasi' => 0,
            'poin_pelanggaran' => 0,
            'poin_total' => 0,
        ]);

        $aspek = aspek_penilaian::create([
            'jenis_poin' => 'Apresiasi',
            'kategori' => 'Prestasi',
            'uraian' => 'Juara 1 Lomba',
            'indikator_poin' => 10,
        ]);

        return [$user, $siswa, $aspek];
    }

    public function test_double_submit_hanya_menyimpan_satu_penghargaan(): void
    {
        [$user, $siswa, $aspek] = $this->buatData();

        $payload = [
            'nis' => $siswa->nis,
            'id_aspekpenilaian' => $aspek->id_aspekpenilaian,
        ];

        $url = route('siswa.skoringPenghargaan', ['nis' => $siswa->nis]);

        $this->actingAs($user)->post($url, $payload);
        $this->actingAs($user)->post($url, $payload);

        $this->assertSame(1, penilaian::count(), 'Penghargaan ganda harus ditolak.');
        $this->assertEquals(10, $siswa->fresh()->poin_apresiasi, 'Poin hanya ditambah sekali.');
        $this->assertEquals(10, $siswa->fresh()->poin_total, 'Poin total hanya ditambah sekali.');
    }

    public function test_penghargaan_berbeda_tetap_bisa_ditambahkan(): void
    {
        [$user, $siswa, $aspek] = $this->buatData();

        $aspekLain = aspek_penilaian::create([
            'jenis_poin' => 'Apresiasi',
            'kategori' => 'Prestasi',
            'uraian' => 'Juara 2 Lomba',
            'indikator_poin' => 5,
        ]);

        $url = route('siswa.skoringPenghargaan', ['nis' => $siswa->nis]);

        $this->actingAs($user)->post($url, [
            'nis' => $siswa->nis,
            'id_aspekpenilaian' => $aspek->id_aspekpenilaian,
        ]);
        $this->actingAs($user)->post($url, [
            'nis' => $siswa->nis,
            'id_aspekpenilaian' => $aspekLain->id_aspekpenilaian,
        ]);

        $this->assertSame(2, penilaian::count(), 'Penghargaan berbeda harus tetap tersimpan.');
        $this->assertEquals(15, $siswa->fresh()->poin_total);
    }
}
