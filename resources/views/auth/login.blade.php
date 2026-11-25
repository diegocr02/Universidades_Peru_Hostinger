@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/login/login-base.css') }}?v=8">
    <link rel="stylesheet" href="{{ asset('css/login/login.css') }}?v=1">
@endpush

@section('content')
    <div class="login-page login-cni-v2">
        <img src="{{ asset('images/fondos/fondo-login.webp') }}"
             alt="" class="login-background" aria-hidden="true">

        <svg class="login-backdrop-art" viewBox="0 0 1600 900" preserveAspectRatio="none"
             xmlns="http://www.w3.org/2000/svg" fill="none" aria-hidden="true">
            <defs>
                <linearGradient id="cniRibbonTop" x1="0" y1="0" x2="1" y2="1">
                    <stop stop-color="#08a4ff" stop-opacity=".58"/>
                    <stop offset=".5" stop-color="#0650c4" stop-opacity=".74"/>
                    <stop offset="1" stop-color="#062552" stop-opacity=".18"/>
                </linearGradient>
                <linearGradient id="cniRibbonBottom" x1="0" y1="0" x2="0" y2="1">
                    <stop stop-color="#0b6de0" stop-opacity=".86"/>
                    <stop offset="1" stop-color="#031439" stop-opacity=".25"/>
                </linearGradient>
            </defs>
            <path d="M0 0H392L208 153 0 264Z" fill="url(#cniRibbonTop)" opacity=".42"/>
            <path d="M0 0H258L0 180Z" fill="#096fe0" opacity=".38"/>
            <path d="M1600 0H1290L1600 230Z" fill="#073d90" opacity=".5"/>
            <path d="M1600 0H1488L1600 125Z" fill="#1397ff" opacity=".42"/>
            <path d="M0 810Q160 792 335 821T870 828Q1190 794 1600 826V900H0Z" fill="url(#cniRibbonBottom)" opacity=".62"/>
            <path d="M0 846Q210 820 415 851T1040 852Q1310 822 1600 854" stroke="#fbd064" stroke-width="3.5" stroke-opacity=".95"/>
            <path d="M0 18Q170-16 296 0" stroke="#ffd270" stroke-width="2" stroke-opacity=".8"/>
            <path d="M1120 0Q1290 184 1458 0" stroke="#ffdc75" stroke-width="3" stroke-opacity=".82"/>
            <path d="M1599 440Q1490 590 1600 755" stroke="#0b7dff" stroke-width="3" stroke-opacity=".4"/>
            <path d="M1500 900Q1540 834 1600 804" stroke="#ffc658" stroke-width="2.5" stroke-opacity=".78"/>
        </svg>

        <section class="login-presentation" aria-labelledby="login-presentation-title">
    <div class="login-presentation-copy">
        <h2 class="login-heading" id="login-presentation-title">
            Tu próximo paso
            <span>empieza <em>aquí.</em></span>
        </h2>
    </div>
    <div class="login-cni-scene" role="img" aria-label="Mascota del CNI en una plataforma luminosa">
        <div class="login-cni-stage" aria-hidden="true"></div>
        <img class="login-cni-cat"
             src="{{ asset('images/mascotas/gatito-cni.webp') }}"
             alt="Gatito del Simulador CNI con uniforme y laptop">
    </div>
</section>

        <section class="login card" aria-labelledby="login-title">
            <div class="login-school-identity">
                <img src="{{ asset('images/cni/escudo-cni.webp') }}"
                     alt="Escudo del Colegio Nacional de Imperial"
                     class="login-school-mark">
                <p class="login-form-kicker">COLEGIO NACIONAL DE IMPERIAL</p>
            </div>

            <div class="login-title-ornament" aria-hidden="true">
                <span></span>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11v5c3.5 3 8.5 3 12 0v-5"/><path d="M22 9v6"/></svg>
                <span></span>
            </div>

            <h1 id="login-title">BIENVENIDO AL <span>SIMULADOR CNI</span></h1>
            <p class="login-instructions">Ingresa con tus credenciales asignadas por el administrador.</p>

            <form method="POST" action="{{ route('login.store') }}" aria-label="Inicia sesión">
                @csrf

                <div class="login-form-field">
                    <label for="usuario">Usuario</label>
                    <div class="login-input-shell @error('usuario') is-invalid @enderror">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>
                        <input id="usuario" name="usuario" value="{{ old('usuario') }}"
                               maxlength="50" autocomplete="username" placeholder="Ingresa tu usuario"
                               aria-invalid="{{ $errors->has('usuario') ? 'true' : 'false' }}"
                               required autofocus>
                    </div>
                    @error('usuario') <p class="login-field-error">{{ $message }}</p> @enderror
                </div>

                <div class="login-form-field">
                    <label for="password">Contraseña</label>
                    <div class="login-input-shell @error('password') is-invalid @enderror">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/></svg>
                        <input id="password" type="password" name="password"
                               maxlength="255" autocomplete="current-password" placeholder="Ingresa tu contraseña"
                               aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                               required>
                        <button type="button" class="login-password-toggle"
                                aria-controls="password" aria-pressed="false"
                                aria-label="Mostrar contraseña" title="Mostrar contraseña">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M2.5 12S6 6 12 6s9.5 6 9.5 6-3.5 6-9.5 6S2.5 12 2.5 12Z"/>
                                <circle cx="12" cy="12" r="2.5"/>
                            </svg>
                        </button>
                    </div>
                    @error('password') <p class="login-field-error">{{ $message }}</p> @enderror
                </div>

                <button class="primary login-submit" type="submit">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6"/>
                    </svg>
                    Iniciar sesión
                </button>
            </form>
            <p class="login-security-note"><span aria-hidden="true">●</span> Acceso institucional seguro</p>
        </section>
    </div>

    <script>
        (function () {
            const toggle = document.querySelector('.login-cni-v2 .login-password-toggle');
            const password = document.getElementById('password');
            if (!toggle || !password) return;
            toggle.addEventListener('click', function () {
                const visible = password.type === 'password';
                password.type = visible ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
                toggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
                toggle.title = visible ? 'Ocultar contraseña' : 'Mostrar contraseña';
            });
        })();
    </script>
@endsection
