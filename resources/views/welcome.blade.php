<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="dHermes: gestión de cartera, cobros, arqueos y verificación de comprobantes.">
    <title>{{ config('app.name', 'dHermes') }} · Cartera y cobranzas</title>

    <x-pwa-meta />
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            color-scheme: light;
            --paper: #f3f0e8;
            --paper-light: #faf8f1;
            --ink: #20221e;
            --muted: #68675f;
            --line: #d8d3c7;
            --accent: #e7653b;
            --accent-dark: #a94327;
            --green: #4f6856;
            --sans: Inter, "Segoe UI", Helvetica, Arial, sans-serif;
            --serif: Georgia, "Times New Roman", serif;
            --mono: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        [hidden] {
            display: none !important;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-width: 320px;
            min-height: 100vh;
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: var(--sans);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font: inherit;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 4px;
        }

        .site-shell {
            width: min(calc(100% - 64px), 1360px);
            margin-inline: auto;
        }

        /* Encabezado */
        .masthead {
            min-height: 82px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
            border-bottom: 1px solid var(--line);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            flex: 0 0 auto;
        }

        .brand__text {
            display: grid;
            gap: 3px;
        }

        .brand__name {
            font-size: 16px;
            font-weight: 650;
            letter-spacing: -0.045em;
        }

        .brand__descriptor {
            color: var(--muted);
            font-family: var(--mono);
            font-size: 8px;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .site-nav {
            display: flex;
            align-items: center;
            gap: clamp(18px, 3vw, 42px);
            margin-left: auto;
            color: var(--muted);
            font-size: 12px;
        }

        .site-nav a {
            transition: color 150ms ease;
        }

        .site-nav a:hover {
            color: var(--ink);
        }

        .masthead__actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .install-button,
        .login-link {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 0 14px;
            border: 1px solid var(--line);
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .install-button {
            background: transparent;
            color: var(--ink);
            cursor: pointer;
        }

        .login-link {
            border-color: var(--ink);
            background: var(--ink);
            color: var(--paper-light);
            transition: background 150ms ease;
        }

        .login-link:hover {
            background: #383a34;
        }

        .link-arrow {
            font-size: 16px;
            line-height: 1;
        }

        /* Hero */
        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(360px, 0.92fr);
            align-items: center;
            gap: clamp(44px, 7vw, 108px);
            padding: clamp(72px, 9vw, 128px) 0 clamp(78px, 9vw, 120px);
        }

        .eyebrow {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--muted);
            font-family: var(--mono);
            font-size: 10px;
            letter-spacing: 0.08em;
            line-height: 1.4;
            text-transform: uppercase;
        }

        .eyebrow__index {
            color: var(--accent-dark);
        }

        .eyebrow__rule {
            width: 28px;
            height: 1px;
            background: var(--accent);
        }

        .hero__title {
            max-width: 760px;
            margin: 28px 0 0;
            font-size: clamp(52px, 7.1vw, 100px);
            font-weight: 550;
            letter-spacing: -0.078em;
            line-height: 0.96;
        }

        .hero__title em {
            display: block;
            margin-top: 5px;
            color: var(--accent-dark);
            font-family: var(--serif);
            font-size: 0.87em;
            font-weight: 400;
            letter-spacing: -0.075em;
            line-height: 1.03;
        }

        .hero__intro {
            max-width: 545px;
            margin: 28px 0 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.75;
        }

        .hero__intro strong {
            color: var(--ink);
            font-weight: 600;
        }

        .hero__actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 22px;
            margin-top: 32px;
        }

        .button-primary {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            padding: 0 18px;
            background: var(--accent);
            color: #201d19;
            font-size: 13px;
            font-weight: 650;
            transition: background 150ms ease, transform 150ms ease;
        }

        .button-primary:hover {
            background: #f07850;
            transform: translateY(-1px);
        }

        .text-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
            border-bottom: 1px solid var(--line);
            color: var(--ink);
            font-size: 12px;
            font-weight: 550;
        }

        .text-link:hover {
            border-color: var(--accent);
        }

        .install-help {
            max-width: 390px;
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .hero__footnote {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 20px;
            margin-top: 48px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-family: var(--mono);
            font-size: 9px;
            letter-spacing: 0.045em;
            line-height: 1.6;
            text-transform: uppercase;
        }

        .hero__footnote span+span::before {
            margin-right: 20px;
            color: var(--accent);
            content: "/";
        }

        /* Escenario del comprobante térmico real */
        .product-preview {
            position: relative;
            max-width: 500px;
            margin: 0 0 0 auto;
            padding: 0 0 18px 18px;
        }

        .product-preview::before {
            position: absolute;
            z-index: -1;
            inset: 26px 18px 0 0;
            border: 1px solid #c9c2b4;
            content: "";
        }

        .preview-caption {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 0 0 12px;
            color: var(--muted);
            font-family: var(--mono);
            font-size: 9px;
            letter-spacing: 0.06em;
            line-height: 1.5;
            text-transform: uppercase;
        }

        .preview-caption span:last-child {
            color: var(--accent-dark);
            text-align: right;
        }

        .receipt-stage {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: clamp(22px, 3.2vw, 34px) 16px;
            border: 1px solid #d3cdbf;
            background:
                radial-gradient(circle at 1px 1px, rgb(32 34 30 / 10%) 1px, transparent 0) 0 0 / 14px 14px,
                var(--paper-light);
            box-shadow: 7px 7px 0 rgb(32 34 30 / 6%);
        }

        .receipt-stage::before {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            width: 3px;
            background: var(--accent);
            content: "";
        }

        .receipt-scale {
            width: 80mm;
            max-width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px dashed #b9b2a3;
            color: var(--muted);
            font-family: var(--mono);
            font-size: 8px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        /* ==========================================================
           DISEÑO REAL DEL COMPROBANTE IMPRIMIBLE (80mm)
           ========================================================== */
        .receipt {
            width: 80mm;
            max-width: 100%;
            /* padding arriba = 0 porque el .prefeed ya hace el colchón */
            padding: 0 4mm 2mm 4mm;
            background: #fff;
            color: #000;
            font-family: "Courier New", Courier, monospace;
            font-weight: 500;
            font-size: 8pt;
            line-height: 1.15;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .14);
        }

        .receipt * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* Espacio en blanco que absorbe la precarga de la impresora */
        .receipt .prefeed {
            height: 5mm;
        }

        .receipt .center {
            text-align: center;
        }

        .receipt .app {
            font-size: 6pt;
            font-weight: 600;
            letter-spacing: 0;
            text-transform: uppercase;
            opacity: .9;
        }

        .receipt .tenant {
            font-size: 8.5pt;
            font-weight: 600;
            line-height: 1.1;
            margin-top: 0.5mm;
            word-break: break-word;
        }

        .receipt .doctype {
            font-size: 6pt;
            margin-top: 0.5mm;
            text-transform: uppercase;
            opacity: .9;
        }

        .receipt .status {
            display: inline-block;
            margin-top: 0.8mm;
            border: 0.5pt dotted #000;
            padding: 0.2mm 1mm;
            font-size: 6pt;
            font-weight: 600;
            letter-spacing: 0;
        }

        .receipt .sep {
            border-top: 1px dotted #000;
            margin: 1.5mm 0;
            opacity: .9;
        }

        .receipt .line {
            display: flex;
            gap: 1mm;
            align-items: baseline;
            padding: 0.5mm 0;
            font-size: 7pt;
        }

        .receipt .line .l {
            font-size: 7pt;
            font-weight: 600;
            white-space: nowrap;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .receipt .line .v {
            font-weight: 600;
            font-size: 7pt;
            word-break: break-all;
            flex: 1;
            text-align: right;
        }

        .receipt .line .v.left {
            text-align: left;
        }

        .receipt .line .v a {
            text-decoration: underline;
        }

        .receipt .cols {
            display: flex;
            gap: 1.5mm;
        }

        .receipt .cols .col {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0.5mm 0;
        }

        .receipt .cols .col .l {
            font-size: 7pt;
            font-weight: 600;
            opacity: .9;
            text-transform: uppercase;
        }

        .receipt .cols .col .v {
            font-size: 7pt;
            font-weight: 600;
            word-break: break-all;
            line-height: 1.1;
        }

        .receipt .mono {
            word-break: break-all;
        }

        .receipt .qr-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.8mm;
            padding: 0.8mm 0;
        }

        .receipt .qr-box {
            width: 20mm;
            height: 20mm;
            line-height: 0;
        }

        .receipt .qr-box svg {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }

        .receipt .url {
            font-size: 6pt;
            text-align: center;
            word-break: break-all;
            line-height: 1.1;
            max-width: 72mm;
        }

        .receipt .hint {
            font-size: 6pt;
            opacity: .9;
            text-align: center;
        }

        .receipt .footer {
            display: block;
            padding: 0;
            color: #000;
            font-size: 6pt;
            line-height: 1.15;
            opacity: .9;
            text-align: center;
            margin-top: 1mm;
        }

        /* Flujo de trabajo */
        .workflow {
            padding: clamp(64px, 8vw, 108px) 0;
            border-top: 1px solid var(--line);
        }

        .workflow__intro {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(260px, 0.65fr);
            align-items: end;
            gap: 48px;
            margin-bottom: 42px;
        }

        .workflow__title {
            max-width: 730px;
            margin: 20px 0 0;
            font-size: clamp(36px, 5vw, 60px);
            font-weight: 500;
            letter-spacing: -0.07em;
            line-height: 1.02;
        }

        .workflow__title em {
            color: var(--accent-dark);
            font-family: var(--serif);
            font-weight: 400;
            letter-spacing: -0.06em;
        }

        .workflow__description {
            max-width: 420px;
            margin: 0 0 4px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.75;
        }

        .step-list {
            border-top: 1px solid var(--ink);
        }

        .step {
            display: grid;
            grid-template-columns: 52px minmax(190px, 0.9fr) minmax(250px, 1.35fr) 100px;
            align-items: center;
            gap: 22px;
            padding: 22px 0;
            border-bottom: 1px solid var(--line);
        }

        .step__number {
            color: var(--accent-dark);
            font-family: var(--mono);
            font-size: 12px;
        }

        .step__heading small {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-family: var(--mono);
            font-size: 8px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .step__heading h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.04em;
        }

        .step__description {
            max-width: 450px;
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.65;
        }

        .step__phase {
            justify-self: end;
            color: var(--muted);
            font-family: var(--mono);
            font-size: 8px;
            letter-spacing: 0.04em;
            text-align: right;
            text-transform: uppercase;
        }

        /* Cierre */
        .closing {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
            padding: clamp(26px, 4vw, 42px);
            background: var(--ink);
            color: var(--paper-light);
        }

        .closing__copy {
            max-width: 760px;
        }

        .closing__label {
            margin: 0 0 12px;
            color: #c9c5b9;
            font-family: var(--mono);
            font-size: 9px;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .closing__title {
            margin: 0;
            font-size: clamp(25px, 3.3vw, 42px);
            font-weight: 500;
            letter-spacing: -0.06em;
            line-height: 1.1;
        }

        .closing__link {
            min-height: 46px;
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            gap: 14px;
            padding: 0 16px;
            background: var(--accent);
            color: var(--ink);
            font-size: 12px;
            font-weight: 650;
            transition: background 150ms ease;
        }

        .closing__link:hover {
            background: #f07850;
        }

        /* Pie general del sitio */
        .site-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 22px 0 26px;
            color: var(--muted);
            font-size: 10px;
        }

        .site-footer p {
            margin: 0;
        }

        .site-footer__brand {
            color: var(--ink);
            font-weight: 600;
        }

        @media (max-width: 960px) {
            .site-nav {
                display: none;
            }

            .hero {
                grid-template-columns: minmax(0, 1fr) minmax(320px, 0.9fr);
                gap: 38px;
            }

            .hero__title {
                font-size: clamp(52px, 7vw, 76px);
            }

            .step {
                grid-template-columns: 38px minmax(170px, 0.9fr) minmax(220px, 1.3fr) 76px;
                gap: 16px;
            }
        }

        @media (max-width: 760px) {
            .site-shell {
                width: min(calc(100% - 36px), 560px);
            }

            .masthead {
                min-height: 70px;
                gap: 12px;
            }

            .brand__descriptor {
                display: none;
            }

            .masthead__actions {
                gap: 7px;
            }

            .install-button,
            .login-link {
                min-height: 38px;
                padding-inline: 10px;
                font-size: 11px;
            }

            .hero {
                grid-template-columns: 1fr;
                gap: 55px;
                padding: 68px 0 72px;
            }

            .hero__title {
                max-width: 620px;
                font-size: clamp(52px, 12vw, 78px);
            }

            .hero__intro {
                max-width: 580px;
                font-size: 15px;
            }

            .hero__footnote {
                margin-top: 34px;
            }

            .product-preview {
                width: min(100%, 500px);
                margin-inline: auto;
            }

            .workflow__intro {
                grid-template-columns: 1fr;
                gap: 18px;
                margin-bottom: 30px;
            }

            .workflow__description {
                max-width: 560px;
            }

            .step {
                grid-template-columns: 34px minmax(0, 1fr) auto;
                gap: 12px;
                padding: 18px 0;
            }

            .step__description {
                grid-column: 2 / 4;
                max-width: none;
            }

            .step__phase {
                grid-column: 3;
                grid-row: 1;
            }

            .closing {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 460px) {
            .site-shell {
                width: calc(100% - 28px);
            }

            .brand {
                gap: 8px;
            }

            .brand__mark {
                width: 34px;
                height: 34px;
            }

            .brand__name {
                font-size: 14px;
            }

            .login-link {
                gap: 6px;
            }

            .hero {
                padding-top: 54px;
            }

            .hero__title {
                margin-top: 22px;
                font-size: clamp(48px, 13.3vw, 62px);
            }

            .hero__actions {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .hero__footnote {
                gap: 7px 12px;
                font-size: 8px;
            }

            .hero__footnote span+span::before {
                margin-right: 12px;
            }

            .product-preview {
                padding-left: 10px;
            }

            .product-preview::before {
                right: 10px;
            }

            .receipt-stage {
                padding: 16px 10px;
            }

            .step {
                grid-template-columns: 26px minmax(0, 1fr);
            }

            .step__phase {
                grid-column: 2;
                grid-row: 2;
                justify-self: start;
                margin-top: -5px;
                text-align: left;
            }

            .step__description {
                grid-column: 2;
            }

            .site-footer {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>
    <div class="site-shell">
        <header class="masthead">
            <a class="brand" href="{{ route('home') }}" aria-label="dHermes, inicio">
                <x-brand-mark style="color: var(--ink);" aria-hidden="true" />
                <span class="brand__text">
                    <span class="brand__name">dHermes</span>
                    <span class="brand__descriptor">Cartera y cobranzas</span>
                </span>
            </a>

            <nav class="site-nav" aria-label="Navegación principal">
                <a href="#producto">Comprobante 80mm</a>
                <a href="#flujo">Cómo funciona</a>
            </nav>

            <div class="masthead__actions">
                <button type="button" class="install-button" data-pwa-install hidden>
                    Instalar app <span aria-hidden="true">↓</span>
                </button>

                <a class="login-link" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                    {{ auth()->check() ? 'Ir al panel' : 'Iniciar sesión' }}
                    <span class="link-arrow" aria-hidden="true">↗</span>
                </a>
            </div>
        </header>

        <main>
            <section class="hero" aria-labelledby="hero-title">
                <div class="hero__copy">
                    <div class="eyebrow">
                        <span class="eyebrow__index">DH / 01</span>
                        <span class="eyebrow__rule" aria-hidden="true"></span>
                        <span>Operación diaria de cobranza</span>
                    </div>

                    <h1 class="hero__title" id="hero-title">
                        El cobro no termina
                        <em>cuando entra el dinero.</em>
                    </h1>

                    <p class="hero__intro">
                        dHermes conecta la cartera de <strong>SAP Business One</strong>,
                        el registro de pagos y los arqueos. Cada operación conserva su contexto,
                        su respaldo y su comprobante verificable.
                    </p>

                    <div class="hero__actions">
                        <a class="button-primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                            {{ auth()->check() ? 'Continuar al panel' : 'Acceder a dHermes' }}
                            <span class="link-arrow" aria-hidden="true">→</span>
                        </a>
                        <a class="text-link" href="#flujo">
                            Ver el flujo de trabajo <span aria-hidden="true">↓</span>
                        </a>
                    </div>

                    <p class="install-help" data-pwa-ios-instructions hidden>
                        En Safari, toca Compartir y luego “Añadir a pantalla de inicio”.
                    </p>

                    <div class="hero__footnote" aria-label="Funciones principales">
                        <span>Cartera SAP</span>
                        <span>Cobros respaldados</span>
                        <span>Impresión 80mm con QR</span>
                    </div>
                </div>

                <figure class="product-preview" id="producto">
                    <figcaption class="preview-caption">
                        <span>Comprobante imprimible · 80mm</span>
                        <span>Vista ilustrativa · formato real</span>
                    </figcaption>

                    <div class="receipt-stage">
                        <div class="receipt-scale" aria-hidden="true">
                            <span>├──── 80 mm ────┤</span>
                            <span>Papel continuo</span>
                        </div>

                        {{-- DISEÑO REAL DEL COMPROBANTE IMPRIMIBLE --}}
                        <div class="receipt" aria-label="Ejemplo del comprobante imprimible de 80mm">
                            {{-- COLCHÓN: absorbe la precarga de la impresora --}}
                            <div class="prefeed"></div>

                            {{-- HEADER --}}
                            <div class="center">
                                <div class="app">///////////////////////////////////</div>
                                <div class="app">{{ config('app.name', 'dHermes') }}</div>
                                <div class="tenant">Comercial del Norte S.A.</div>
                                <div class="doctype">Comprobante de cobro</div>
                                <div class="status">VALIDO</div>
                            </div>

                            <div class="sep"></div>

                            {{-- DATA --}}
                            <div class="line">
                                <span class="l">COD:</span>
                                <span class="v mono left">DH-2025-9F4A8C21</span>
                            </div>

                            <div class="cols">
                                <div class="col">
                                    <span class="l">Monto</span>
                                    <span class="v">USD 1.250,00</span>
                                </div>
                                <div class="col">
                                    <span class="l">Fecha</span>
                                    <span class="v">06/03/2025</span>
                                </div>
                            </div>
                            <div class="cols">
                                <div class="col">
                                    <span class="l">Factura</span>
                                    <span class="v">D:10482/F:4819</span>
                                </div>
                                <div class="col">
                                    <span class="l">Voucher</span>
                                    <span class="v">TRF-009481</span>
                                </div>
                            </div>
                            <div class="line">
                                <span class="l">Cliente:</span>
                                <span class="v left">Almacén Central Rivera S.R.L.</span>
                            </div>
                            <div class="line">
                                <span class="l">Adj:</span>
                                <span class="v left">SI <span class="no-print">- <a
                                            href="#producto">ver</a></span></span>
                            </div>

                            <div class="sep"></div>

                            {{-- QR --}}
                            <div class="qr-wrap">
                                <div class="qr-box">
                                    <svg viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg"
                                        shape-rendering="crispEdges" aria-hidden="true">
                                        <rect width="29" height="29" fill="#fff" />
                                        <path fill="#000"
                                            d="M2 2h7v7H2V2zm1 1v5h5V3H3zm1 1h3v3H4V4zm16-2h7v7h-7V2zm1 1v5h5V3h-5zm1 1h3v3h-3V4zM2 20h7v7H2v-7zm1 1v5h5v-5H3zm1 1h3v3H4v-3zM11 2h2v1h-2V2zm3 0h1v2h-1V2zm2 0h2v1h-2V2zm-5 2h1v2h-1V4zm2 1h2v2h-2V5zm3 0h1v1h-1V5zm2 1h1v2h-1V6zm-7 2h2v1h-2V8zm4 0h2v1h-2V8zM2 11h2v1H2v-1zm4 0h1v2H6v-2zm2 0h2v1H8v-1zm3 0h1v1h-1v-1zm3 0h2v2h-2v-2zm4 0h1v1h-1v-1zm2 0h2v1h-2v-1zm4 0h3v1h-3v-1zM3 13h2v1H3v-1zm4 1h2v1H7v-1zm3-1h2v2h-2v-2zm6 1h2v1h-2v-1zm3-1h2v2h-2v-2zm4 1h2v1h-2v-1zM2 15h1v2H2v-2zm3 0h2v1H5v-1zm4 1h1v2H9v-2zm4-1h2v1h-2v-1zm3 1h1v2h-1v-2zm3-1h2v1h-2v-1zm4 1h1v2h-1v-2zm3-1h1v2h-1v-2zM4 17h3v1H4v-1zm6 1h2v1h-2v-1zm4-1h1v2h-1v-2zm4 1h3v1h-3v-1zm6 0h2v1h-2v-1zM11 20h1v2h-1v-2zm3 0h2v1h-2v-1zm4 0h3v3h-3v-3zm1 1v1h1v-1h-1zm4-1h2v1h-2v-1zm3 1h1v2h-1v-2zM12 22h2v2h-2v-2zm4 1h1v2h-1v-2zm6 0h2v2h-2v-2zm3 1h2v1h-2v-1zM11 25h2v2h-2v-2zm4 1h3v1h-3v-1zm4-1h2v2h-2v-2zm4 1h2v1h-2v-1zm3-1h1v2h-1v-2z" />
                                    </svg>
                                </div>
                                <div class="url mono">{{ url('/v/DH-2025-9F4A8C21') }}</div>
                                <div class="hint">Escanee para verificar</div>
                            </div>

                            <div class="sep"></div>

                            <div class="footer">
                                Emitido electrónicamente por Comercial del Norte S.A.. Valide en la URL. No comparta si
                                es REVOCADO.
                            </div>
                            <div class="footer">///////////////////////////////////</div>
                        </div>
                    </div>
                </figure>
            </section>

            <section class="workflow" id="flujo" aria-labelledby="workflow-title">
                <div class="workflow__intro">
                    <div>
                        <div class="eyebrow">
                            <span class="eyebrow__index">DH / 02</span>
                            <span class="eyebrow__rule" aria-hidden="true"></span>
                            <span>Del saldo al cierre</span>
                        </div>

                        <h2 class="workflow__title" id="workflow-title">
                            Una operación.<br>
                            <em>Un rastro completo.</em>
                        </h2>
                    </div>

                    <p class="workflow__description">
                        La información no debería quedar repartida entre cartera, comprobantes
                        y arqueos. dHermes mantiene cada parte ligada a la misma operación.
                    </p>
                </div>

                <div class="step-list">
                    <article class="step">
                        <span class="step__number">01</span>
                        <div class="step__heading">
                            <small>Antes del cobro</small>
                            <h3>Cartera con contexto</h3>
                        </div>
                        <p class="step__description">
                            Consulta saldos por cliente, vendedor y moneda a partir de la información
                            proveniente de SAP Business One.
                        </p>
                        <span class="step__phase">Cartera</span>
                    </article>

                    <article class="step">
                        <span class="step__number">02</span>
                        <div class="step__heading">
                            <small>Al registrar el pago</small>
                            <h3>Cobros con respaldo</h3>
                        </div>
                        <p class="step__description">
                            Registra el medio de pago, los documentos aplicados (`D/F`) y el comprobante
                            adjunto en el historial de cobranza.
                        </p>
                        <span class="step__phase">Cobranza</span>
                    </article>

                    <article class="step">
                        <span class="step__number">03</span>
                        <div class="step__heading">
                            <small>Al terminar la jornada</small>
                            <h3>Arqueo y recibo 80mm</h3>
                        </div>
                        <p class="step__description">
                            Compara el monto esperado con el contado e imprime el comprobante térmico con
                            verificación pública mediante QR.
                        </p>
                        <span class="step__phase">Control</span>
                    </article>
                </div>
            </section>

            <section class="closing" aria-labelledby="closing-title">
                <div class="closing__copy">
                    <p class="closing__label">DH / 03 · El siguiente paso</p>
                    <h2 class="closing__title" id="closing-title">
                        Que el cierre del día también quede en el sistema.
                    </h2>
                </div>

                <a class="closing__link" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                    {{ auth()->check() ? 'Abrir el panel' : 'Entrar a dHermes' }}
                    <span class="link-arrow" aria-hidden="true">↗</span>
                </a>
            </section>
        </main>

        <footer class="site-footer">
            <p><span class="site-footer__brand">dHermes</span> · Gestión de cartera y cobranzas</p>
            <p>Aplicación web instalable para el trabajo diario.</p>
        </footer>
    </div>
</body>

</html>

