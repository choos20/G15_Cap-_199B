<style>
    .mfa-page-card {
        max-width: 560px;
    }

    .mfa-eyebrow {
        display: inline-flex;
        align-items: center;
        min-height: 26px;
        margin-bottom: 12px;
        padding: 4px 10px;
        border: 1px solid rgba(145, 238, 195, 0.35);
        border-radius: 999px;
        background: rgba(145, 238, 195, 0.12);
        color: #bfffd8;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .mfa-page-card .section-subtitle {
        line-height: 1.55;
    }

    .mfa-back-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-top: 4px;
        color: #d9fae4;
        font-size: 0.8rem;
        font-weight: 700;
        text-decoration: none;
    }

    .mfa-back-link svg {
        width: 16px;
        height: 16px;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .mfa-back-link:hover {
        color: #ffffff;
        text-decoration: underline;
    }

    .mfa-step {
        margin-top: 20px;
        padding: 18px;
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.08);
    }

    .mfa-step h3 {
        margin: 0 0 8px;
        color: #f4fff7;
        font-size: 0.96rem;
    }

    .mfa-step p,
    .mfa-help,
    .mfa-recovery-note {
        color: rgba(238, 247, 239, 0.76);
        font-size: 0.78rem;
        line-height: 1.55;
    }

    .mfa-step p {
        margin: 8px 0 0;
    }

    .mfa-qr-frame {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: min(100%, 280px);
        min-height: 280px;
        margin: 14px auto;
        padding: 12px;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 12px 28px rgba(5, 20, 10, 0.22);
    }

    .mfa-qr-frame::after {
        content: '';
        position: absolute;
        inset: 10px;
        border: 2px solid rgba(5, 150, 105, 0.45);
        border-radius: 10px;
        pointer-events: none;
        animation: mfa-qr-frame-pulse 2.4s ease-in-out infinite;
    }

    .mfa-qr-corner {
        position: absolute;
        z-index: 2;
        width: 30px;
        height: 30px;
        border-color: #059669;
        border-style: solid;
        pointer-events: none;
    }

    .mfa-qr-corner--tl { top: 14px; left: 14px; border-width: 3px 0 0 3px; border-radius: 9px 0 0 0; }
    .mfa-qr-corner--tr { top: 14px; right: 14px; border-width: 3px 3px 0 0; border-radius: 0 9px 0 0; }
    .mfa-qr-corner--bl { bottom: 14px; left: 14px; border-width: 0 0 3px 3px; border-radius: 0 0 0 9px; }
    .mfa-qr-corner--br { right: 14px; bottom: 14px; border-width: 0 3px 3px 0; border-radius: 0 0 9px 0; }

    .mfa-qr-status {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin: 14px auto 0;
        color: #caffdc;
        font-size: .76rem;
        font-weight: 700;
    }

    .mfa-qr-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #86efac;
        box-shadow: 0 0 0 5px rgba(134, 239, 172, .12);
        animation: mfa-qr-status-pulse 1.8s ease-in-out infinite;
    }

    .mfa-qr-frame img {
        display: block;
        width: 100%;
        height: auto;
        max-width: 260px;
    }

    .mfa-manual-key {
        margin-top: 14px;
    }

    .mfa-manual-key summary {
        color: #caffdc;
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .mfa-manual-key code {
        display: block;
        margin-top: 10px;
        padding: 12px;
        overflow-wrap: anywhere;
        border: 1px dashed rgba(145, 238, 195, 0.45);
        border-radius: 9px;
        background: rgba(0, 0, 0, 0.16);
        color: #effff4;
        font-size: 0.9rem;
        letter-spacing: 0.08em;
        text-align: center;
    }

    .mfa-code-input {
        letter-spacing: 0.32em;
        text-align: center;
        font-variant-numeric: tabular-nums;
    }

    .mfa-primary-button,
    .mfa-secondary-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 46px;
        margin-top: 18px;
        padding: 12px 16px;
        border-radius: 11px;
        font-size: 0.9rem;
        font-weight: 700;
        text-decoration: none;
    }

    .mfa-primary-button {
        background: linear-gradient(135deg, #2ca56f, #17824f);
        color: #ffffff;
    }

    .mfa-secondary-button {
        border: 1px solid rgba(145, 238, 195, 0.52);
        background: rgba(255, 255, 255, 0.08);
        color: #effff4;
        cursor: pointer;
    }

    .mfa-primary-button:hover,
    .mfa-secondary-button:hover {
        filter: brightness(1.06);
        transform: translateY(-1px);
    }

    .mfa-primary-button:focus-visible,
    .mfa-secondary-button:focus-visible,
    .mfa-manual-key summary:focus-visible,
    .mfa-back-link:focus-visible,
    .mfa-logout:focus-visible {
        outline: 3px solid rgba(168, 243, 200, 0.72);
        outline-offset: 3px;
    }

    .mfa-divider {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 22px 0 4px;
        color: rgba(238, 247, 239, 0.55);
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .mfa-divider::before,
    .mfa-divider::after {
        content: '';
        height: 1px;
        flex: 1;
        background: rgba(255, 255, 255, 0.16);
    }

    .mfa-recovery-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
        margin: 16px 0;
    }

    .mfa-recovery-code {
        display: block;
        padding: 10px 8px;
        border: 1px solid rgba(145, 238, 195, 0.3);
        border-radius: 8px;
        background: rgba(0, 0, 0, 0.16);
        color: #effff4;
        font-size: 0.82rem;
        letter-spacing: 0.08em;
        text-align: center;
    }

    .mfa-recovery-warning {
        padding: 11px 12px;
        border: 1px solid rgba(252, 211, 77, 0.45);
        border-radius: 9px;
        background: rgba(120, 85, 0, 0.18);
        color: #fff4bf;
        font-size: 0.78rem;
        line-height: 1.5;
    }

    .mfa-help {
        margin: 18px 0 0;
        text-align: center;
    }

    .mfa-logout {
        display: inline-block;
        margin-top: 12px;
        color: #d9fae4;
        font-size: 0.78rem;
        font-weight: 600;
        text-decoration: none;
    }

    .mfa-logout:hover {
        text-decoration: underline;
    }

    @keyframes mfa-qr-frame-pulse {
        50% { opacity: .35; transform: scale(.985); }
    }

    @keyframes mfa-qr-status-pulse {
        50% { opacity: .45; transform: scale(.78); }
    }

    @media (max-width: 480px) {
        .mfa-page-card {
            padding: 30px 20px;
        }

        .mfa-step {
            padding: 14px;
        }

        .mfa-qr-frame {
            min-height: 230px;
        }

        .mfa-qr-corner {
            width: 26px;
            height: 26px;
        }

        .mfa-qr-corner--tl { top: 11px; left: 11px; }
        .mfa-qr-corner--tr { top: 11px; right: 11px; }
        .mfa-qr-corner--bl { bottom: 11px; left: 11px; }
        .mfa-qr-corner--br { right: 11px; bottom: 11px; }

        .mfa-recovery-list {
            grid-template-columns: 1fr;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .mfa-qr-frame::after,
        .mfa-qr-status-dot {
            animation: none;
        }
    }
</style>
