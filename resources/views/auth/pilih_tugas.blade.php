<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#56A6E8">
    <title>SIJUWARA - Pilih Tugas</title>
    @include('layouts.partials.favicon')
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; }

        body { background: #f0f6ff; }

        .right-panel {
            background: linear-gradient(160deg, #f0f6ff 0%, #e8f2ff 50%, #ddeeff 100%);
            position: relative;
            overflow: hidden;
        }

        .right-panel::before {
            content: '';
            position: absolute;
            top: -120px; right: -120px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(86,166,232,0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .right-panel::after {
            content: '';
            position: absolute;
            bottom: -80px; left: -80px;
            width: 320px; height: 320px;
            background: radial-gradient(circle, rgba(86,166,232,0.12) 0%, transparent 70%);
            border-radius: 50%;
        }

        .role-card {
            display: flex;
            align-items: center;
            gap: 16px;
            width: 100%;
            padding: 15px 16px;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            text-align: left;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .role-card:hover {
            border-color: #56A6E8;
            background: #f8fbff;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(86,166,232,0.18);
        }

        .role-card:focus-visible {
            outline: none;
            border-color: #56A6E8;
            box-shadow: 0 0 0 3px rgba(86,166,232,0.18);
        }

        .role-icon {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(86,166,232,0.18) 0%, rgba(61,143,212,0.10) 100%);
            color: #3d8fd4;
        }

        .role-arrow {
            flex-shrink: 0;
            color: #cbd5e1;
            transition: all 0.2s ease;
        }

        .role-card:hover .role-arrow {
            color: #56A6E8;
            transform: translateX(2px);
        }

        .tagline-box {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.9);
            border-radius: 16px;
            padding: 20px 28px;
            text-align: center;
            max-width: 440px;
        }

        .deco-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(86,166,232,0.08);
        }

        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e2e8f0, transparent);
            margin: 24px 0;
        }

        .alert-error {
            background: #fef2f2;
            border-left: 3px solid #f87171;
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body class="min-h-screen flex">

    <!-- ========== LEFT SIDE ========== -->
    <div class="w-1/2 flex items-center justify-center bg-white px-16 py-12">

        <div class="w-full max-w-[400px]">

            <!-- Logo above form -->
            <div class="mb-10">
                <img
                    src="{{ asset('img/logowtext.png') }}"
                    alt="SIJUWARA - Sistem Jurnal Siswa Aktif"
                    class="h-12 w-auto object-contain"
                >
            </div>

            <!-- Heading -->
            <div class="mb-7">
                <h2 class="text-[26px] text-[#0f172a] leading-tight mb-2" style="font-weight:800;">
                    {{ $sudahMasuk ? 'Ganti Tugas' : 'Pilih Tugas Anda' }}
                </h2>
                <p class="text-sm text-slate-400 leading-relaxed">
                    Halo, <span class="font-semibold text-slate-500">{{ $nama }}</span>.
                    {{ $sudahMasuk
                        ? 'Pilih tugas yang ingin Anda gunakan sekarang.'
                        : 'Akun Anda memiliki lebih dari satu tugas. Pilih salah satu untuk membuka dasbor yang sesuai.' }}
                </p>
            </div>

            <!-- Alerts -->
            @if ($errors->any())
                <div class="alert-error" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <!-- Daftar tugas (tombol interaktif) -->
            <div class="space-y-3">
                @foreach ($tugas as $t)
                    <form method="POST"
                        action="{{ $sudahMasuk ? route('tugas.ganti') : route('pilih_tugas.submit') }}">
                        @csrf
                        <input type="hidden" name="role" value="{{ $t['kode'] }}">

                        <button type="submit" class="role-card">
                            <span class="role-icon">
                                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                    @switch($t['kode'])
                                        @case(\App\Models\User::ROLE_WAKASEK)
                                            <path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z" />
                                            <path d="M9 12l2 2 4-4" />
                                            @break
                                        @case(\App\Models\User::ROLE_GURU_BK)
                                            <path d="M12 20s-6-3.8-6-8a3.5 3.5 0 016-2.2A3.5 3.5 0 0118 12c0 4.2-6 8-6 8z" />
                                            @break
                                        @case(\App\Models\User::ROLE_WALIKELAS)
                                            <circle cx="9" cy="8" r="3" />
                                            <path d="M3 20a6 6 0 0112 0" />
                                            <path d="M16 5.5a3 3 0 010 5.5" />
                                            <path d="M18 20a5 5 0 00-3-4.6" />
                                            @break
                                        @case(\App\Models\User::ROLE_KETUA_PROGRAM)
                                            <path d="M3 9l9-4 9 4-9 4-9-4z" />
                                            <path d="M7 11.5V16c0 1.7 2.2 3 5 3s5-1.3 5-3v-4.5" />
                                            @break
                                        @default
                                            <rect x="4" y="10" width="16" height="10" rx="2" />
                                            <path d="M8 10V7a4 4 0 118 0v3" />
                                    @endswitch
                                </svg>
                            </span>

                            <span class="flex-1 min-w-0">
                                <span class="block text-[15px] font-bold text-[#0f172a]">{{ $t['label'] }}</span>
                                @if (!empty($t['detail']))
                                    <span class="block text-xs text-slate-400 mt-0.5">{{ $t['detail'] }}</span>
                                @endif
                            </span>

                            <svg class="role-arrow" width="18" height="18" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path d="M9 6l6 6-6 6" />
                            </svg>
                        </button>
                    </form>
                @endforeach
            </div>

            <div class="divider"></div>

            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-slate-400">
                    Tugas aktif dapat diganti kapan saja tanpa login ulang.
                </p>
                <a href="{{ route('logout') }}"
                    class="flex-shrink-0 text-xs font-semibold transition-colors hover:text-[#3d8fd4]"
                    style="color:#56A6E8;">
                    Ganti akun
                </a>
            </div>

            <p class="text-center text-[11px] text-slate-300 mt-6">
                &copy; {{ date('Y') }} SIJUWARA &middot; Sistem Jurnal Siswa Aktif
            </p>

        </div>
    </div>

    <!-- ========== RIGHT SIDE ========== -->
    <div class="w-1/2 right-panel flex flex-col items-center justify-center px-10 py-10 relative z-10">

        <!-- Decorative circles -->
        <div class="deco-circle w-64 h-64" style="top:-60px;right:-40px;"></div>
        <div class="deco-circle w-48 h-48" style="bottom:-30px;left:-20px;"></div>
        <div class="deco-circle w-20 h-20" style="top:30%;left:8%;background:rgba(86,166,232,0.06);"></div>

        <!-- Mockup Image -->
        <div class="mb-7 relative z-10 w-full flex items-center justify-center"
            style="filter: drop-shadow(0 20px 40px rgba(86,166,232,0.25));">
            <img
                src="{{ asset('img/image.png') }}"
                alt="App Mockup"
                class="w-full max-w-[520px] object-contain"
            >
        </div>

        <!-- Tagline Card -->
        <div class="tagline-box relative z-10">
            <h3 class="font-bold text-[17px] mb-2" style="color:#56A6E8;">
                Satu Akun, Banyak Peran
            </h3>
            <p class="text-slate-500 text-sm leading-relaxed">
                Pilih tugas yang sedang Anda jalankan agar menu dan data yang tampil sesuai dengan tanggung jawab Anda.
            </p>
        </div>

    </div>

</body>
</html>
