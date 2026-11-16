<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Profil pengguna.
 * Tampilkan dan perbarui data profil akun.
 */
class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        return view('wakasek.profile.profilewakasek', compact('user'));
    }
    public function updateProfile(Request $request, )
    {
       $data = $request->validate([
        'nama_wakasek' => 'required|string|max:255',
        'nip_wakasek' => ['required', 'regex:/^\d{5,18}$/'],
        'email' => 'required|email',
    ], [
        'nip_wakasek.regex' => 'NIP harus berupa angka 5 sampai 18 digit.',
    ]);

    $user = auth()->user();

    if (\App\Support\AkunStaf::nipTerpakaiOlehLain($data['nip_wakasek'], $user->wakasek->nip_wakasek)) {
        return redirect()->back()->withInput()
            ->withErrors(['nip_wakasek' => 'NIP sudah dipakai oleh pengguna lain.']);
    }

    $user->update([
        'email' => $data['email'],
    ]);

    $user->wakasek->update([
        'nama_wakasek' => $data['nama_wakasek'],
        'nip_wakasek' => $data['nip_wakasek'],
    ]);

       return redirect()->back()->with('success', 'Profil berhasil diperbarui.');
    }

   
}