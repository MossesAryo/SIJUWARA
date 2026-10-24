<?php

namespace App\Http\Controllers\AuthAPI;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\walikelas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthAPIcontroller extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'nip'      => 'required|string',
            'password' => 'required|string',
        ]);

        $wk = walikelas::where('nip_walikelas', $request->nip)->first();

        if (!$wk) {
            return response()->json([
                'status'  => false,
                'message' => 'NIP atau password salah',
            ], 401);
        }

        $user = User::where('username', $wk->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'NIP atau password salah',
            ], 401);
        }

        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'status' => true,
            'role'   => 3,
            'token'  => $token,
            'user'   => [
                'username' => $user->username,
                'email'    => $user->email,
            ],
            'detail' => $wk->toArray(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['status' => true, 'message' => 'Berhasil logout']);
    }
}