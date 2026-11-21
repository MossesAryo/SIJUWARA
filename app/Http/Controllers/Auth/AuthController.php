<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\guru_bk;
use App\Models\ketua_program;
use App\Models\User;
use App\Models\wakasek;
use App\Models\walikelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Kunci session untuk akun yang sudah lolos kata sandi tetapi belum memilih tugas.
     * /pilih-tugas berada di luar middleware auth: pengguna baru benar-benar login
     * setelah salah satu tugas dipilih.
     */
    private const SESSION_PENDING = 'auth.pilih_tugas';

    public function index()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'nip' => 'required',
            'password' => 'required',
        ]);

        $user = $this->cariPengguna((string) $request->nip);

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withInput($request->only('nip'))->withErrors(['nip' => 'NIP atau password salah']);
        }

        // Akun dengan lebih dari satu tugas memilih tugas dulu sebelum masuk dashboard.
        if ($user->punyaBanyakTugas()) {
            $request->session()->put(self::SESSION_PENDING, $user->id);

            return redirect()->route('pilih_tugas');
        }

        return $this->masuk($request, $user);
    }

    /**
     * Halaman pilihan tugas (role selection screen).
     */
    public function pilihTugas(Request $request)
    {
        $user = $this->penggunaPilihTugas($request);

        if (!$user) {
            return redirect()->route('login');
        }

        // Tugas tunggal tidak perlu dipilih (mis. URL dibuka langsung).
        if (!$user->punyaBanyakTugas()) {
            return Auth::check()
                ? redirect()->route($user->ruteDashboard())
                : $this->masuk($request, $user);
        }

        return view('auth.pilih_tugas', [
            'nama'       => $user->namaTampil(),
            'tugas'      => $user->daftarTugas(),
            'sudahMasuk' => Auth::check(),
        ]);
    }

    /**
     * Simpan tugas yang dipilih setelah kata sandi benar.
     */
    public function simpanPilihTugas(Request $request)
    {
        $request->validate(['role' => ['required', 'integer']]);

        $user = $this->penggunaPilihTugas($request);

        if (!$user) {
            return redirect()
                ->route('login')
                ->withErrors(['nip' => 'Sesi login berakhir. Silakan masuk kembali.']);
        }

        $kode = $this->tugasValid($user, (int) $request->input('role'));

        if ($kode === null) {
            return back()->withErrors(['role' => 'Tugas yang dipilih tidak tersedia untuk akun Anda.']);
        }

        // Halaman bisa dibuka dari dalam aplikasi (ganti tugas).
        if (Auth::check()) {
            return $this->gantiKeTugas($user, $kode);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_PENDING);

        $this->aktifkanTugas($user, $kode);

        return $this->redirectDashboard($user);
    }

    /**
     * Ganti tugas aktif tanpa login ulang.
     */
    public function gantiTugas(Request $request)
    {
        $request->validate(['role' => ['required', 'integer']]);

        $user = $request->user();
        $kode = $this->tugasValid($user, (int) $request->input('role'));

        if ($kode === null) {
            return back()->withErrors(['role' => 'Tugas yang dipilih tidak tersedia untuk akun Anda.']);
        }

        return $this->gantiKeTugas($user, $kode);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Cari akun dari NIP. NIP unik lintas tabel jabatan, urutannya sama dengan
     * urutan lama: Wakasek, Guru BK, Ketua Program, Wali Kelas, lalu Super Admin.
     */
    private function cariPengguna(string $nip): ?User
    {
        $sumber = [
            [wakasek::class, 'nip_wakasek'],
            [guru_bk::class, 'nip_bk'],
            [ketua_program::class, 'nip_kaprog'],
            [walikelas::class, 'nip_walikelas'],
        ];

        foreach ($sumber as [$model, $kolom]) {
            $profil = $model::where($kolom, $nip)->first();

            if ($profil && $user = User::where('username', $profil->username)->first()) {
                return $user;
            }
        }

        // Super Admin tidak punya tabel profil: login memakai username pada kolom yang sama.
        return User::where('username', $nip)
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->first();
    }

    /** Login sebagai pengguna dan buka dashboard tugas aktifnya. */
    private function masuk(Request $request, User $user)
    {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_PENDING);

        $this->aktifkanTugas($user);

        return $this->redirectDashboard($user);
    }

    /**
     * Pengguna yang sedang memilih tugas: pengguna yang sudah login, atau akun
     * yang tersimpan di session setelah kata sandi benar.
     */
    private function penggunaPilihTugas(Request $request): ?User
    {
        if ($user = $request->user()) {
            return $user;
        }

        $id = $request->session()->get(self::SESSION_PENDING);

        return $id ? User::find($id) : null;
    }

    /** Kode tugas bila benar-benar dipegang akun, atau null. */
    private function tugasValid(User $user, int $kode): ?int
    {
        return in_array($kode, $user->kodeTugas(), true) ? $kode : null;
    }

    /** Simpan tugas aktif lalu arahkan ke dashboard tugas tersebut. */
    private function gantiKeTugas(User $user, int $kode)
    {
        $this->aktifkanTugas($user, $kode);

        return $this->redirectDashboard($user, 'Tugas aktif diganti ke ' . $user->labelRole($kode) . '.');
    }

    /** Arahkan ke dashboard sesuai tugas aktif. */
    private function redirectDashboard(User $user, ?string $pesan = null)
    {
        $redirect = redirect()->route($user->ruteDashboard());

        return $pesan
            ? $redirect->with('success', $pesan)
            : $redirect->with('success', 'Berhasil login sebagai ' . $user->labelRole((int) $user->role) . '.');
    }

    /**
     * Pastikan kolom users.role berisi tugas yang benar-benar dipegang pengguna.
     */
    private function aktifkanTugas(User $user, ?int $kode = null): void
    {
        $dipegang = $user->kodeTugas();
        $aktif = $kode ?? (int) $user->role;

        if (!in_array($aktif, $dipegang, true)) {
            $aktif = $dipegang[0] ?? (int) $user->role;
        }

        if ((int) $user->role !== $aktif) {
            $user->role = $aktif;
            $user->save();
        }
    }
}
