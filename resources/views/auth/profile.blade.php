@extends('layouts.wakasek.app')
@push('css')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }

        :root {
            --primary: #0083ee;
            --primary-dark: #0066cc;
            --primary-light: #339eff;
            --primary-ultra-light: rgba(0, 131, 238, 0.1);
            --surface: #ffffff;
            --surface-alt: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --shadow: rgba(15, 23, 42, 0.08);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            position: relative;
            border-radius: 20px;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .profile-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 150%;
            height: 150%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 60%);
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        .profile-card {
            background: var(--surface);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            box-shadow: 0 20px 60px var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }

        .avatar-container {
            position: relative;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            box-shadow: 
                0 0 0 6px var(--surface),
                0 0 0 8px var(--primary-ultra-light),
                0 20px 60px rgba(0, 131, 238, 0.3);
        }

        .avatar-container::before {
            content: '';
            position: absolute;
            inset: -4px;
            background: linear-gradient(45deg, var(--primary), transparent, var(--primary-light));
            border-radius: inherit;
            z-index: -1;
            animation: rotate 3s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .stats-card {
            background: var(--surface);
            border: 1px solid var(--border);
            box-shadow: 0 4px 20px var(--shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .stats-card:hover::before {
            transform: scaleX(1);
        }

        .stats-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 131, 238, 0.15);
        }

        .info-item {
            transition: all 0.3s ease;
            border-radius: 16px;
            border: 1px solid transparent;
        }

        .info-item:hover {
            background: var(--primary-ultra-light);
            border-color: rgba(0, 131, 238, 0.2);
            transform: translateX(8px);
        }

        .badge-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 131, 238, 0.3);
        }

        .badge-success {
            background: linear-gradient(135deg, var(--success), #22d3ee);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--surface), var(--surface-alt));
            border: none;
            color: var(--primary);
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .btn-primary:hover::before {
            opacity: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 131, 238, 0.4);
        }

        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 131, 238, 0.3);
        }

        .activity-item {
            transition: all 0.3s ease;
            border-radius: 16px;
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .activity-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            transform: scaleY(0);
            transform-origin: bottom;
            transition: transform 0.3s ease;
        }

        .activity-item:hover::before {
            transform: scaleY(1);
        }

        .activity-item:hover {
            background: var(--primary-ultra-light);
            border-color: rgba(0, 131, 238, 0.15);
            transform: translateX(8px);
        }

        .floating-action {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 8px 30px rgba(0, 131, 238, 0.4);
            transition: all 0.3s ease;
            z-index: 100;
        }

        .floating-action:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 40px rgba(0, 131, 238, 0.5);
        }

        .notification-dot {
            width: 12px;
            height: 12px;
            background: var(--success);
            border: 3px solid var(--surface);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { 
                opacity: 1; 
                transform: scale(1);
            }
            50% { 
                opacity: 0.8; 
                transform: scale(1.2);
            }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .profile-header {
                padding: 2rem 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .floating-action {
                width: 50px;
                height: 50px;
                bottom: 1rem;
                right: 1rem;
            }
        }

        .section-header {
            position: relative;
            padding-bottom: 0.5rem;
        }

        .section-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 2px;
        }
    </style>
@endpush

