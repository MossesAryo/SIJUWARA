<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="nbPE5Fm6_GATYTsRoFTUinYGMDkkqDuTy5dO5z6VICw" />
     <meta name="description"
        content="Masuk ke SIJUWARA – Sistem Jurnal Siswa Aktif untuk mencatat, menilai, dan melaporkan aktivitas siswa secara digital. Platform manajemen jurnal sekolah yang modern dan responsif.">
    <meta name="keywords"
        content="SIJUWARA, sistem jurnal siswa aktif, jurnal siswa, manajemen sekolah, penilaian siswa, monitoring siswa, laporan siswa, aplikasi sekolah, login sekolah">
    <meta name="author" content="SIJUWARA">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="theme-color" content="#56A6E8">
    <link rel="canonical" href="{{ url()->current() }}">
 
    <!-- ===== OPEN GRAPH (Facebook, WhatsApp, LinkedIn) ===== -->
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="SIJUWARA">
    <meta property="og:title" content="Login SIJUWARA – Sistem Jurnal Siswa Aktif">
    <meta property="og:description"
        content="Masuk ke SIJUWARA untuk mencatat, menilai, dan melaporkan aktivitas siswa. Platform manajemen jurnal sekolah yang modern dan mudah digunakan.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('storage/assets/logowtext.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="SIJUWARA – Sistem Jurnal Siswa Aktif">
 
    <!-- ===== TWITTER / X CARD ===== -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Login SIJUWARA – Sistem Jurnal Siswa Aktif">
    <meta name="twitter:description"
        content="Masuk ke SIJUWARA untuk mencatat, menilai, dan melaporkan aktivitas siswa secara digital.">
    <meta name="twitter:image" content="{{ asset('storage/assets/logowtext.png') }}">
    <meta name="twitter:image:alt" content="SIJUWARA – Sistem Jurnal Siswa Aktif">
    <title>SIJUWARA - Login</title>
    @include('layouts.partials.favicon')
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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

        .input-field {
            width: 100%;
            padding: 12px 16px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            color: #1e293b;
            transition: all 0.2s ease;
            outline: none;
        }

        .input-field:focus {
            border-color: #56A6E8;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(86,166,232,0.1);
        }

        .input-field::placeholder { color: #94a3b8; }

        .sign-in-btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #56A6E8 0%, #3d8fd4 100%);
            color: white;
            font-weight: 700;
            font-size: 15px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(86,166,232,0.35);
        }

        .sign-in-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(86,166,232,0.45);
        }

        .mockup-img {
            filter: drop-shadow(0 20px 40px rgba(86,166,232,0.25));
        }

        .label-text {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            display: block;
        }

        .deco-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(86,166,232,0.08);
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

<body class="min-h-screen flex" x-data="{ showPassword: false }">

    <!-- ========== LEFT SIDE ========== -->
    <div class="w-1/2 flex items-center justify-center bg-white px-16 py-12">

        <div class="w-full max-w-[360px]">

            <!-- Logo above form -->
            <div class="mb-10">
                <img
                    src="{{ asset('img/logowtext.png') }}"
                    alt="SIJUWARA - Sistem Jurnal Siswa Aktif"
                    class="h-12 w-auto object-contain"
                >
            </div>

            <!-- Heading -->
            <div class="mb-8">
                <h2 class="text-[26px] text-[#0f172a] leading-tight mb-2" style="font-weight:800;">
                    Selamat Datang Kembali
                </h2>
                <p class="text-sm text-slate-400 leading-relaxed">
                    Silakan masukkan detail anda untuk mengakses dasbor
                </p>
            </div>

            <!-- Alerts -->
            @if (session('error'))
                <div class="alert-error" role="alert">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert-error" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <!-- Form -->
            <form method="POST" action="{{ route('login.submit') }}" class="space-y-5">
                @csrf

                <!-- NIP -->
                <div>
                    <label class="label-text">NIP</label>
                    <input
                        type="text"
                        name="nip"
                        value="{{ old('nip') }}"
                        placeholder="192837363555355"
                        class="input-field"
                        required
                    >
                </div>

                <!-- Password -->
                <div>
                    <label class="label-text">Password</label>
                    <div class="relative">
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            placeholder="••••••••"
                            class="input-field"
                            style="padding-right: 44px;"
                            required
                        >
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-[#56A6E8] transition-colors"
                        >
                            <svg x-show="!showPassword" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showPassword" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" class="sign-in-btn">
                    Sign In
                </button>

            </form>

            <div class="divider"></div>
            <p class="text-center text-xs text-slate-400">
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

        <!-- Mockup Image — bigger, no animation -->
        <div class="mockup-img mb-7 relative z-10 w-full flex items-center justify-center">
            <img
                src="{{ asset('img/image.png') }}"
                alt="App Mockup"
                class="w-full max-w-[520px] object-contain"
            >
        </div>

        <!-- Tagline Card -->
        <div class="tagline-box relative z-10">
            <h3 class="font-bold text-[17px] mb-2" style="color:#56A6E8;">
                Sistem Jurnal Siswa Aktif
            </h3>
            <p class="text-slate-500 text-sm leading-relaxed">
                Mulai dari pencatatan, penilaian, hingga laporan — semuanya ada di satu aplikasi.
            </p>
        </div>

    </div>

</body>
</html>