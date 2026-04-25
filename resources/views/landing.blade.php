@extends('layouts.public')

@section('body_class', 'landing-bloom')

@section('content')
    <style>
        .landing-bloom-wrap {
            display: grid;
            gap: 68px;
        }

        [data-reveal] {
            opacity: 0;
            transform: translateY(26px) scale(0.98);
            transition:
                opacity .6s ease,
                transform .72s cubic-bezier(.2, .9, .24, 1);
            transition-delay: var(--reveal-delay, 0ms);
            will-change: opacity, transform;
        }

        [data-reveal].is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .hero-shell {
            position: relative;
            overflow: hidden;
            border-radius: 34px;
            border: 1px solid #d7e8fa;
            background: linear-gradient(154deg, #ffffff 0%, #f2f8ff 58%, #ebf6ff 100%);
            padding: clamp(26px, 5vw, 52px);
            box-shadow: 0 24px 60px rgba(16, 42, 67, 0.12);
        }

        .hero-shell::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 12% 12%, rgba(255, 255, 255, 0.75) 0 22%, transparent 46%),
                radial-gradient(circle at 82% 82%, rgba(255, 255, 255, 0.44) 0 18%, transparent 50%);
            pointer-events: none;
        }

        .hero-backdrop {
            position: absolute;
            inset: -40px;
            pointer-events: none;
        }

        .blob {
            position: absolute;
            border-radius: 48% 52% 56% 44% / 42% 56% 44% 58%;
            opacity: 0.66;
            filter: blur(2px);
            animation: blobMorph 12s ease-in-out infinite alternate, blobDrift 18s ease-in-out infinite;
        }

        .blob-a {
            width: 340px;
            height: 280px;
            left: -110px;
            top: 20px;
            background: radial-gradient(circle at 30% 28%, rgba(13, 59, 102, 0.44), rgba(13, 59, 102, 0.08));
        }

        .blob-b {
            width: 260px;
            height: 220px;
            right: 40px;
            top: -32px;
            background: radial-gradient(circle at 62% 34%, rgba(249, 87, 56, 0.42), rgba(249, 87, 56, 0.06));
            animation-delay: -3s;
        }

        .blob-c {
            width: 220px;
            height: 180px;
            right: -48px;
            bottom: -40px;
            background: radial-gradient(circle at 52% 52%, rgba(86, 159, 233, 0.4), rgba(86, 159, 233, 0.05));
            animation-delay: -5.5s;
        }

        @keyframes blobMorph {
            0% {
                border-radius: 48% 52% 56% 44% / 42% 56% 44% 58%;
            }

            50% {
                border-radius: 56% 44% 48% 52% / 54% 42% 58% 46%;
            }

            100% {
                border-radius: 44% 56% 52% 48% / 48% 60% 40% 52%;
            }
        }

        @keyframes blobDrift {
            0% {
                transform: translate3d(0, 0, 0);
            }

            50% {
                transform: translate3d(12px, -10px, 0);
            }

            100% {
                transform: translate3d(-12px, 12px, 0);
            }
        }

        .hero-grid {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: 1.04fr 0.96fr;
            gap: 30px;
            align-items: center;
        }

        .hero-pill {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            border-radius: 999px;
            padding: 0 14px;
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 800;
            color: #0d3b66;
            background: rgba(13, 59, 102, 0.12);
        }

        .hero-heading {
            margin: 14px 0 14px;
            font-family: 'Fraunces', serif;
            color: #102a43;
            line-height: 1.04;
            font-size: clamp(2.35rem, 5vw, 4.4rem);
            letter-spacing: -0.01em;
            max-width: 720px;
        }

        .hero-subhead {
            margin: 0;
            max-width: 650px;
            color: #334e68;
            font-size: clamp(1.02rem, 1.6vw, 1.14rem);
            line-height: 1.72;
        }

        .hero-actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .cta-shimmer {
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .cta-shimmer::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: inherit;
            background: linear-gradient(
                112deg,
                rgba(255, 255, 255, 0) 30%,
                rgba(255, 255, 255, 0.48) 48%,
                rgba(255, 255, 255, 0) 66%
            );
            transform: translateX(-140%);
            animation: ctaShimmer 4.8s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes ctaShimmer {
            0%,
            68% {
                transform: translateX(-140%);
            }

            85%,
            100% {
                transform: translateX(160%);
            }
        }

        .hero-secondary {
            background: rgba(255, 255, 255, 0.75);
            border-color: rgba(13, 59, 102, 0.16);
            color: #0d3b66;
        }

        .hero-signal {
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #1f476d;
            font-size: 13px;
            font-weight: 700;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.66);
            border: 1px solid rgba(13, 59, 102, 0.14);
        }

        .signal-points {
            display: inline-flex;
            gap: 6px;
        }

        .signal-points span {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: rgba(13, 59, 102, 0.28);
            animation: signalPulse 1.8s ease-in-out infinite;
        }

        .signal-points span:nth-child(2) {
            animation-delay: .2s;
        }

        .signal-points span:nth-child(3) {
            animation-delay: .4s;
        }

        @keyframes signalPulse {
            0%,
            100% {
                transform: scale(0.9);
                opacity: 0.4;
            }

            50% {
                transform: scale(1.2);
                opacity: 1;
            }
        }

        .hero-stage-shell {
            position: relative;
        }

        .hero-stage {
            position: relative;
            min-height: 440px;
            perspective: 1200px;
            transition: transform .3s cubic-bezier(.2, .9, .24, 1);
        }

        .hero-visual-frame {
            position: absolute;
            inset: 20px 10px 20px 10px;
            border-radius: 24px;
            padding: 16px;
            background: linear-gradient(150deg, rgba(255, 255, 255, 0.96), rgba(228, 242, 255, 0.88));
            border: 1px solid rgba(133, 176, 218, 0.45);
            box-shadow: 0 20px 44px rgba(9, 42, 74, 0.16);
            z-index: 1;
        }

        .hero-visual-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 16px;
            display: block;
        }

        .hero-card {
            position: absolute;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(133, 176, 218, 0.42);
            box-shadow: 0 16px 26px rgba(16, 42, 67, 0.16);
            padding: 12px 14px;
            color: #123455;
            font-size: 13px;
            line-height: 1.45;
            animation: floatCard 6s ease-in-out infinite;
            z-index: 2;
        }

        .hero-card strong {
            display: block;
            color: #0d3b66;
            margin-bottom: 2px;
            font-size: 14px;
        }

        .hero-card.top {
            top: -4px;
            left: 0;
            max-width: 210px;
        }

        .hero-card.bottom {
            right: 0;
            bottom: -8px;
            max-width: 230px;
            animation-delay: -2.2s;
        }

        @keyframes floatCard {
            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        .scroll-indicator {
            position: absolute;
            left: 50%;
            bottom: 12px;
            transform: translateX(-50%);
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            color: #33587c;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .scroll-indicator i {
            width: 14px;
            height: 14px;
            border-right: 2px solid currentColor;
            border-bottom: 2px solid currentColor;
            transform: rotate(45deg);
            animation: bounceScroll 1.5s ease-in-out infinite;
        }

        @keyframes bounceScroll {
            0%,
            100% {
                transform: rotate(45deg) translate(-2px, -2px);
                opacity: 0.6;
            }

            50% {
                transform: rotate(45deg) translate(3px, 3px);
                opacity: 1;
            }
        }

        .stats-section {
            position: relative;
            border-radius: 24px;
            border: 1px solid #d4e6f8;
            background: #ffffff;
            box-shadow: 0 18px 36px rgba(16, 42, 67, 0.08);
            padding: 18px 22px 24px;
            overflow: hidden;
        }

        .stats-wave {
            position: absolute;
            left: 0;
            right: 0;
            top: -1px;
            height: 62px;
            pointer-events: none;
        }

        .stats-wave svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .stats-row {
            margin-top: 26px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .stat-card {
            border-radius: 16px;
            border: 1px solid #d7e8f8;
            background: linear-gradient(168deg, #ffffff, #f6fbff);
            padding: 18px;
            min-height: 112px;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.06);
        }

        .stat-value {
            margin: 0 0 8px;
            font-size: clamp(1.9rem, 3.3vw, 2.3rem);
            line-height: 1;
            color: #0d3b66;
            font-weight: 800;
        }

        .stat-card div {
            color: #486581;
            font-size: 14px;
            font-weight: 600;
        }

        .section-shell {
            display: grid;
            gap: 18px;
        }

        .section-kicker {
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 800;
            font-size: 12px;
            color: #1f476d;
        }

        .section-title {
            margin: 0;
            font-family: 'Fraunces', serif;
            font-size: clamp(1.9rem, 3.4vw, 2.8rem);
            color: #102a43;
            line-height: 1.1;
        }

        .section-copy {
            margin: 0;
            color: #52667a;
            line-height: 1.68;
            max-width: 780px;
            font-size: 16px;
        }

        .steps-track-shell {
            position: relative;
            padding-top: 18px;
        }

        .steps-connector {
            position: absolute;
            top: 48px;
            left: 6%;
            width: 88%;
            height: 26px;
            pointer-events: none;
        }

        .steps-connector-path {
            stroke: rgba(13, 59, 102, 0.3);
            stroke-width: 3;
            stroke-linecap: round;
            fill: none;
            stroke-dasharray: 1200;
            stroke-dashoffset: 1200;
            transition: stroke-dashoffset 1.4s cubic-bezier(.2, .9, .24, 1);
        }

        .steps-track-shell.is-visible .steps-connector-path {
            stroke-dashoffset: 0;
        }

        .steps-grid {
            margin-top: 10px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            position: relative;
            z-index: 1;
        }

        .step-card {
            border: 1px solid #d7e8f8;
            border-radius: 16px;
            background: #fff;
            padding: 18px;
            box-shadow: 0 10px 20px rgba(16, 42, 67, 0.06);
        }

        .step-card[data-reveal] {
            transform: translateY(32px) scale(0.94);
        }

        .step-index {
            width: 38px;
            height: 38px;
            border-radius: 999px;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(13, 59, 102, 0.08);
            margin-bottom: 12px;
            overflow: hidden;
        }

        .step-index::before {
            content: '';
            position: absolute;
            inset: 0;
            border: 2px solid #0d3b66;
            border-radius: inherit;
            opacity: 0;
            transform: scale(0.58);
            transition: transform .46s cubic-bezier(.2, .9, .24, 1), opacity .3s ease;
        }

        .step-index strong {
            color: #0d3b66;
            font-size: 15px;
            font-weight: 800;
            opacity: 0;
            transform: translateY(7px);
            transition: transform .4s ease, opacity .34s ease;
        }

        .step-card.is-visible .step-index::before {
            opacity: 1;
            transform: scale(1);
        }

        .step-card.is-visible .step-index strong {
            opacity: 1;
            transform: translateY(0);
            transition-delay: .16s;
        }

        .step-card h3 {
            margin: 0 0 8px;
            color: #102a43;
            font-size: 18px;
        }

        .step-card p {
            margin: 0;
            color: #52667a;
            line-height: 1.58;
            font-size: 14px;
        }

        .teachers-grid,
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .teacher-card,
        .testimonial-card {
            border: 1px solid #d7e8f8;
            border-radius: 16px;
            background: #fff;
            padding: 18px;
            box-shadow: 0 12px 24px rgba(16, 42, 67, 0.07);
        }

        .teacher-card[data-reveal] {
            transform: perspective(900px) rotateX(11deg) rotateY(-10deg) translateY(20px);
            transform-origin: 50% 100%;
        }

        .teacher-card[data-reveal].is-visible {
            transform: perspective(900px) rotateX(0) rotateY(0) translateY(0);
        }

        .teacher-card:hover {
            box-shadow: 0 18px 28px rgba(16, 42, 67, 0.13);
            transform: perspective(900px) rotateX(0) rotateY(0) translateY(-3px);
        }

        .teacher-head {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .teacher-head img {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #d8e8f8;
        }

        .teacher-meta h3 {
            margin: 0;
            color: #102a43;
            font-size: 18px;
        }

        .teacher-meta p {
            margin: 4px 0 0;
            color: #486581;
            font-size: 14px;
        }

        .teacher-footer {
            margin-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            color: #486581;
            font-size: 14px;
        }

        .teacher-footer a {
            color: #0d3b66;
            text-decoration: none;
            font-weight: 800;
        }

        .testimonial-card p {
            margin: 0 0 12px;
            color: #334e68;
            line-height: 1.68;
        }

        .testimonial-card strong {
            color: #102a43;
            font-size: 14px;
        }

        .value-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .value-card,
        .faq-card {
            border: 1px solid #d7e8f8;
            border-radius: 16px;
            background: #fff;
            padding: 18px;
            box-shadow: 0 12px 24px rgba(16, 42, 67, 0.07);
        }

        .value-card h3,
        .faq-card h3 {
            margin: 0 0 8px;
            color: #102a43;
            font-size: 17px;
            line-height: 1.3;
        }

        .value-card p,
        .faq-card p {
            margin: 0;
            color: #486581;
            line-height: 1.66;
            font-size: 14px;
        }

        .faq-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .inline-link {
            color: #0d3b66;
            text-decoration: none;
            font-weight: 700;
            border-bottom: 1px dotted rgba(13, 59, 102, 0.42);
        }

        .inline-link:hover,
        .inline-link:focus-visible {
            color: #f95738;
            border-bottom-color: rgba(249, 87, 56, 0.6);
        }

        .cta-band {
            position: relative;
            overflow: hidden;
            border-radius: 26px;
            border: 1px solid rgba(173, 210, 245, 0.44);
            background: linear-gradient(122deg, #0d3b66 0%, #15548c 58%, #f95738 100%);
            color: #ffffff;
            padding: 34px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            box-shadow: 0 20px 44px rgba(7, 42, 74, 0.28);
        }

        .cta-band::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 10% 14%, rgba(255, 255, 255, 0.16) 0 2px, transparent 2px),
                radial-gradient(circle at 72% 40%, rgba(255, 255, 255, 0.16) 0 2px, transparent 2px),
                radial-gradient(circle at 44% 76%, rgba(255, 255, 255, 0.14) 0 2px, transparent 2px);
            background-size: 16px 16px, 18px 18px, 20px 20px;
            opacity: 0.35;
            pointer-events: none;
        }

        .cta-band h2 {
            margin: 0 0 10px;
            font-family: 'Fraunces', serif;
            font-size: clamp(1.84rem, 3.1vw, 2.5rem);
            line-height: 1.08;
            position: relative;
            z-index: 1;
        }

        .cta-band p {
            margin: 0;
            max-width: 620px;
            color: rgba(255, 255, 255, 0.92);
            line-height: 1.62;
            position: relative;
            z-index: 1;
        }

        .cta-actions {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .cta-fill-btn {
            position: relative;
            overflow: hidden;
            border: 0;
            background: #ffffff;
            color: #0d3b66;
            isolation: isolate;
            box-shadow: 0 14px 28px rgba(8, 46, 81, 0.34);
        }

        .cta-fill-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, #f95738, #ff7a61);
            clip-path: inset(0 100% 0 0 round 999px);
            transition: clip-path .46s cubic-bezier(.2, .9, .24, 1);
            z-index: -1;
        }

        .cta-fill-btn:hover,
        .cta-fill-btn:focus-visible {
            color: #ffffff;
        }

        .cta-fill-btn:hover::after,
        .cta-fill-btn:focus-visible::after {
            clip-path: inset(0 0 0 0 round 999px);
        }

        .cta-outline {
            border-color: rgba(255, 255, 255, 0.52);
            color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
        }

        .cta-outline:hover {
            background: rgba(255, 255, 255, 0.16);
        }

        @media (max-width: 1060px) {
            .hero-grid {
                grid-template-columns: 1fr;
            }

            .hero-stage {
                min-height: 370px;
            }

            .stats-row,
            .steps-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .teachers-grid,
            .testimonials-grid {
                grid-template-columns: 1fr;
            }

            .value-grid,
            .faq-grid {
                grid-template-columns: 1fr;
            }

            .steps-connector {
                display: none;
            }
        }

        @media (max-width: 760px) {
            .landing-bloom-wrap {
                gap: 52px;
            }

            .hero-shell {
                border-radius: 26px;
            }

            .hero-stage {
                min-height: 300px;
            }

            .hero-visual-frame {
                inset: 14px 6px;
            }

            .hero-card {
                font-size: 12px;
                max-width: 190px;
            }

            .stats-row,
            .steps-grid {
                grid-template-columns: 1fr;
            }

            .scroll-indicator {
                display: none;
            }

            .cta-band {
                padding: 24px;
            }

            .cta-actions {
                width: 100%;
            }

            .cta-actions .btn-pill {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            [data-reveal] {
                opacity: 1;
                transform: none;
                transition: none;
            }

            .blob,
            .hero-card,
            .cta-shimmer::before,
            .signal-points span,
            .scroll-indicator i {
                animation: none;
            }

            .steps-connector-path {
                transition: none;
                stroke-dashoffset: 0;
            }
        }
    </style>

    <div class="landing-bloom-wrap">
        <section class="hero-shell" id="landing-hero-shell">
            <div class="hero-backdrop" aria-hidden="true">
                <span class="blob blob-a"></span>
                <span class="blob blob-b"></span>
                <span class="blob blob-c"></span>
            </div>

            <div class="hero-grid">
                <div>
                    <span class="hero-pill" data-reveal style="--reveal-delay: 20ms;">Trusted online learning</span>
                    <h1 class="hero-heading" data-reveal style="--reveal-delay: 130ms;">Learn faster with verified teachers who match your goals.</h1>
                    <p class="hero-subhead" data-reveal style="--reveal-delay: 260ms;">
                        EduBridge helps students discover top educators, book secure sessions, and stay consistent with a flexible learning plan built around real outcomes.
                    </p>

                    <div class="hero-actions" data-reveal style="--reveal-delay: 380ms;">
                        <a href="{{ route('student.register') }}" class="btn-pill primary cta-shimmer" id="landing-start-student">Start Learning</a>
                        <a href="{{ route('teacher.register') }}" class="btn-pill secondary hero-secondary" id="landing-start-teacher">Teach on EduBridge</a>
                    </div>

                    <div class="hero-signal" data-reveal style="--reveal-delay: 500ms;">
                        <span class="signal-points" aria-hidden="true"><span></span><span></span><span></span></span>
                        Real-time matching, verified teachers, secure sessions
                    </div>
                </div>

                <div class="hero-stage-shell" data-reveal style="--reveal-delay: 180ms;">
                    <div class="hero-stage" id="landing-hero-stage">
                        <div class="hero-card top" aria-hidden="true">
                            <strong>Session confirmed</strong>
                            Algebra with R. Menon in 18 minutes
                        </div>
                        <div class="hero-visual-frame">
                            <img src="{{ asset('images/hero-illustration.svg') }}" width="960" height="720" alt="Student attending a live online class with a teacher">
                        </div>
                        <div class="hero-card bottom" aria-hidden="true">
                            <strong>Progress pulse</strong>
                            Weekly consistency improved by 24%
                        </div>
                    </div>
                </div>
            </div>

            <a href="#landing-stats" class="scroll-indicator" aria-label="Scroll to trust indicators">
                <span>Scroll</span>
                <i></i>
            </a>
        </section>

        <section class="stats-section" id="landing-stats" data-reveal style="--reveal-delay: 50ms;">
            <div class="stats-wave" aria-hidden="true">
                <svg viewBox="0 0 1200 80" preserveAspectRatio="none">
                    <path d="M0,24 C120,64 240,4 360,26 C480,48 600,78 720,42 C840,6 960,8 1080,28 C1140,38 1180,40 1200,38 L1200,0 L0,0 Z" fill="rgba(116, 170, 225, 0.18)"></path>
                </svg>
            </div>

            <div class="stats-row" aria-label="Trust indicators">
                <article class="stat-card" data-reveal style="--reveal-delay: 100ms;">
                    <p class="stat-value" data-count="{{ $stats['teachers'] }}" data-suffix="+">0</p>
                    <div>Active teachers</div>
                </article>
                <article class="stat-card" data-reveal style="--reveal-delay: 170ms;">
                    <p class="stat-value" data-count="{{ $stats['students'] }}" data-suffix="+">0</p>
                    <div>Students enrolled</div>
                </article>
                <article class="stat-card" data-reveal style="--reveal-delay: 240ms;">
                    <p class="stat-value" data-count="{{ $stats['sessions'] }}" data-suffix="+">0</p>
                    <div>Sessions completed</div>
                </article>
                <article class="stat-card" data-reveal style="--reveal-delay: 310ms;">
                    <p class="stat-value" data-count="{{ $stats['reviews'] }}" data-suffix="+">0</p>
                    <div>Verified reviews</div>
                </article>
            </div>
        </section>

        <section class="section-shell">
            <div data-reveal>
                <p class="section-kicker">How It Works</p>
                <h2 class="section-title">A smooth loop from discovery to measurable progress.</h2>
                <p class="section-copy">Students and teachers move through a clear journey: discover a strong fit, schedule confidently, deliver focused sessions, and reflect with feedback to improve outcomes.</p>
            </div>

            <div class="steps-track-shell" id="steps-track-shell">
                <svg class="steps-connector" viewBox="0 0 1000 120" preserveAspectRatio="none" aria-hidden="true">
                    <path class="steps-connector-path" d="M24 68 C164 8, 286 110, 426 66 C566 22, 684 108, 824 64 C888 44, 936 44, 976 64" />
                </svg>

                <div class="steps-grid">
                    <article class="step-card" data-reveal style="--reveal-delay: 40ms;">
                        <span class="step-index"><strong>1</strong></span>
                        <h3>Explore teachers</h3>
                        <p>Search by subject, ratings, and teaching style to find the right mentor quickly.</p>
                    </article>
                    <article class="step-card" data-reveal style="--reveal-delay: 130ms;">
                        <span class="step-index"><strong>2</strong></span>
                        <h3>Book instantly</h3>
                        <p>Pick open time slots and confirm secure payments in one guided checkout.</p>
                    </article>
                    <article class="step-card" data-reveal style="--reveal-delay: 220ms;">
                        <span class="step-index"><strong>3</strong></span>
                        <h3>Attend live class</h3>
                        <p>Join interactive sessions with clear structure, whiteboard tools, and focus.</p>
                    </article>
                    <article class="step-card" data-reveal style="--reveal-delay: 310ms;">
                        <span class="step-index"><strong>4</strong></span>
                        <h3>Review outcomes</h3>
                        <p>Capture feedback after every class and tune your learning trajectory.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section-shell">
            <div data-reveal>
                <p class="section-kicker">Featured Teachers</p>
                <h2 class="section-title">Meet verified mentors learners already trust.</h2>
                <p class="section-copy">Each profile is identity-verified and reviewed by real students before being highlighted on EduBridge.</p>
            </div>

            <div class="teachers-grid">
                @forelse($featuredTeachers as $teacher)
                    @php($subjects = array_slice($teacher->teacherProfile->subjects ?? [], 0, 2))
                    <article class="teacher-card" data-reveal style="--reveal-delay: {{ $loop->index * 90 }}ms;">
                        <div class="teacher-head">
                            <img
                                src="{{ $teacher->avatar ?: 'https://ui-avatars.com/api/?name=' . urlencode($teacher->name) . '&background=0D3B66&color=fff' }}"
                                alt="{{ $teacher->name }} profile photo"
                                loading="lazy"
                                width="72"
                                height="72"
                            >
                            <div class="teacher-meta">
                                <h3>{{ $teacher->name }}</h3>
                                <p>{{ !empty($subjects) ? implode(', ', $subjects) : 'General mentoring' }}</p>
                            </div>
                        </div>
                        <div class="teacher-footer">
                            <span>
                                &#9733; {{ number_format((float) ($teacher->teacherProfile->rating_avg ?? 0), 1) }} &middot;
                                {{ $teacher->teacherProfile->total_reviews ?? 0 }} reviews
                            </span>
                            <a href="{{ route('teachers.show', $teacher->id) }}">View profile</a>
                        </div>
                    </article>
                @empty
                    <article class="teacher-card" data-reveal style="--reveal-delay: 40ms;">
                        <h3>Teacher listings coming soon</h3>
                        <p>We are onboarding verified teachers in your subjects right now.</p>
                    </article>
                @endforelse
            </div>
        </section>

        <section class="section-shell">
            <div data-reveal>
                <p class="section-kicker">Learner Stories</p>
                <h2 class="section-title">Small weekly gains compound into confidence.</h2>
            </div>
            <div class="testimonials-grid">
                <article class="testimonial-card" data-reveal style="--reveal-delay: 70ms;">
                    <p>"EduBridge made it easy to find a science teacher who explains concepts clearly. My daughter became more confident in just a few weeks."</p>
                    <strong>Priya S., Parent</strong>
                </article>
                <article class="testimonial-card" data-reveal style="--reveal-delay: 160ms;">
                    <p>"I like the transparent teacher profiles and how quickly I can schedule sessions around my college timetable."</p>
                    <strong>Arjun R., Student</strong>
                </article>
            </div>
        </section>

        <section class="section-shell">
            <div data-reveal>
                <p class="section-kicker">Why EduBridge</p>
                <h2 class="section-title">Built for trust, not trial and error.</h2>
                <p class="section-copy">
                    Every part of EduBridge is designed to reduce uncertainty for families and teachers. From profile vetting to post-session feedback,
                    we focus on clarity, accountability, and consistent learning outcomes.
                </p>
            </div>

            <div class="value-grid">
                <article class="value-card" data-reveal style="--reveal-delay: 80ms;">
                    <h3>Verified teacher onboarding</h3>
                    <p>
                        Teacher profiles are reviewed before they are promoted, including subject expertise, teaching setup quality, and learner feedback.
                    </p>
                </article>
                <article class="value-card" data-reveal style="--reveal-delay: 170ms;">
                    <h3>Secure and transparent bookings</h3>
                    <p>
                        Students can view rates, schedules, and outcomes clearly before confirming a class, with traceable payment and booking records.
                    </p>
                </article>
                <article class="value-card" data-reveal style="--reveal-delay: 260ms;">
                    <h3>Ongoing support after each class</h3>
                    <p>
                        Session history, reviews, and support workflows help both learners and teachers continuously improve with less friction.
                    </p>
                </article>
            </div>
        </section>

        <section class="section-shell">
            <div data-reveal>
                <p class="section-kicker">FAQ</p>
                <h2 class="section-title">Questions families ask before joining.</h2>
                <p class="section-copy">
                    If you need a specific policy or onboarding answer, visit our
                    <a class="inline-link" href="{{ route('privacy-policy') }}">Privacy Policy</a>,
                    <a class="inline-link" href="{{ route('terms') }}">Terms</a>, or
                    <a class="inline-link" href="{{ route('contact') }}">Contact page</a>.
                </p>
            </div>

            <div class="faq-grid">
                <article class="faq-card" data-reveal style="--reveal-delay: 90ms;">
                    <h3>How do I choose the right teacher?</h3>
                    <p>
                        Start with subject fit, ratings, and schedule availability. Shortlist two or three profiles and compare teaching style notes before booking.
                    </p>
                </article>
                <article class="faq-card" data-reveal style="--reveal-delay: 170ms;">
                    <h3>Can I reschedule a class?</h3>
                    <p>
                        Yes. Rescheduling and cancellation follow the booking policy shown during checkout so expectations are clear before payment.
                    </p>
                </article>
                <article class="faq-card" data-reveal style="--reveal-delay: 250ms;">
                    <h3>Do teachers get a professional profile flow?</h3>
                    <p>
                        Yes. Teachers complete structured profile steps, availability, and verification signals to help students make informed decisions.
                    </p>
                </article>
                <article class="faq-card" data-reveal style="--reveal-delay: 330ms;">
                    <h3>How fast does support respond?</h3>
                    <p>
                        Most support requests are acknowledged within one business day, with priority handling for booking and session-impacting issues.
                    </p>
                </article>
            </div>
        </section>

        <section class="cta-band" data-reveal>
            <div>
                <h2>Ready to make learning consistent?</h2>
                <p>Join EduBridge and start with trusted teachers, structured sessions, and measurable progress.</p>
            </div>
            <div class="cta-actions">
                <a href="{{ route('student.register') }}" class="btn-pill cta-fill-btn" id="landing-bottom-student">Join as Student</a>
                <a href="{{ route('teacher.register') }}" class="btn-pill cta-outline" id="landing-bottom-teacher">Join as Teacher</a>
            </div>
        </section>
    </div>

    <script>
        (function () {
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const revealElements = Array.from(document.querySelectorAll('[data-reveal]'));
            const counters = Array.from(document.querySelectorAll('[data-count]'));
            const stepsTrack = document.getElementById('steps-track-shell');
            const heroShell = document.getElementById('landing-hero-shell');
            const heroStage = document.getElementById('landing-hero-stage');

            const formatCount = (value, suffix) => `${value.toLocaleString('en-IN')}${suffix || ''}`;

            const finalizeCounters = function () {
                counters.forEach((counter) => {
                    const target = Number(counter.dataset.count || 0);
                    const suffix = counter.dataset.suffix || '';
                    counter.textContent = formatCount(target, suffix);
                });
            };

            const animateCounter = function (counter) {
                const target = Number(counter.dataset.count || 0);
                const suffix = counter.dataset.suffix || '';
                const duration = 1150;
                const startTime = performance.now();

                const easeOut = function (value) {
                    return 1 - Math.pow(1 - value, 3);
                };

                const tick = function (now) {
                    const progress = Math.min((now - startTime) / duration, 1);
                    const eased = easeOut(progress);
                    const current = Math.round(target * eased);
                    counter.textContent = formatCount(current, suffix);

                    if (progress < 1) {
                        requestAnimationFrame(tick);
                    }
                };

                requestAnimationFrame(tick);
            };

            if (prefersReducedMotion || !('IntersectionObserver' in window)) {
                revealElements.forEach((element) => element.classList.add('is-visible'));

                if (stepsTrack) {
                    stepsTrack.classList.add('is-visible');
                }

                finalizeCounters();
            } else {
                const revealObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) {
                            return;
                        }

                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    });
                }, {
                    threshold: 0.2,
                    rootMargin: '0px 0px -8% 0px',
                });

                revealElements.forEach((element) => revealObserver.observe(element));

                if (stepsTrack) {
                    const stepsObserver = new IntersectionObserver((entries, observer) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) {
                                return;
                            }

                            stepsTrack.classList.add('is-visible');
                            observer.disconnect();
                        });
                    }, { threshold: 0.5 });

                    stepsObserver.observe(stepsTrack);
                }

                if (counters.length > 0) {
                    const counterObserver = new IntersectionObserver((entries, observer) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) {
                                return;
                            }

                            animateCounter(entry.target);
                            observer.unobserve(entry.target);
                        });
                    }, { threshold: 0.5 });

                    counters.forEach((counter) => counterObserver.observe(counter));
                }
            }

            if (heroShell && heroStage && !prefersReducedMotion && !('ontouchstart' in window)) {
                const resetHero = function () {
                    heroStage.style.transform = 'translate3d(0px, 0px, 0px)';
                };

                heroShell.addEventListener('mousemove', function (event) {
                    if (window.innerWidth < 1060) {
                        resetHero();
                        return;
                    }

                    const bounds = heroShell.getBoundingClientRect();
                    const ratioX = (event.clientX - bounds.left) / bounds.width - 0.5;
                    const ratioY = (event.clientY - bounds.top) / bounds.height - 0.5;

                    heroStage.style.transform = `translate3d(${ratioX * 16}px, ${ratioY * 14}px, 0)`;
                });

                heroShell.addEventListener('mouseleave', resetHero);
                window.addEventListener('resize', resetHero, { passive: true });
            }

            const trackCta = (id, label) => {
                const button = document.getElementById(id);

                if (!button) {
                    return;
                }

                button.addEventListener('click', function () {
                    if (typeof window.gtag !== 'function') {
                        return;
                    }

                    window.gtag('event', 'landing_cta_click', {
                        cta_label: label,
                    });
                });
            };

            trackCta('landing-start-student', 'hero_student');
            trackCta('landing-start-teacher', 'hero_teacher');
            trackCta('landing-bottom-student', 'footer_student');
            trackCta('landing-bottom-teacher', 'footer_teacher');
        })();
    </script>
@endsection