@section('content')
    <!-- Profile Header -->
    <div class="profile-header text-white py-16 px-4 relative z-10">
        <div class="max-w-6xl mx-auto">
            <div class="flex flex-col md:flex-row items-center gap-8">
                <!-- Avatar -->
                <div class="avatar-container w-32 h-32 rounded-full flex items-center justify-center text-4xl font-bold relative">
                    AD
                    <div class="notification-dot absolute top-2 right-2"></div>
                </div>
                
                <!-- Profile Info -->
                <div class="text-center md:text-left flex-1">
                    <h1 class="text-4xl md:text-5xl font-bold mb-2">Elan Pratama S.pd</h1>
                    <p class="text-xl text-blue-100 mb-4">Wakil Kepala Sekolah</p>
                    <div class="flex flex-wrap justify-center md:justify-start gap-3 mb-6">
                        <span class="badge-primary px-4 py-2 rounded-full text-sm">Administrator</span>
                        <span class="badge-success px-4 py-2 rounded-full text-sm">Active</span>
                    </div>
                    <div class="flex flex-wrap justify-center md:justify-start gap-4">
                        <button class="btn-primary px-8 py-3 rounded-xl">
                            <i class="bi bi-pencil mr-2"></i>Edit Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 -mt-8 relative z-20">
        <!-- Stats Cards -->
        <div class="stats-grid grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="stats-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="bi bi-people text-blue-600 text-xl"></i>
                    </div>
                    <span class="text-sm text-green-600 font-semibold">+12%</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">2,345</h3>
                <p class="text-gray-600">Total Siswa</p>
            </div>
            
            <div class="stats-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                        <i class="bi bi-award text-green-600 text-xl"></i>
                    </div>
                    <span class="text-sm text-green-600 font-semibold">+8%</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">1,234</h3>
                <p class="text-gray-600">Apresiasi</p>
            </div>
            
            <div class="stats-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                        <i class="bi bi-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <span class="text-sm text-red-600 font-semibold">-3%</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">567</h3>
                <p class="text-gray-600">Pelanggaran</p>
            </div>
            
            <div class="stats-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                        <i class="bi bi-clipboard-data text-purple-600 text-xl"></i>
                    </div>
                    <span class="text-sm text-green-600 font-semibold">+15%</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">89.5%</h3>
                <p class="text-gray-600">Rata-rata Skor</p>
            </div>
        </div>

        <!-- Profile Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Profile Information -->
            <div class="lg:col-span-2">
                <div class="profile-card rounded-2xl p-8 mb-8">
                    <h2 class="section-header text-2xl font-bold text-gray-900 mb-6">Informasi Profile</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="info-item p-4">
                            <label class="block text-sm font-semibold text-gray-500 mb-2">Nama Lengkap</label>
                            <p class="text-lg font-semibold text-gray-900">Elan Pratama S.pd</p>
                        </div>
                        
                        <div class="info-item p-4">
                            <label class="block text-sm font-semibold text-gray-500 mb-2">NIP</label>
                            <p class="text-lg font-semibold text-gray-900">196512121990031001</p>
                        </div>
                        
                        <div class="info-item p-4">
                            <label class="block text-sm font-semibold text-gray-500 mb-2">Email</label>
                            <p class="text-lg font-semibold text-gray-900">elann03@smkn1bandung.sch.id</p>
                        </div>
                        
                        <div class="info-item p-4">
                            <label class="block text-sm font-semibold text-gray-500 mb-2">Jabatan</label>
                            <p class="text-lg font-semibold text-gray-900">Wakil Kepala Sekolah</p>
                        </div>
                        
                        <div class="info-item p-4">
                            <label class="block text-sm font-semibold text-gray-500 mb-2">Telepon</label>
                            <p class="text-lg font-semibold text-gray-900">+62 22 1234567</p>
                        </div>
                        
                        <div class="info-item p-4">
                            <label class="block text-sm font-semibold text-gray-500 mb-2">Status</label>
                            <span class="badge-success px-3 py-1 rounded-full text-sm">Aktif</span>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="profile-card rounded-2xl p-8">
                    <h2 class="section-header text-2xl font-bold text-gray-900 mb-6">Aktivitas Terbaru</h2>
                    
                    <div class="space-y-4">
                        <div class="activity-item p-4">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="bi bi-plus-circle text-blue-600"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">Menambahkan data siswa baru</p>
                                    <p class="text-sm text-gray-600">25 siswa kelas 10 PPLG 1 berhasil ditambahkan</p>
                                    <span class="text-xs text-gray-500">2 jam yang lalu</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="activity-item p-4">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="bi bi-award text-green-600"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">Memberikan apresiasi</p>
                                    <p class="text-sm text-gray-600">Apresiasi kepada Andi Setiawan untuk prestasi terbaik</p>
                                    <span class="text-xs text-gray-500">5 jam yang lalu</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="activity-item p-4">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="bi bi-file-earmark-text text-purple-600"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">Generate laporan bulanan</p>
                                    <p class="text-sm text-gray-600">Laporan penilaian siswa bulan Januari 2024</p>
                                    <span class="text-xs text-gray-500">1 hari yang lalu</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="space-y-8">
                <!-- Quick Actions -->
                <div class="profile-card rounded-2xl p-6">
                    <h3 class="section-header text-xl font-bold text-gray-900 mb-6">Quick Actions</h3>
                    
                    <div class="space-y-3">
                        <button class="w-full btn-primary py-3 px-4 rounded-xl text-left">
                            <i class="bi bi-plus-circle mr-3"></i>Tambah Siswa
                        </button>
                        <button class="w-full btn-outline py-3 px-4 rounded-xl text-left">
                            <i class="bi bi-download mr-3"></i>Export Data
                        </button>
                        <button class="w-full btn-outline py-3 px-4 rounded-xl text-left">
                            <i class="bi bi-bar-chart mr-3"></i>Lihat Statistik
                        </button>
                    </div>
                </div>
                
                <!-- System Info -->
                <div class="profile-card rounded-2xl p-6">
                    <h3 class="section-header text-xl font-bold text-gray-900 mb-6">System Info</h3>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Version</span>
                            <span class="font-semibold text-gray-900">v2.1.0</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Last Login</span>
                            <span class="font-semibold text-gray-900">Today 08:30</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Session</span>
                            <span class="badge-success px-2 py-1 rounded-full text-xs">Active</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Role</span>
                            <span class="badge-primary px-2 py-1 rounded-full text-xs">Admin</span>
                        </div>
                    </div>
                </div>
                
                <!-- School Info -->
                <div class="profile-card rounded-2xl p-6">
                    <h3 class="section-header text-xl font-bold text-gray-900 mb-6">Sekolah</h3>
                    
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <i class="bi bi-building text-blue-600 text-2xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-2">SMK Negeri 1 Bandung</h4>
                        <p class="text-sm text-gray-600 mb-4">Jl. Wastukancana No.3, Bandung</p>
                        <button class="btn-outline px-4 py-2 rounded-lg text-sm">
                            <i class="bi bi-geo-alt mr-2"></i>Lihat Lokasi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        // Smooth scroll untuk internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animasi stats counter
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.ceil(current).toLocaleString();
                }
            }, 30);
        }

        // Trigger animasi ketika element terlihat
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statsCards = entry.target.querySelectorAll('.stats-card h3');
                    statsCards.forEach(stat => {
                        const value = stat.textContent.replace(/,/g, '');
                        if (!isNaN(value)) {
                            animateCounter(stat, parseInt(value));
                        }
                    });
                    observer.unobserve(entry.target);
                }
            });
        });

        const statsGrid = document.querySelector('.stats-grid');
        if (statsGrid) {
            observer.observe(statsGrid);
        }

        // Floating action button functionality
        document.querySelector('.floating-action').addEventListener('click', function() {
            // Implement chat or help functionality
            alert('Chat feature will be implemented here!');
        });

        // Add loading animation
        window.addEventListener('load', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease';
            
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });

        // Profile card hover effects
        document.querySelectorAll('.info-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(8px)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });
    </script>
@endpush