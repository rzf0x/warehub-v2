<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Sedang Dalam Pemeliharaan | WareHub</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg-color: #090d16;
            --card-bg: rgba(15, 23, 42, 0.75);
            --card-border: rgba(255, 255, 255, 0.08);
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            --accent-glow: rgba(99, 102, 241, 0.15);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient background glow & radial circles */
        .ambient-sphere {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            pointer-events: none;
            z-index: 0;
        }
        .sphere-1 {
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.28) 0%, rgba(99, 102, 241, 0) 70%);
            top: -10%;
            left: -10%;
        }
        .sphere-2 {
            width: 520px;
            height: 520px;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.2) 0%, rgba(236, 72, 153, 0) 70%);
            bottom: -15%;
            right: -10%;
        }
        .sphere-3 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.15) 0%, rgba(14, 165, 233, 0) 70%);
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        /* Tech Grid Pattern */
        .grid-pattern {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 3.5rem 3.5rem;
            mask-image: radial-gradient(ellipse 65% 55% at 50% 50%, #000 70%, transparent 100%);
            -webkit-mask-image: radial-gradient(ellipse 65% 55% at 50% 50%, #000 70%, transparent 100%);
            pointer-events: none;
            z-index: 1;
        }

        /* Main Container */
        .card-container {
            position: relative;
            z-index: 10;
            max-width: 640px;
            width: 100%;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 3rem 2.25rem;
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.6),
                0 0 0 1px rgba(255, 255, 255, 0.05),
                inset 0 1px 1px rgba(255, 255, 255, 0.15);
            text-align: center;
            animation: fadeInScale 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(15px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* Logo Brand Pill */
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.45rem 1.1rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.09);
            margin-bottom: 2rem;
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #c7d2fe;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .pulsing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #fbbf24;
            box-shadow: 0 0 12px #fbbf24;
            animation: pulseWarning 1.8s infinite;
        }

        @keyframes pulseWarning {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.4);
                opacity: 0.6;
            }
        }

        /* Animated Illustration */
        .illustration-wrapper {
            position: relative;
            width: 110px;
            height: 110px;
            margin: 0 auto 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-circle-bg {
            position: absolute;
            inset: 0;
            border-radius: 32px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(168, 85, 247, 0.15) 100%);
            border: 1px solid rgba(139, 92, 246, 0.3);
            transform: rotate(-6deg);
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.25);
            animation: floatCircle 5s ease-in-out infinite;
        }

        @keyframes floatCircle {
            0%, 100% {
                transform: rotate(-6deg) translateY(0);
            }
            50% {
                transform: rotate(4deg) translateY(-8px);
            }
        }

        .maintenance-icon {
            position: relative;
            z-index: 2;
            width: 52px;
            height: 52px;
            color: #818cf8;
            filter: drop-shadow(0 4px 12px rgba(99, 102, 241, 0.4));
        }

        /* Typography */
        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 2.15rem;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 0.85rem;
            letter-spacing: -0.02em;
            background: linear-gradient(180deg, #ffffff 30%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p.subtitle {
            font-size: 0.98rem;
            color: var(--text-muted);
            line-height: 1.65;
            margin-bottom: 2rem;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Status Box */
        .status-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 18px;
            padding: 1.15rem 1.25rem;
            margin-bottom: 2.25rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 1rem;
            text-align: left;
        }

        .status-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .status-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            font-weight: 700;
        }

        .status-val {
            font-size: 0.9rem;
            font-weight: 600;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .badge-active {
            color: #38bdf8;
        }

        /* Progress Bar */
        .progress-container {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 9999px;
            overflow: hidden;
            margin-bottom: 2rem;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            width: 45%;
            background: var(--primary-gradient);
            border-radius: 9999px;
            animation: indeterminateProgress 2.2s infinite ease-in-out;
            box-shadow: 0 0 14px rgba(99, 102, 241, 0.6);
        }

        @keyframes indeterminateProgress {
            0% {
                transform: translateX(-100%);
                width: 30%;
            }
            50% {
                transform: translateX(100%);
                width: 70%;
            }
            100% {
                transform: translateX(350%);
                width: 30%;
            }
        }

        /* Action Buttons */
        .action-group {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            justify-content: center;
            align-items: center;
        }

        @media (min-width: 480px) {
            .action-group {
                flex-direction: row;
            }
        }

        .btn-refresh {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            padding: 0.85rem 1.75rem;
            border-radius: 14px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #ffffff;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 8px 24px -4px rgba(79, 70, 229, 0.5);
            width: 100%;
        }

        @media (min-width: 480px) {
            .btn-refresh {
                width: auto;
            }
        }

        .btn-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -4px rgba(79, 70, 229, 0.7);
            background: linear-gradient(135deg, #4338ca 0%, #6d28d9 100%);
        }

        .btn-refresh:active {
            transform: scale(0.97);
        }

        .btn-refresh svg {
            transition: transform 0.5s ease;
        }

        .btn-refresh:hover svg {
            transform: rotate(180deg);
        }

        /* Auto refresh count down */
        .reload-timer {
            margin-top: 1.5rem;
            font-size: 0.82rem;
            color: #64748b;
        }

        .reload-timer span {
            color: #a5b4fc;
            font-weight: 700;
            font-family: monospace;
        }

        /* Footer */
        .footer-note {
            margin-top: 2.25rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.78rem;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .footer-note a {
            color: #818cf8;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .footer-note a:hover {
            color: #c7d2fe;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Ambient Blur Spheres -->
    <div class="ambient-sphere sphere-1"></div>
    <div class="ambient-sphere sphere-2"></div>
    <div class="ambient-sphere sphere-3"></div>

    <!-- Background Tech Grid -->
    <div class="grid-pattern"></div>

    <main class="card-container">
        <!-- Brand Pill -->
        <div class="brand-badge">
            <span class="pulsing-dot"></span>
            <span>WAREHUB SYSTEM V2 &bull; MAINTENANCE</span>
        </div>

        <!-- Animated Icon -->
        <div class="illustration-wrapper">
            <div class="icon-circle-bg"></div>
            <svg class="maintenance-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
            </svg>
        </div>

        <!-- Main Title -->
        <h1>Sistem Sedang Diperbarui</h1>
        
        <p class="subtitle">
            Kami sedang melakukan peningkatan performa & sinkronisasi data gudang berkala. Sistem akan segera aktif kembali dalam beberapa saat.
        </p>

        <!-- Progress Indicator -->
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>

        <!-- Status Dashboard Mini -->
        <div class="status-box">
            <div class="status-item">
                <span class="status-label">Status Server</span>
                <span class="status-val badge-active">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    Optimalisasi Database
                </span>
            </div>
            <div class="status-item">
                <span class="status-label">Keamanan Data</span>
                <span class="status-val" style="color: #34d399;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    100% Aman & Terenkripsi
                </span>
            </div>
        </div>

        <!-- Action Button -->
        <div class="action-group">
            <button onclick="window.location.reload();" class="btn-refresh">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Periksa Status Sistem Sekarang</span>
            </button>
        </div>

        <!-- Auto Reload Timer -->
        <div class="reload-timer">
            Halaman akan memuat ulang secara otomatis dalam <span id="countdown">30</span> detik
        </div>

        <!-- Footer Note -->
        <div class="footer-note">
            <span>&copy; {{ date('Y') }} WareHub Inventory System.</span>
            <span>Butuh bantuan mendesak? <a href="javascript:void(0)" onclick="alert('Silakan hubungi administrator tim IT gudang.');">Hubungi Superadmin</a></span>
        </div>
    </main>

    <script>
        // Auto refresh countdown
        let secondsLeft = 30;
        const countdownEl = document.getElementById('countdown');

        const interval = setInterval(() => {
            secondsLeft--;
            if (countdownEl) {
                countdownEl.textContent = secondsLeft;
            }
            if (secondsLeft <= 0) {
                clearInterval(interval);
                window.location.reload();
            }
        }, 1000);
    </script>
</body>
</html>
