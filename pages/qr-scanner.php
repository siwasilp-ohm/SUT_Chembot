<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) {
    header('Location: /v1/pages/login.php');
    exit;
}
$userId = $user['id'];
$roleLevel = (int) ($user['role_level'] ?? 2);
$isAdmin = $roleLevel >= 5;
$isManager = $roleLevel >= 3;
$lang = I18n::getCurrentLang();
$TH = $lang === 'th';
Layout::head($TH ? 'สแกน QR / Barcode' : 'Scan QR / Barcode', [], ['https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js']);
?>
<style>
    :root {
        --g: #10b981;
        --gd: #059669;
        --gg: rgba(16, 185, 129, .25);
        --hdr: var(--hdr-h, 60px)
    }

    * {
        box-sizing: border-box
    }

    /* ─── PAGE SHELL ─── */
    .sc {
        display: grid;
        grid-template-rows: 1fr;
        height: calc(100vh - var(--hdr));
        overflow: hidden;
        background: #0b0f1a;
    }

    /* Mobile: camera top, sheet bottom */
    @media(max-width:899px) {
        .sc {
            grid-template-rows: 45vh 1fr;
            grid-template-columns: 1fr
        }

        .sc-cam {
            grid-row: 1;
            grid-column: 1
        }

        .sc-sheet {
            grid-row: 2;
            grid-column: 1;
            border-radius: 20px 20px 0 0;
            margin-top: -16px
        }
    }

    /* Desktop: camera left, panel right */
    @media(min-width:900px) {
        .sc {
            grid-template-rows: 1fr;
            grid-template-columns: 1fr 400px
        }

        .sc-cam {
            grid-row: 1;
            grid-column: 1
        }

        .sc-sheet {
            grid-row: 1;
            grid-column: 2;
            border-radius: 0;
            border-left: 1px solid #f1f5f9
        }
    }

    /* ─── CAMERA PANEL ─── */
    .sc-cam {
        position: relative;
        background: #000;
        overflow: hidden;
        min-height: 0;
    }

    /* html5-qrcode overrides — explicit height required for mobile */
    #qrBox {
        width: 100% !important;
        height: 100% !important;
        border: none !important;
        background: #000 !important;
        position: absolute !important;
        inset: 0 !important
    }

    #qrBox>div {
        width: 100% !important;
        height: 100% !important;
        position: absolute !important;
        inset: 0 !important
    }

    #qrBox>div>div {
        position: relative !important
    }

    #qrBox video {
        position: absolute !important;
        inset: 0 !important;
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        display: block !important;
        border-radius: 0 !important
    }

    #qrBox canvas {
        position: absolute !important;
        inset: 0 !important;
        width: 100% !important;
        height: 100% !important
    }

    #qrBox img,
    #qrBox__dashboard_section_swaplink,
    #qrBox__status_span,
    #qrBox__dashboard_section_csr,
    .code-outline-highlight,
    .scan-region-highlight-svg,
    #qrBox div[style*="border"] {
        display: none !important
    }

    /* Hide the library-injected shaded region overlay completely */
    .qr-shaded-region {
        display: none !important;
        border: none !important;
        background: none !important;
        box-shadow: none !important;
    }

    /* HTTPS warning */
    .sc-https-warn {
        position: absolute;
        inset: 0;
        z-index: 20;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 14px;
        background: #0b0f1a;
        padding: 20px;
        text-align: center
    }

    .sc-https-warn i {
        font-size: 48px;
        color: #f59e0b
    }

    .sc-https-warn h4 {
        margin: 0;
        color: #fff;
        font-size: 16px;
        font-weight: 700
    }

    .sc-https-warn p {
        margin: 0;
        color: #94a3b8;
        font-size: 12px;
        line-height: 1.7;
        max-width: 280px
    }

    .sc-https-code {
        background: #1e293b;
        color: #10b981;
        font-family: 'Courier New', monospace;
        font-size: 11px;
        padding: 8px 14px;
        border-radius: 8px;
        word-break: break-all;
        max-width: 280px
    }

    .sc-https-warn .sc-open-btn {
        margin-top: 4px
    }

    /* ══════════════════════════════════════════
       BANK-STYLE QR / BARCODE SCANNER FRAME  v4
       ══════════════════════════════════════════ */

    /* Remove old overlay — frame's box-shadow is the dark cutout */
    .sc-overlay { display: none }
    .sc-vig      { display: none }

    /* ── Frame — square viewfinder, true transparent cutout ──
       box-shadow: 0 0 0 9999px darkens everything OUTSIDE the
       frame's transparent rectangle (no gradient, real punch-out). */
    .sc-frame {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -52%);
        /* square — handles both QR and 1D barcodes */
        width:  min(72vw, 256px);
        height: min(72vw, 256px);
        pointer-events: none;
        z-index: 3;
        display: none;
        overflow: visible;
        border-radius: 3px;
        /* true dark surround */
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, .72);
        /* hairline inner edge for depth */
        outline: 1px solid rgba(255,255,255,.06);
    }

    @media(min-width:900px) {
        .sc-frame {
            width:  268px;
            height: 268px;
            transform: translate(-50%, -51%)
        }
    }

    /* ── Corner L-brackets — bank style ──
       Longer arms (44px), thicker (4px), larger radius */
    .sc-c {
        position: absolute;
        width:  44px;
        height: 44px;
        border-style: solid;
        border-color: rgba(255, 255, 255, .97);
        transition:
            border-color .22s ease,
            transform    .22s cubic-bezier(.34, 1.56, .64, 1),
            filter       .22s ease;
    }

    .sc-c.tl { top:-3px; left:-3px;   border-width:4px 0 0 4px; border-radius:7px 0 0 0 }
    .sc-c.tr { top:-3px; right:-3px;  border-width:4px 4px 0 0; border-radius:0 7px 0 0 }
    .sc-c.bl { bottom:-3px; left:-3px;  border-width:0 0 4px 4px; border-radius:0 0 0 7px }
    .sc-c.br { bottom:-3px; right:-3px; border-width:0 4px 4px 0; border-radius:0 0 7px 0 }

    /* Active — green breathing */
    .sc-frame.act .sc-c {
        border-color: var(--g);
        animation: cpulse 2.6s ease-in-out infinite;
    }
    @keyframes cpulse {
        0%,100% { border-color:var(--g);  filter:drop-shadow(0 0 5px rgba(16,185,129,.55)) }
        50%      { border-color:#6ee7b7;  filter:drop-shadow(0 0 16px rgba(16,185,129,1))  }
    }

    /* DETECT — corners snap inward with overshoot */
    .sc-frame.detect .sc-c {
        border-color: #fff !important;
        filter: drop-shadow(0 0 20px rgba(255,255,255,.95)) !important;
        animation: none !important;
    }
    .sc-frame.detect .sc-c.tl { transform: translate( 7px,  7px) }
    .sc-frame.detect .sc-c.tr { transform: translate(-7px,  7px) }
    .sc-frame.detect .sc-c.bl { transform: translate( 7px, -7px) }
    .sc-frame.detect .sc-c.br { transform: translate(-7px, -7px) }

    /* Settle — vibrant green */
    .sc-frame.settle .sc-c {
        border-color: #6ee7b7 !important;
        filter: drop-shadow(0 0 12px rgba(16,185,129,.9)) !important;
        animation: none !important;
    }

    /* ── Edge lines (connect the corners subtly) ── */
    .sc-edge {
        position: absolute;
        left: 42px; right: 42px;
        height: 1px;
        background: rgba(255, 255, 255, .1);
        transition: background .3s;
    }
    .sc-edge.top { top: -1px }
    .sc-edge.bot { bottom: -1px }
    .sc-frame.act   .sc-edge { background: rgba(16,185,129,.3)  }
    .sc-frame.detect .sc-edge { background: rgba(255,255,255,.5) }
    .sc-frame.settle .sc-edge { background: rgba(16,185,129,.6)  }

    /* ── Side track lines (scan-range indicator) ── */
    .sc-track {
        position: absolute;
        top: 42px; bottom: 42px;
        width: 1px;
        background: rgba(255, 255, 255, .08);
        transition: background .3s;
    }
    .sc-track.l { left: -1px }
    .sc-track.r { right: -1px }
    .sc-frame.act    .sc-track { background: rgba(16,185,129,.22) }
    .sc-frame.detect .sc-track { background: rgba(255,255,255,.4) }
    .sc-frame.settle .sc-track { background: rgba(16,185,129,.5)  }

    /* ── Scan line — green, bank-style ──
       Full-width sweep with bright centre hotspot, bloom trail */
    .sc-laser {
        position: absolute;
        left: 0; right: 0;
        height: 2px;
        border-radius: 2px;
        background: linear-gradient(
            90deg,
            transparent               0%,
            rgba(16,185,129,.35)      6%,
            var(--g)                 22%,
            #a7f3d0                  50%,
            var(--g)                 78%,
            rgba(16,185,129,.35)     94%,
            transparent             100%
        );
        box-shadow:
            0 -3px 10px 2px rgba(16,185,129,.5),
            0  3px 10px 2px rgba(16,185,129,.5),
            0   0  28px 6px rgba(16,185,129,.22);
        animation: laser 2.2s ease-in-out infinite;
        opacity: 0;
    }

    /* Centre hotspot glow */
    .sc-laser::before {
        content: '';
        position: absolute;
        left: 50%; top: -7px;
        width: 20px; height: 16px;
        transform: translateX(-50%);
        background: radial-gradient(ellipse, rgba(167,243,208,.95) 0%, transparent 70%);
        filter: blur(3px);
    }

    /* Trailing bloom beneath laser */
    .sc-laser::after {
        content: '';
        position: absolute;
        left: 6%; right: 6%;
        top: 2px; height: 22px;
        background: linear-gradient(to bottom, rgba(16,185,129,.16), transparent);
        filter: blur(5px);
    }

    .sc-laser.on { opacity: 1 }

    @keyframes laser {
        0%   { top: 2px;               opacity: .9  }
        43%  { top: calc(100% - 4px);  opacity: 1   }
        50%  { top: calc(100% - 4px);  opacity: .3  }
        57%  { top: calc(100% - 4px);  opacity: 1   }
        100% { top: 2px;               opacity: .9  }
    }

    /* DETECT — bright white, freeze at centre */
    .sc-frame.detect .sc-laser {
        animation: none !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        background: linear-gradient(90deg, transparent 5%, #fff 40%, #fff 60%, transparent 95%) !important;
        box-shadow:
            0 0 22px 5px rgba(255,255,255,.75),
            0 0 55px 12px rgba(255,255,255,.3) !important;
        opacity: 1 !important;
    }

    /* Settle — saturated green */
    .sc-frame.settle .sc-laser {
        animation: none !important;
        background: linear-gradient(90deg, transparent, #6ee7b7, transparent) !important;
        box-shadow:
            0 0 18px rgba(16,185,129,.95),
            0 0 40px rgba(16,185,129,.42) !important;
        opacity: 1 !important;
    }

    /* ── Ripple rings on detect ── */
    .sc-ring, .sc-ring2 {
        position: absolute;
        inset: -3px;
        border-radius: 8px;
        border: 2px solid transparent;
        pointer-events: none;
        transform-origin: center;
    }
    .sc-ring.fire {
        border-color: rgba(16,185,129,.9);
        animation: rpulse .65s ease-out forwards;
    }
    .sc-ring2.fire {
        border-color: rgba(16,185,129,.5);
        animation: rpulse .85s .14s ease-out forwards;
    }
    @keyframes rpulse {
        0%   { opacity:1; transform:scale(1);    border-width:3px }
        100% { opacity:0; transform:scale(1.14); border-width:1px }
    }

    /* ── Scan label ── */
    .sc-label {
        position: absolute;
        top: calc(100% + 18px);
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, .72);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        color: rgba(255, 255, 255, .9);
        font-size: 12px;
        font-weight: 600;
        padding: 7px 18px 7px 13px;
        border-radius: 24px;
        white-space: nowrap;
        letter-spacing: .3px;
        display: flex;
        align-items: center;
        gap: 9px;
        border: 1px solid rgba(255,255,255,.12);
        transition: color .22s, border-color .22s;
    }

    /* Animated status dot */
    .sc-label::before {
        content: '';
        width: 7px; height: 7px;
        border-radius: 50%;
        background: #64748b;
        box-shadow: 0 0 6px rgba(100,116,139,.8);
        flex-shrink: 0;
        transition: background .25s, box-shadow .25s;
        animation: ldot 1.2s ease-in-out infinite;
    }
    .sc-frame.act .sc-label::before {
        background: var(--g);
        box-shadow: 0 0 9px rgba(16,185,129,.9);
    }
    .sc-frame.detect .sc-label,
    .sc-frame.settle .sc-label {
        color: #6ee7b7;
        border-color: rgba(16,185,129,.45);
    }
    .sc-frame.detect .sc-label::before,
    .sc-frame.settle .sc-label::before {
        background: #fff;
        box-shadow: 0 0 12px rgba(255,255,255,.9);
        animation: none;
    }
    @keyframes ldot {
        0%,100% { opacity:1; transform:scale(1)    }
        50%      { opacity:.2; transform:scale(.65) }
    }

    /* ── Guide bar (hide — frame is square, no centre dash needed) ── */
    .sc-guide { display: none }

    /* ── Green flash overlay ── */
    .sc-flash {
        position: absolute;
        inset: 0;
        background: rgba(16,185,129,.2);
        opacity: 0;
        pointer-events: none;
        z-index: 4;
        transition: opacity .06s;
    }
    .sc-flash.on { opacity: 1 }

    /* top bar */
    .sc-topbar {
        position: absolute;
        top: 10px;
        left: 10px;
        right: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 10
    }

    .sc-tb {
        width: 38px;
        height: 38px;
        border-radius: 11px;
        border: none;
        background: rgba(0, 0, 0, .5);
        backdrop-filter: blur(8px);
        color: #fff;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: .15s
    }

    .sc-tb:hover,
    .sc-tb.on {
        background: var(--g)
    }

    .sc-tb-row {
        display: flex;
        gap: 6px
    }

    .sc-badge {
        background: var(--g);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        padding: 5px 12px;
        border-radius: 20px;
        display: none;
        backdrop-filter: blur(6px)
    }

    /* scan hint bottom */
    .sc-hint {
        position: absolute;
        bottom: 10px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, .55);
        backdrop-filter: blur(8px);
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        padding: 5px 16px;
        border-radius: 20px;
        display: none;
        align-items: center;
        gap: 7px;
        z-index: 10;
        white-space: nowrap
    }

    .sc-hint .dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--g);
        animation: bk .9s ease-in-out infinite
    }

    @keyframes bk {

        0%,
        100% {
            opacity: 1
        }

        50% {
            opacity: .2
        }
    }

    /* no-cam */
    .sc-nocam {
        position: absolute;
        inset: 0;
        z-index: 5;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        background: #0b0f1a;
    }

    .sc-nocam i {
        font-size: 48px;
        color: #1e293b
    }

    .sc-nocam p {
        color: #475569;
        font-size: 13px;
        margin: 0;
        text-align: center;
        max-width: 220px;
        line-height: 1.6
    }

    .sc-open-btn {
        padding: 10px 28px;
        background: var(--g);
        border: none;
        border-radius: 12px;
        color: #fff;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: .15s
    }

    .sc-open-btn:hover {
        background: var(--gd);
        transform: scale(1.03)
    }

    /* ─── SHEET / PANEL ─── */
    .sc-sheet {
        background: #fff;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        z-index: 5;
        box-shadow: -2px 0 20px rgba(0, 0, 0, .08);
    }

    .sc-handle {
        display: flex;
        justify-content: center;
        padding: 9px 0 0;
        flex-shrink: 0
    }

    .sc-handle-bar {
        width: 36px;
        height: 4px;
        border-radius: 2px;
        background: #e2e8f0
    }

    @media(min-width:900px) {
        .sc-handle {
            display: none
        }
    }

    /* manual input bar */
    .sc-inp-row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        flex-shrink: 0
    }

    .sc-inp-wrap {
        flex: 1;
        display: flex;
        align-items: center;
        background: #f8fafc;
        border-radius: 11px;
        border: 1.5px solid #e2e8f0;
        transition: .15s
    }

    .sc-inp-wrap:focus-within {
        border-color: var(--g);
        background: #f0fdf8
    }

    .sc-inp-ico {
        padding: 0 10px;
        color: #cbd5e1;
        font-size: 13px;
        flex-shrink: 0
    }

    .sc-inp {
        flex: 1;
        border: none;
        background: none;
        outline: none;
        font-size: 13px;
        padding: 10px 4px 10px 0;
        font-family: 'Courier New', monospace;
        color: #1e293b;
        min-width: 0
    }

    .sc-inp::placeholder {
        font-family: Inter, sans-serif;
        font-size: 12px;
        color: #cbd5e1
    }

    .sc-gobtn {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        border: none;
        background: var(--g);
        color: #fff;
        font-size: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: .15s;
        flex-shrink: 0
    }

    .sc-gobtn:hover {
        background: var(--gd)
    }

    .sc-gobtn:disabled {
        opacity: .4;
        cursor: not-allowed
    }

    /* section header */
    .sc-sec-hdr {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px 4px;
        flex-shrink: 0
    }

    .sc-sec-hdr h5 {
        margin: 0;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        flex: 1;
        text-transform: uppercase;
        letter-spacing: .5px
    }

    .sc-cnt-lbl {
        font-size: 11px;
        font-weight: 700;
        color: var(--g)
    }

    .sc-clr-btn {
        font-size: 11px;
        color: #94a3b8;
        border: none;
        background: none;
        cursor: pointer;
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 6px
    }

    .sc-clr-btn:hover {
        color: #dc2626;
        background: #fef2f2
    }

    /* ─── CART ─── */
    .sc-cart {
        flex: 1;
        overflow-y: auto;
        padding: 6px 12px 4px
    }

    /* empty */
    .sc-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 24px 20px;
        text-align: center
    }

    .sc-empty i {
        font-size: 40px;
        color: #e2e8f0
    }

    .sc-empty p {
        margin: 0;
        font-size: 12px;
        color: #94a3b8;
        line-height: 1.7;
        white-space: pre-line
    }

    /* ─── CART ITEM CARD ─── */
    .sc-card {
        background: #fcfcfd;
        border: 1.5px solid #f1f5f9;
        border-radius: 14px;
        padding: 11px 12px 10px;
        margin-bottom: 8px;
        position: relative;
        animation: cIn .22s ease;
        transition: border-color .15s, box-shadow .15s;
    }

    .sc-card:hover {
        border-color: #d1fae5;
        box-shadow: 0 2px 10px rgba(16, 185, 129, .07)
    }

    @keyframes cIn {
        from {
            opacity: 0;
            transform: translateY(10px)
        }

        to {
            opacity: 1;
            transform: none
        }
    }

    .sc-card.warn {
        border-color: #fca5a5;
        background: #fff5f5
    }

    .sc-card.dup-flash {
        animation: df .4s ease
    }

    @keyframes df {
        0% {
            background: #fef9c3;
            border-color: #fcd34d
        }

        100% {
            background: #fcfcfd;
            border-color: #f1f5f9
        }
    }

    /* seq badge */
    .sc-seq {
        position: absolute;
        top: -7px;
        left: 10px;
        background: #0f172a;
        color: #fff;
        font-size: 9px;
        font-weight: 800;
        padding: 1px 8px;
        border-radius: 20px;
        letter-spacing: .4px;
    }

    /* card top row */
    .sc-card-top {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 9px
    }

    .sc-avi {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0
    }

    .av-owner {
        background: linear-gradient(135deg, #f3e8ff, #ddd6fe);
        color: #7c3aed
    }

    .av-borrow {
        background: linear-gradient(135deg, #dcfce7, #a7f3d0);
        color: #059669
    }

    .av-other {
        background: linear-gradient(135deg, #fff7ed, #fed7aa);
        color: #ea580c
    }

    .av-err {
        background: #fef2f2;
        color: #dc2626
    }

    .sc-meta {
        flex: 1;
        min-width: 0
    }

    .sc-name {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3
    }

    .sc-sub {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 1px;
        display: flex;
        align-items: center;
        gap: 5px;
        flex-wrap: wrap
    }

    .sc-tag {
        background: #f1f5f9;
        color: #475569;
        font-size: 9px;
        font-weight: 700;
        padding: 1px 7px;
        border-radius: 20px
    }

    .sc-tag.owner {
        background: #f3e8ff;
        color: #7c3aed
    }

    .sc-tag.borrower {
        background: #dcfce7;
        color: #059669
    }

    .sc-tag.other {
        background: #fff7ed;
        color: #ea580c
    }

    .sc-del {
        width: 26px;
        height: 26px;
        border-radius: 8px;
        border: none;
        background: none;
        color: #cbd5e1;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: .12s;
        flex-shrink: 0
    }

    .sc-del:hover {
        background: #fef2f2;
        color: #dc2626
    }

    /* card bottom: stock | qty */
    .sc-card-bot {
        display: flex;
        gap: 8px;
        align-items: stretch
    }

    /* stock box */
    .sc-stock {
        flex: 1;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 10px;
        padding: 7px 10px;
        min-width: 0
    }

    .sc-slbl {
        font-size: 9px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 3px
    }

    .sc-sval {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
        display: flex;
        align-items: baseline;
        gap: 3px
    }

    .sc-sval span {
        font-size: 10px;
        font-weight: 600;
        color: #94a3b8
    }

    .sc-sbar {
        height: 3px;
        background: #e2e8f0;
        border-radius: 2px;
        margin-top: 5px;
        overflow: hidden
    }

    .sc-sfill {
        height: 100%;
        border-radius: 2px;
        transition: width .4s ease;
        background: linear-gradient(90deg, var(--g), #34d399)
    }

    .sc-sfill.low {
        background: linear-gradient(90deg, #f59e0b, #fbbf24)
    }

    .sc-sfill.vl {
        background: linear-gradient(90deg, #ef4444, #f87171)
    }

    /* qty box */
    .sc-qty {
        background: #f0fdf8;
        border: 1.5px solid #a7f3d0;
        border-radius: 10px;
        padding: 7px 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 128px;
        flex-shrink: 0;
    }

    .sc-card.warn .sc-qty {
        border-color: #fca5a5;
        background: #fef2f2
    }

    .sc-qlbl {
        font-size: 9px;
        font-weight: 700;
        color: #047857;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 4px;
        text-align: center
    }

    .sc-card.warn .sc-qlbl {
        color: #dc2626
    }

    .sc-qctrl {
        display: flex;
        align-items: center;
        gap: 5px
    }

    .sc-qbtn {
        width: 26px;
        height: 26px;
        border-radius: 7px;
        border: 1.5px solid #a7f3d0;
        background: #fff;
        color: #047857;
        font-size: 17px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        transition: .12s;
        line-height: 1;
        flex-shrink: 0;
    }

    .sc-qbtn:hover {
        border-color: var(--g);
        background: var(--g);
        color: #fff
    }

    .sc-card.warn .sc-qbtn {
        border-color: #fca5a5;
        color: #dc2626
    }

    .sc-qinp {
        width: 52px;
        text-align: center;
        border: 1.5px solid #a7f3d0;
        border-radius: 7px;
        font-size: 15px;
        font-weight: 800;
        color: #047857;
        padding: 3px 2px;
        background: #fff;
        outline: none;
        -moz-appearance: textfield;
    }

    .sc-qinp::-webkit-inner-spin-button,
    .sc-qinp::-webkit-outer-spin-button {
        -webkit-appearance: none
    }

    .sc-qinp:focus {
        border-color: var(--g);
        box-shadow: 0 0 0 2px var(--gg)
    }

    .sc-card.warn .sc-qinp {
        border-color: #fca5a5;
        color: #dc2626
    }

    .sc-qunit {
        font-size: 10px;
        font-weight: 700;
        color: #059669;
        margin-top: 3px;
        text-align: center
    }

    .sc-card.warn .sc-qunit {
        color: #ef4444
    }

    /* warn message */
    .sc-warn-msg {
        font-size: 10px;
        color: #ef4444;
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 4px
    }

    /* ─── FOOTER ─── */
    .sc-footer {
        padding: 10px 14px 12px;
        border-top: 1px solid #f1f5f9;
        flex-shrink: 0;
        background: #fff
    }

    .sc-footer-row {
        display: flex;
        align-items: center;
        gap: 8px
    }

    .sc-total {
        flex: 1
    }

    .sc-total-lbl {
        font-size: 9px;
        color: #94a3b8;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px
    }

    .sc-total-cnt {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1
    }

    .sc-total-cnt em {
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
        font-style: normal
    }

    .sc-fbtn-row {
        display: flex;
        gap: 7px
    }

    .sc-proc-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 11px 20px;
        border: none;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        cursor: pointer;
        background: linear-gradient(135deg, #047857, #10b981);
        transition: .18s;
        white-space: nowrap;
    }

    .sc-proc-btn:hover {
        filter: brightness(1.07);
        transform: translateY(-1px)
    }

    .sc-proc-btn:active {
        transform: none
    }

    .sc-proc-btn:disabled {
        opacity: .4;
        cursor: not-allowed;
        transform: none;
        filter: none
    }

    .sc-clr-btn2 {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        border: 1.5px solid #e2e8f0;
        background: #f8fafc;
        color: #94a3b8;
        font-size: 15px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: .15s;
        flex-shrink: 0;
    }

    .sc-clr-btn2:hover {
        border-color: #fca5a5;
        color: #dc2626;
        background: #fef2f2
    }

    /* ─── MODALS ─── */
    .sc-mbg {
        position: fixed;
        inset: 0;
        z-index: 999;
        background: rgba(0, 0, 0, .48);
        display: flex;
        align-items: flex-end;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity .2s;
    }

    .sc-mbg.show {
        opacity: 1;
        pointer-events: auto
    }

    .sc-modal {
        background: #fff;
        border-radius: 22px 22px 0 0;
        width: 100%;
        max-width: 520px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        transform: translateY(100%);
        transition: transform .28s cubic-bezier(.34, 1.3, .64, 1);
        overflow: hidden;
    }

    .sc-mbg.show .sc-modal {
        transform: translateY(0)
    }

    .sc-mhdr {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 16px 20px;
        flex-shrink: 0
    }

    .sc-mhdr h3 {
        margin: 0;
        flex: 1;
        font-size: 15px;
        font-weight: 700
    }

    .sc-mclose {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        border: none;
        background: #f1f5f9;
        color: #64748b;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center
    }

    .sc-mclose:hover {
        background: #fef2f2;
        color: #dc2626
    }

    .sc-mbody {
        flex: 1;
        overflow-y: auto;
        padding: 16px 20px
    }

    .sc-mfoot {
        padding: 12px 20px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        gap: 8px;
        flex-shrink: 0
    }

    .sc-mfoot .ci-btn {
        flex: 1;
        justify-content: center
    }

    /* action cards */
    .sc-act-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 14px
    }

    .sc-acard {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 14px 8px;
        border-radius: 13px;
        border: 2px solid #e2e8f0;
        background: #fafafa;
        cursor: pointer;
        transition: .15s;
        font-size: 12px;
        font-weight: 700;
        color: #475569
    }

    .sc-acard:hover,
    .sc-acard.sel {
        border-color: var(--g);
        background: #f0fdf8;
        color: var(--gd)
    }

    .sc-acard.sel {
        box-shadow: 0 2px 10px var(--gg)
    }

    .sc-aico {
        width: 44px;
        height: 44px;
        border-radius: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px
    }

    .ai-use {
        background: #f3e8ff;
        color: #7c3aed
    }

    .ai-borrow {
        background: #fff7ed;
        color: #ea580c
    }

    .ai-return {
        background: #dcfce7;
        color: #059669
    }

    .ai-transfer {
        background: #dbeafe;
        color: #2563eb
    }

    /* form fields */
    .sc-fg {
        margin-bottom: 12px
    }

    .sc-fg label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .3px
    }

    .sc-fg input,
    .sc-fg textarea {
        width: 100%;
        padding: 9px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 9px;
        font-size: 13px;
        background: #fafafa;
        color: #1e293b;
        outline: none;
        transition: .15s;
        font-family: inherit;
        resize: vertical
    }

    .sc-fg input:focus,
    .sc-fg textarea:focus {
        border-color: var(--g);
        background: #fff
    }

    .sc-fg textarea {
        min-height: 64px
    }

    /* review list */
    .sc-rev-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #f8fafc
    }

    .sc-rev-item:last-child {
        border-bottom: none
    }

    .sc-rev-name {
        flex: 1;
        font-size: 12px;
        font-weight: 700;
        color: #1e293b;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .sc-rev-qty {
        font-size: 12px;
        font-weight: 700;
        color: var(--gd);
        white-space: nowrap
    }

    /* toasts */
    .sc-toasts {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 7px;
        pointer-events: none
    }

    .sc-toast {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 30px;
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .18);
        animation: tIn .25s ease;
        white-space: nowrap;
        max-width: 92vw
    }

    @keyframes tIn {
        from {
            opacity: 0;
            transform: translateY(10px)
        }

        to {
            opacity: 1;
            transform: none
        }
    }

    .t-ok {
        background: linear-gradient(135deg, #047857, #10b981)
    }

    .t-err {
        background: linear-gradient(135deg, #dc2626, #f87171)
    }

    .t-info {
        background: linear-gradient(135deg, #1e40af, #60a5fa)
    }
</style>

<body>
    <?php Layout::sidebar('qr-scanner');
    Layout::beginContent(); ?>

    <!-- ══ PAGE ══ -->
    <div class="sc" id="scPage">

        <!-- CAMERA ZONE -->
        <div class="sc-cam" id="camZone">

            <!-- Top controls -->
            <div class="sc-topbar">
                <button class="sc-tb" id="camTogBtn" onclick="toggleCam()">
                    <i class="fas fa-camera" id="camIco"></i>
                </button>
                <div class="sc-badge" id="scBadge">
                    <i class="fas fa-layer-group"></i>
                    <span id="badgeCnt">0</span> <?php echo $TH ? 'รายการ' : 'items' ?>
                </div>
                <div class="sc-tb-row">
                    <button class="sc-tb" id="swBtn" onclick="switchCam()" style="display:none"
                        title="<?php echo $TH ? 'สลับกล้อง' : 'Switch camera' ?>">
                        <i class="fas fa-sync-alt" id="swIco"></i>
                    </button>
                    <button class="sc-tb" id="torchBtn" onclick="toggleTorch()" style="display:none"><i
                            class="fas fa-bolt"></i></button>
                </div>
            </div>

            <!-- QR reader -->
            <div id="qrBox"></div>

            <div class="sc-vig"></div>

            <!-- Scan frame with laser -->
            <div class="sc-frame" id="scFrame">
                <!-- corner L-brackets -->
                <div class="sc-c tl"></div>
                <div class="sc-c tr"></div>
                <div class="sc-c bl"></div>
                <div class="sc-c br"></div>
                <!-- side laser-range track lines -->
                <div class="sc-track l"></div>
                <div class="sc-track r"></div>
                <!-- top/bottom edge lines -->
                <div class="sc-edge top"></div>
                <div class="sc-edge bot"></div>
                <!-- subtle barcode guide dashes -->
                <div class="sc-guide"><div class="sc-guide-bar"></div></div>
                <!-- laser beam -->
                <div class="sc-laser" id="scLaser"></div>
                <!-- detect ripple rings -->
                <div class="sc-ring"  id="scRing"></div>
                <div class="sc-ring2" id="scRing2"></div>
                <!-- label -->
                <div class="sc-label" id="scLabel"><?php echo $TH ? 'วางบาร์โค้ดในกรอบ' : 'Align barcode in frame' ?></div>
            </div>

            <!-- Flash -->
            <div class="sc-flash" id="scFlash"></div>

            <!-- No camera -->
            <div class="sc-nocam" id="noCam">
                <i class="fas fa-camera-slash"></i>
                <p><?php echo $TH ? 'กรุณาอนุญาตการเข้าถึงกล้อง\nหรือกดปุ่มเพื่อลองอีกครั้ง' : 'Please allow camera access\nor tap below to retry' ?>
                </p>
                <button class="sc-open-btn" onclick="startCam()">
                    <i class="fas fa-camera"></i> <?php echo $TH ? 'เปิดกล้อง' : 'Open Camera' ?>
                </button>
            </div>

            <!-- Hint -->
            <div class="sc-hint" id="scHint">
                <div class="dot"></div>
                <span><?php echo $TH ? 'กำลังสแกน — ส่งขวดถัดไปได้เลย' : 'Scanning continuously — present next bottle' ?></span>
            </div>
        </div>

        <!-- CONTROL PANEL / SHEET -->
        <div class="sc-sheet">
            <div class="sc-handle">
                <div class="sc-handle-bar"></div>
            </div>

            <!-- Manual input -->
            <div class="sc-inp-row">
                <div class="sc-inp-wrap">
                    <i class="sc-inp-ico fas fa-barcode"></i>
                    <input type="text" id="barcodeInput" class="sc-inp"
                        placeholder="<?php echo $TH ? 'พิมพ์รหัสขวด แล้วกด Enter' : 'Type bottle code then Enter' ?>"
                        autocomplete="off" autocorrect="off" spellcheck="false">
                </div>
                <button class="sc-gobtn" id="goBtn" onclick="manualScan()">
                    <i class="fas fa-plus"></i>
                </button>
            </div>

            <!-- Section header -->
            <div class="sc-sec-hdr">
                <h5><?php echo $TH ? 'รายการที่สแกน' : 'Scanned Items' ?></h5>
                <span class="sc-cnt-lbl" id="cntLbl"></span>
                <button class="sc-clr-btn" onclick="clearAll()"><?php echo $TH ? 'ล้างทั้งหมด' : 'Clear All' ?></button>
            </div>

            <!-- Cart -->
            <div class="sc-cart" id="cartEl">
                <div class="sc-empty" id="emptyEl">
                    <i class="fas fa-qrcode"></i>
                    <p><?php echo $TH ? "ยังไม่มีรายการ\nสแกน Barcode หรือ QR Code\nบนขวดสารเคมีเพื่อเพิ่มรายการ" : "No items yet\nScan barcodes on chemical\nbottles to add them here" ?>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="sc-footer" id="scFooter">
                <div class="sc-footer-row">
                    <div class="sc-total">
                        <div class="sc-total-lbl"><?php echo $TH ? 'รวมทั้งหมด' : 'Total' ?></div>
                        <div class="sc-total-cnt">
                            <span id="cartCnt">0</span>
                            <em> <?php echo $TH ? 'ขวด' : 'bottles' ?></em>
                        </div>
                    </div>
                    <div class="sc-fbtn-row">
                        <button class="sc-clr-btn2" onclick="clearAll()"
                            title="<?php echo $TH ? 'ล้างทั้งหมด' : 'Clear all' ?>">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <button class="sc-proc-btn" id="procBtn" onclick="openProcess()" disabled>
                            <?php echo $TH ? 'ดำเนินการ' : 'Process' ?> <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sc-toasts" id="toastEl"></div>

    <!-- ══ PROCESS MODAL ══ -->
    <div class="sc-mbg" id="modProc">
        <div class="sc-modal">
            <div class="sc-mhdr" style="border-bottom:1px solid #f1f5f9">
                <i class="fas fa-tasks" style="color:var(--g);font-size:16px"></i>
                <h3><?php echo $TH ? 'เลือกการดำเนินการ' : 'Select Action' ?></h3>
                <button class="sc-mclose" onclick="closeM('modProc')">&times;</button>
            </div>
            <div class="sc-mbody">
                <p style="font-size:12px;color:#64748b;margin:0 0 12px">
                    <?php echo $TH ? 'เลือกการดำเนินการสำหรับรายการที่สแกนทั้งหมด:' : 'Choose action for all scanned items:' ?>
                </p>
                <div class="sc-act-grid">
                    <div class="sc-acard ac-use" onclick="selAct('use')">
                        <div class="sc-aico ai-use"><i class="fas fa-eye-dropper"></i></div>
                        <span><?php echo $TH ? 'เบิกใช้' : 'Use' ?></span>
                    </div>
                    <div class="sc-acard ac-borrow" onclick="selAct('borrow')">
                        <div class="sc-aico ai-borrow"><i class="fas fa-hand-holding-medical"></i></div>
                        <span><?php echo $TH ? 'ยืม' : 'Borrow' ?></span>
                    </div>
                    <div class="sc-acard ac-return" onclick="selAct('return')">
                        <div class="sc-aico ai-return"><i class="fas fa-undo"></i></div>
                        <span><?php echo $TH ? 'คืน' : 'Return' ?></span>
                    </div>
                    <?php if ($isManager): ?>
                        <div class="sc-acard ac-transfer" onclick="selAct('transfer')">
                            <div class="sc-aico ai-transfer"><i class="fas fa-people-arrows"></i></div>
                            <span><?php echo $TH ? 'โอน' : 'Transfer' ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div
                    style="background:#f8fafc;border-radius:12px;padding:12px;max-height:160px;overflow-y:auto;margin-bottom:12px">
                    <p
                        style="font-size:10px;font-weight:700;color:#94a3b8;margin:0 0 8px;text-transform:uppercase;letter-spacing:.4px">
                        <?php echo $TH ? 'รายการที่จะดำเนินการ' : 'Items to process' ?></p>
                    <div id="revList"></div>
                </div>
                <div id="extraForm" style="display:none">
                    <div class="sc-fg" id="fgRetDate" style="display:none">
                        <label><?php echo $TH ? 'วันที่กำหนดคืน' : 'Return By' ?></label>
                        <input type="date" id="retDateV">
                    </div>
                    <div class="sc-fg" id="fgPurpose" style="display:none">
                        <label><?php echo $TH ? 'วัตถุประสงค์' : 'Purpose' ?></label>
                        <textarea id="purposeV" placeholder="<?php echo $TH ? 'ตัวเลือก...' : 'Optional...' ?>"></textarea>
                    </div>
                    <div class="sc-fg">
                        <label><?php echo $TH ? 'หมายเหตุ' : 'Notes' ?></label>
                        <textarea id="notesV" placeholder="<?php echo $TH ? 'ตัวเลือก...' : 'Optional...' ?>"></textarea>
                    </div>
                </div>
            </div>
            <div class="sc-mfoot">
                <button onclick="closeM('modProc')"
                    class="ci-btn ci-btn-secondary"><?php echo $TH ? 'ยกเลิก' : 'Cancel' ?></button>
                <button onclick="submitProc()" class="ci-btn ci-btn-primary" id="procSubmit" disabled>
                    <i class="fas fa-check"></i> <?php echo $TH ? 'ยืนยัน' : 'Confirm' ?>
                </button>
            </div>
        </div>
    </div>

    <?php Layout::endContent(); ?>
    <script>
        const TH = <?php echo json_encode($TH); ?>;
        const IS_MANAGER = <?php echo json_encode($isManager); ?>;
        const API = '/v1/api/borrow.php';
        const isMobile = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);

        let qr = null, camOn = false, cameras = [], torchOn = false;
        let activeFacing = 'environment'; // 'environment' | 'user'
        let cart = []; // {barcode,chemName,cas,unit,qty,remaining,sourceType,sourceId,relation,activeBorrow}
        let selAction = null;
        let lastScanTs = 0;
        const COOLDOWN = 1800;

        // ─── CAMERA ───
        function showHttpsWarn() {
            // Remove any existing warn
            document.querySelector('.sc-https-warn')?.remove();
            const host = location.hostname;
            const div = document.createElement('div');
            div.className = 'sc-https-warn';
            div.innerHTML = `
        <i class="fas fa-lock-open"></i>
        <h4>${TH ? 'ต้องการ HTTPS' : 'HTTPS Required'}</h4>
        <p>${TH ? 'กล้องบนอุปกรณ์มือถือต้องการการเชื่อมต่อ HTTPS กรุณาเข้าใช้งานผ่าน:' : 'Mobile cameras require HTTPS. Please access via:'}</p>
        <div class="sc-https-code">https://${host}/v1/pages/qr-scanner.php</div>
        <p style="font-size:11px;color:#64748b">${TH ? 'หรือใช้ช่อง Manual Input ด้านล่างแทน' : 'Or use the manual input field below'}</p>`;
            g('camZone').appendChild(div);
        }

        // ── Strip library-injected overlays ──
        function stripLibraryOverlays() {
            document.querySelectorAll('.qr-shaded-region').forEach(el => el.remove());
            document.querySelectorAll('#qrBox > div > div[style]').forEach(el => {
                if (el.style.border || el.style.background || el.style.backgroundColor) {
                    el.style.cssText = 'position:absolute!important;inset:0!important;width:100%!important;height:100%!important;border:none!important;background:none!important;box-shadow:none!important;';
                }
            });
        }

        // ── Show the scan frame (fix: must use 'block', not '' which reverts to CSS display:none) ──
        function showFrame() {
            const f = g('scFrame');
            f.style.display = 'block';
            f.classList.add('act');
            g('scLaser').classList.add('on');
        }

        function hideFrame() {
            const f = g('scFrame');
            f.style.display = 'none';
            f.classList.remove('act', 'success', 'detect', 'settle');
            g('scLaser').classList.remove('on');
            g('scRing').classList.remove('fire');
            g('scRing2').classList.remove('fire');
        }

        // ── Enumerate cameras once and cache ──
        async function getCameraList() {
            if (cameras.length) return cameras;
            try { cameras = await Html5Qrcode.getCameras(); } catch (e) { cameras = []; }
            return cameras;
        }

        async function startCam(facing) {
            if (facing !== undefined) activeFacing = facing;

            g('noCam').style.display = 'none';
            document.querySelector('.sc-https-warn')?.remove();

            // HTTPS check (mobile only, not localhost)
            if (isMobile
                && location.protocol !== 'https:'
                && location.hostname !== 'localhost'
                && location.hostname !== '127.0.0.1') {
                showHttpsWarn();
                return;
            }

            // Stop any existing session
            if (qr && camOn) { try { await qr.stop(); } catch (e) {} camOn = false; }
            if (!qr) qr = new Html5Qrcode('qrBox');

            // qrbox callback — sets our custom frame dimensions too
            const qrboxFn = (w, h) => {
                const size = Math.min(Math.round(Math.min(w, h) * .76), 268);
                const f = g('scFrame');
                if (f) { f.style.width = size + 'px'; f.style.height = size + 'px'; }
                return { width: size, height: size };
            };

            const formats = [
                Html5QrcodeSupportedFormats.QR_CODE,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.CODE_93,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.ITF,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.DATA_MATRIX,
            ];

            const cfg = {
                fps: 20,
                qrbox: qrboxFn,
                rememberLastUsedCamera: false,
                formatsToSupport: formats,
                experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                videoConstraints: {
                    facingMode: activeFacing,
                    width:  { min: 480, ideal: 1280, max: 1920 },
                    height: { min: 360, ideal: 720,  max: 1080 },
                }
            };

            let started = false;
            const errors = [];

            // Strategy 1 — exact facingMode (ideal for mobile)
            try {
                await qr.start({ facingMode: { exact: activeFacing } }, cfg, onScan, () => {});
                started = true;
            } catch (e) { errors.push('exact:' + e.message); }

            // Strategy 2 — non-exact facingMode
            if (!started) {
                try {
                    await qr.start({ facingMode: activeFacing }, cfg, onScan, () => {});
                    started = true;
                } catch (e) { errors.push('soft:' + e.message); }
            }

            // Strategy 3 — enumerate and pick by label
            if (!started) {
                try {
                    const list = await getCameraList();
                    let cam;
                    if (activeFacing === 'environment') {
                        cam = list.find(c => /(back|rear|environment|post)/i.test(c.label))
                            || list[list.length - 1] || list[0];
                    } else {
                        cam = list.find(c => /(front|user|selfie|face)/i.test(c.label))
                            || list[0];
                    }
                    if (!cam) throw new Error('No cameras');
                    await qr.start(cam.id, cfg, onScan, () => {});
                    started = true;
                } catch (e) { errors.push('id:' + e.message); }
            }

            if (!started) {
                console.error('Camera failed:', errors);
                g('noCam').style.display = '';
                const denied = errors.some(e => /permission|denied|notallowed/i.test(e));
                g('noCam').querySelector('p').textContent = denied
                    ? (TH ? 'ถูกปฏิเสธการเข้าถึงกล้อง\nไปที่การตั้งค่าเบราว์เซอร์แล้วอนุญาตการเข้าถึงกล้อง'
                          : 'Camera permission denied\nGo to browser settings and allow camera access')
                    : (TH ? 'ไม่พบกล้องหรือไม่รองรับ\nลองใช้ Manual Input แทน'
                          : 'Camera not found or not supported\nTry Manual Input instead');
                return;
            }

            camOn = true;

            // Remove library overlays immediately and again after 400ms
            stripLibraryOverlays();
            setTimeout(stripLibraryOverlays, 400);

            // Show our custom frame
            showFrame();
            g('scHint').style.display = 'flex';
            g('camTogBtn').classList.add('on');
            g('camIco').className = 'fas fa-video-slash';

            // Enumerate cameras to decide whether to show switch button
            const list = await getCameraList();
            if (list.length > 1) g('swBtn').style.display = '';
            // Torch only on mobile
            if (isMobile) g('torchBtn').style.display = '';

            // Icon hint: show which cam is active
            g('swBtn').title = activeFacing === 'environment'
                ? (TH ? 'สลับเป็นกล้องหน้า' : 'Switch to front cam')
                : (TH ? 'สลับเป็นกล้องหลัง' : 'Switch to rear cam');
        }

        function stopCam() {
            if (qr && camOn) qr.stop().catch(() => {});
            camOn = false;
            hideFrame();
            g('scHint').style.display = 'none';
            g('camTogBtn').classList.remove('on');
            g('camIco').className = 'fas fa-camera';
            g('swBtn').style.display = 'none';
            g('torchBtn').style.display = 'none';
            torchOn = false;
            g('noCam').style.display = '';
            g('noCam').querySelector('p').textContent =
                TH ? 'กรุณาอนุญาตการเข้าถึงกล้อง\nหรือกดปุ่มเพื่อลองอีกครั้ง'
                   : 'Please allow camera access\nor tap below to retry';
        }

        function toggleCam() { camOn ? stopCam() : startCam(); }

        // Toggle between rear ↔ front camera
        async function switchCam() {
            if (!camOn) return;
            const next = activeFacing === 'environment' ? 'user' : 'environment';
            // Spin icon for feedback
            const ico = g('swIco');
            ico.style.transition = 'transform .4s';
            ico.style.transform = 'rotate(180deg)';
            g('swBtn').disabled = true;
            await startCam(next);
            setTimeout(() => {
                ico.style.transform = '';
                g('swBtn').disabled = false;
            }, 420);
        }

        async function toggleTorch() {
            try {
                await qr.applyVideoConstraints({ advanced: [{ torch: !torchOn }] });
                torchOn = !torchOn;
                g('torchBtn').classList.toggle('on', torchOn);
            } catch (e) { }
        }

        // Detect if scanned code is a URL
        function isUrl(s) {
            try { const u = new URL(s); return u.protocol === 'http:' || u.protocol === 'https:'; }
            catch { return false; }
        }

        // Continuous scan — DON'T stop camera
        async function onScan(code) {
            const now = Date.now();

            // Cooldown guard (duplicate within COOLDOWN ms)
            if (code === (onScan._last || '') && now - lastScanTs < COOLDOWN) {
                if (!isUrl(code)) {
                    const el = document.querySelector(`[data-bc="${CSS.escape(code)}"]`);
                    if (el) { el.classList.remove('dup-flash'); void el.offsetWidth; el.classList.add('dup-flash'); }
                    toast(TH ? 'สแกนแล้ว — ปรับจำนวนได้ในช่องด้านล่าง' : 'Already scanned — adjust qty below', 'info');
                }
                return;
            }
            onScan._last = code;
            lastScanTs = now;

            if (navigator.vibrate) navigator.vibrate([40, 30, 80]);

            // ── Detect animation ──
            const frame = g('scFrame');
            const fl    = g('scFlash');
            const ring  = g('scRing');
            const ring2 = g('scRing2');

            frame.classList.add('detect');
            fl.classList.add('on');
            ring.classList.add('fire');
            ring2.classList.add('fire');

            setTimeout(() => {
                frame.classList.remove('detect');
                frame.classList.add('settle');
                fl.classList.remove('on');
            }, 260);

            setTimeout(() => {
                frame.classList.remove('settle');
                ring.classList.remove('fire');
                ring2.classList.remove('fire');
            }, 700);

            // ── If URL → navigate immediately ──
            if (isUrl(code)) {
                location.href = code;
                return;
            }

            await addToCart(code);
        }

        // ─── CART ───
        async function addToCart(barcode) {
            g('goBtn').disabled = true;
            try {
                const d = await api(API + '?action=scan_barcode&barcode=' + encodeURIComponent(barcode));
                if (!d.success) throw new Error(d.error || 'Not found');
                const item = d.data.item, rel = d.data.relation, ab = d.data.active_borrow;

                const already = cart.find(c => c.barcode === barcode);
                if (already) {
                    already.qty = Math.min(already.qty + 1, already.remaining || 999);
                    renderCart();
                    toast(`+1 ${item.chemical_name}`, 'ok');
                    return;
                }

                cart.push({
                    barcode,
                    chemName: item.chemical_name || '–',
                    cas: item.cas_number || '',
                    unit: item.unit || '',
                    qty: 1,
                    remaining: parseFloat(item.remaining_qty) || 0,
                    sourceType: item.source_type,
                    sourceId: item.source_id,
                    relation: rel,
                    activeBorrow: ab || null,
                });
                renderCart();
                toast('✓ ' + item.chemical_name, 'ok');
            } catch (e) {
                toast((TH ? 'ไม่พบ: ' : 'Not found: ') + barcode, 'err');
            } finally {
                g('goBtn').disabled = false;
            }
        }

        function manualScan() {
            const v = g('barcodeInput').value.trim();
            if (!v) return;
            g('barcodeInput').value = '';
            addToCart(v);
        }

        function removeFromCart(bc) { cart = cart.filter(c => c.barcode !== bc); renderCart(); }

        function updateQty(bc, val) {
            const it = cart.find(c => c.barcode === bc);
            if (!it) return;
            it.qty = Math.max(0.01, parseFloat(val) || 0.01);
            // re-render warn state without full re-render
            const el = document.querySelector(`[data-bc="${CSS.escape(bc)}"]`);
            if (el) el.classList.toggle('warn', it.qty > it.remaining && it.remaining > 0);
        }

        function adjustQty(bc, delta) {
            const it = cart.find(c => c.barcode === bc);
            if (!it) return;
            const step = 1;
            it.qty = Math.max(0.01, parseFloat((it.qty + delta * step).toFixed(3)));
            const inp = document.querySelector(`[data-bc="${CSS.escape(bc)}"] .sc-qinp`);
            if (inp) inp.value = it.qty;
            const el = document.querySelector(`[data-bc="${CSS.escape(bc)}"]`);
            if (el) el.classList.toggle('warn', it.qty > it.remaining && it.remaining > 0);
        }

        function clearAll() { if (!cart.length) return; cart = []; renderCart(); }

        function renderCart() {
            const cnt = cart.length;
            g('cartCnt').textContent = cnt;
            g('cntLbl').textContent = cnt ? cnt + ' ' + (TH ? 'รายการ' : 'items') : '';
            if (cnt) { g('scBadge').style.display = ''; g('badgeCnt').textContent = cnt; }
            else g('scBadge').style.display = 'none';
            g('procBtn').disabled = !cnt;

            // Remove only .sc-card children (keep emptyEl intact in DOM)
            const cartEl = g('cartEl');
            Array.from(cartEl.querySelectorAll('.sc-card')).forEach(el => el.remove());
            g('emptyEl').style.display = cnt ? 'none' : '';

            if (!cnt) return;

            const rLbl = { owner: TH ? 'เจ้าของ' : 'Owner', borrower: TH ? 'กำลังยืม' : 'Borrowing', other: TH ? 'ของผู้อื่น' : 'Other' };

            cart.forEach((c, i) => {
                const rem = parseFloat(c.remaining) || 0;
                const over = c.qty > rem && rem > 0;
                const pct = rem > 0 ? Math.min(100, Math.round((rem / rem) * 100)) : 100; // show as full since we don't have initial
                // simpler: show stock bar based on remaining vs a reasonable max
                const barFill = rem > 0 ? Math.min(100, Math.round((rem / Math.max(rem, c.qty)) * 100)) : 0;
                const barCls = barFill > 50 ? '' : barFill > 20 ? 'low' : 'vl';

                const aCls = c.relation === 'owner' ? 'av-owner' : c.relation === 'borrower' ? 'av-borrow' : 'av-other';
                const aIco = c.relation === 'owner' ? 'fa-crown' : c.relation === 'borrower' ? 'fa-undo' : 'fa-flask';
                const tCls = c.relation === 'owner' ? 'owner' : c.relation === 'borrower' ? 'borrower' : 'other';
                const bc = c.barcode.replace(/\\/g, '\\\\').replace(/'/g, "\\'");

                const card = document.createElement('div');
                card.className = 'sc-card' + (over ? ' warn' : '');
                card.dataset.bc = c.barcode;
                card.innerHTML = `
<span class="sc-seq">#${i + 1}</span>
<div class="sc-card-top">
  <div class="sc-avi ${aCls}"><i class="fas ${aIco}"></i></div>
  <div class="sc-meta">
    <div class="sc-name" title="${x(c.chemName)}">${x(c.chemName)}</div>
    <div class="sc-sub">
      ${c.cas ? `<span class="sc-tag">${x(c.cas)}</span>` : ''}
      <span class="sc-tag ${tCls}">${rLbl[c.relation] || '–'}</span>
      <span style="font-family:'Courier New',monospace;font-size:9px;color:#cbd5e1">${x(c.barcode)}</span>
    </div>
  </div>
  <button class="sc-del" onclick="removeFromCart('${bc}')"><i class="fas fa-times"></i></button>
</div>
<div class="sc-card-bot">
  <div class="sc-stock">
    <div class="sc-slbl">${TH ? 'คงเหลือในขวด' : 'Remaining Stock'}</div>
    <div class="sc-sval">${n(rem)}<span>${x(c.unit)}</span></div>
    <div class="sc-sbar"><div class="sc-sfill ${barCls}" style="width:${barFill}%"></div></div>
  </div>
  <div class="sc-qty">
    <div class="sc-qlbl">${TH ? 'ปริมาณที่ต้องการ' : 'Quantity'}</div>
    <div class="sc-qctrl">
      <button class="sc-qbtn" onclick="adjustQty('${bc}',-1)">−</button>
      <input class="sc-qinp" type="number" min="0.01" step="any" value="${c.qty}"
             oninput="updateQty('${bc}',this.value)"
             onchange="updateQty('${bc}',this.value)">
      <button class="sc-qbtn" onclick="adjustQty('${bc}',1)">+</button>
    </div>
    <div class="sc-qunit">${x(c.unit)}</div>
  </div>
</div>
${over ? `<div class="sc-warn-msg"><i class="fas fa-exclamation-triangle"></i>${TH ? 'ปริมาณเกินจำนวนคงเหลือ' : 'Exceeds remaining stock'}</div>` : ''}`;
                g('cartEl').insertBefore(card, g('emptyEl'));
            });
        }

        // ─── PROCESS ───
        function openProcess() {
            if (!cart.length) return;
            selAction = null;
            document.querySelectorAll('.sc-acard').forEach(c => c.classList.remove('sel'));
            g('extraForm').style.display = 'none';
            g('procSubmit').disabled = true;
            g('revList').innerHTML = cart.map(c => `
      <div class="sc-rev-item">
        <div class="sc-rev-name">${x(c.chemName)}</div>
        <div class="sc-rev-qty">${n(c.qty)} ${x(c.unit)}</div>
      </div>`).join('');
            g('purposeV').value = ''; g('notesV').value = '';
            const d = new Date(); d.setDate(d.getDate() + 7);
            g('retDateV').value = d.toISOString().split('T')[0];
            openM('modProc');
        }

        function selAct(act) {
            selAction = act;
            document.querySelectorAll('.sc-acard').forEach(c => c.classList.remove('sel'));
            document.querySelector('.ac-' + act)?.classList.add('sel');
            g('extraForm').style.display = '';
            g('fgRetDate').style.display = act === 'borrow' ? '' : 'none';
            g('fgPurpose').style.display = (act === 'borrow' || act === 'use') ? '' : 'none';
            g('procSubmit').disabled = false;
        }

        async function submitProc() {
            if (!selAction || !cart.length) return;
            const btn = g('procSubmit');
            btn.disabled = true; const orig = btn.innerHTML;
            btn.innerHTML = spn();
            const retDate = g('retDateV').value;
            const purpose = g('purposeV').value.trim();
            const notes = g('notesV').value.trim();

            if (selAction === 'use' || selAction === 'transfer') {
                sessionStorage.setItem('scanBatch', JSON.stringify({ action: selAction, items: cart, notes, purpose, ts: Date.now() }));
                location.href = '/v1/pages/borrow.php?scan_action=' + selAction;
                return;
            }

            if (selAction === 'return') {
                let ok = 0, fail = 0;
                for (const c of cart) {
                    if (!c.activeBorrow) { fail++; continue; }
                    try {
                        const fd = new FormData();
                        fd.append('txn_id', c.activeBorrow.id);
                        fd.append('return_quantity', c.qty);
                        if (notes) fd.append('notes', notes);
                        const d = await api(API + '?action=return', { method: 'POST', body: fd });
                        if (d.success) ok++; else fail++;
                    } catch (e) { fail++; }
                }
                toast(`${TH ? 'คืนสำเร็จ' : 'Returned'} ${ok}/${cart.length} ${TH ? 'รายการ' : 'items'}`, fail ? 'err' : 'ok');
                if (ok > 0) { cart = []; renderCart(); }
                closeM('modProc'); btn.disabled = false; btn.innerHTML = orig; return;
            }

            if (selAction === 'borrow') {
                let ok = 0, fail = 0;
                for (const c of cart) {
                    try {
                        const fd = new FormData();
                        fd.append('source_type', c.sourceType);
                        fd.append('source_id', c.sourceId);
                        fd.append('quantity', c.qty);
                        fd.append('purpose', purpose);
                        if (retDate) fd.append('expected_return_date', retDate);
                        const d = await api(API + '?action=borrow', { method: 'POST', body: fd });
                        if (d.success) ok++; else fail++;
                    } catch (e) { fail++; }
                }
                toast(`${TH ? 'ส่งคำขอยืม' : 'Submitted'} ${ok}/${cart.length}`, fail ? 'err' : 'ok');
                if (ok > 0) { cart = []; renderCart(); }
                closeM('modProc'); btn.disabled = false; btn.innerHTML = orig; return;
            }

            btn.disabled = false; btn.innerHTML = orig;
        }

        // ─── MODAL ───
        function openM(id) { g(id).classList.add('show'); }
        function closeM(id) { g(id).classList.remove('show'); }

        // ─── UTILS ───
        const g = id => document.getElementById(id);
        const x = s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; };
        const n = v => Number(v || 0).toLocaleString();
        const spn = () => '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:tIn .6s linear infinite"></span>';

        function toast(msg, type = 'info') {
            const w = g('toastEl'), t = document.createElement('div');
            t.className = 'sc-toast ' + (type === 'ok' ? 't-ok' : type === 'err' ? 't-err' : 't-info');
            t.innerHTML = `<i class="fas fa-${type === 'ok' ? 'check-circle' : type === 'err' ? 'times-circle' : 'info-circle'}"></i> ${x(msg)}`;
            w.appendChild(t); setTimeout(() => t.remove(), 3200);
        }

        async function api(url, opts = {}) {
            const tk = document.cookie.split('; ').find(c => c.startsWith('auth_token='))?.split('=')[1];
            const h = { ...(opts.headers || {}) };
            if (!(opts.body instanceof FormData)) h['Content-Type'] = 'application/json';
            if (tk) h['Authorization'] = 'Bearer ' + tk;
            const r = await fetch(url, { ...opts, headers: h });
            if (r.status === 401) { location.href = '/v1/'; throw new Error('Unauthorized'); }
            return r.json();
        }

        // ─── INIT ───
        document.addEventListener('DOMContentLoaded', () => {
            g('barcodeInput').addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); manualScan(); }
            });
            document.querySelectorAll('.sc-mbg').forEach(bg =>
                bg.addEventListener('click', e => { if (e.target === bg) bg.classList.remove('show'); })
            );
            renderCart();
            // Auto-start camera
            startCam();
        });
    </script>
    <?php Layout::footer(); ?>