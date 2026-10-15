<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\guru_bk;
use App\Models\User;
use App\Models\wakasek;
use App\Models\ketua_program;
use App\Models\walikelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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

        $role = null;
        $user = null;

        $wakasek = wakasek::where('nip_wakasek', $request->nip)->first();
        if ($wakasek) {
            $user = User::where('username', $wakasek->username)->first();
            $role = 'wakasek';
        }

        if (!$user) {
            $guru_bk = guru_bk::where('nip_bk', $request->nip)->first();
            if ($guru_bk) {
                $user = User::where('username', $guru_bk->username)->first();
                $role = 'guru_bk';
            }
        }

        if (!$user) {
            $ketua_program = ketua_program::where('nip_kaprog', $request->nip)->first();
            if ($ketua_program) {
                $user = User::where('username', $ketua_program->username)->first();
                $role = 'ketua_program';
            }
        }

        if (!$user) {
            $walikelas = walikelas::where('nip_walikelas', $request->nip)->first();
            if ($walikelas) {
                $user = User::where('username', $walikelas->username)->first();
                $role = 'walikelas';
            }
        }

        // Super Admin tidak punya tabel profil: login memakai username pada kolom yang sama.
        if (!$user) {
            $user = User::where('username', $request->nip)
                ->where('role', User::ROLE_SUPER_ADMIN)
                ->first();
            $role = $user ? 'super_admin' : null;
        }

        if (!$user) {
            return back()->withErrors(['nip' => 'NIP tidak ditemukan']);
        }

        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Password salah']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->isSuperAdmin()) {
            return redirect()->route('superadmin.dashboard')->with('success', 'Berhasil login');
        }

        return redirect()->route('wakasek.dashboard')->with('success', 'Berhasil login');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }
}