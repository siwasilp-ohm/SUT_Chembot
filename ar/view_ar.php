<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>AR View — SUT chemBot</title>
    
    <!-- Google Model Viewer for AR + 3D -->
    <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;overflow:hidden;background:#0a0a1a;color:#fff;height:100vh;width:100vw}

        /* ═══ Model Viewer ═══ */
        model-viewer{width:100%;height:100vh;background:linear-gradient(135deg,#0c0c1d 0%,#1a1a3e 50%,#0c0c1d 100%);--poster-color:transparent}
        model-viewer::part(default-ar-button){display:none}
        model-viewer::part(default-progress-bar){display:none}

        /* ═══ Embed Viewer ═══ */
        .embed-viewer{width:100%;height:100vh;position:relative}
        .embed-viewer iframe{width:100%;height:100%;border:none}

        /* ═══ Fallback 3D (CSS bottle) ═══ */
        .fallback-3d{width:100%;height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0c0c1d 0%,#1a1a3e 50%,#0c0c1d 100%);position:relative;overflow:hidden}
        .fallback-3d::before{content:'';position:absolute;width:200%;height:200%;background:radial-gradient(ellipse,rgba(99,102,241,.1) 0%,transparent 70%);animation:bgPulse 8s ease-in-out infinite}
        .fallback-3d::after{content:'';position:absolute;width:100%;height:100%;background:radial-gradient(circle at 50% 50%,transparent 30%,rgba(0,0,0,.3) 100%);pointer-events:none}
        @keyframes bgPulse{0%,100%{transform:scale(1);opacity:.5}50%{transform:scale(1.1);opacity:1}}

        .bottle-3d{width:120px;position:relative;animation:bottleFloat 4s ease-in-out infinite;filter:drop-shadow(0 20px 40px rgba(0,0,0,.4))}
        @keyframes bottleFloat{0%,100%{transform:translateY(0) rotate(-2deg)}50%{transform:translateY(-12px) rotate(2deg)}}
        .bottle-body{width:120px;height:200px;border:3px solid rgba(255,255,255,.25);border-radius:20px;position:relative;overflow:hidden;background:rgba(255,255,255,.03);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}
        .bottle-neck{width:40px;height:30px;border:3px solid rgba(255,255,255,.25);border-bottom:none;border-radius:8px 8px 0 0;margin:0 auto;background:rgba(255,255,255,.03)}
        .bottle-cap{width:48px;height:14px;background:rgba(255,255,255,.2);border-radius:6px 6px 0 0;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,.3)}
        .bottle-fluid{position:absolute;bottom:0;left:0;right:0;transition:height .8s cubic-bezier(.4,0,.2,1);border-radius:0 0 17px 17px}
        .bottle-fluid::before{content:'';position:absolute;top:0;left:0;right:0;height:8px;background:rgba(255,255,255,.15);border-radius:50%;animation:wave 3s ease-in-out infinite}
        .bottle-fluid::after{content:'';position:absolute;top:0;left:-50%;width:200%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.1),transparent);animation:bottleShine 4s ease-in-out infinite}
        @keyframes wave{0%,100%{transform:scaleX(.95)}50%{transform:scaleX(1.05)}}
        @keyframes bottleShine{0%{left:-50%}50%,100%{left:150%}}
        .bottle-label{position:absolute;top:30%;left:8px;right:8px;text-align:center;padding:8px 4px;background:rgba(0,0,0,.3);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);border-radius:6px;border:1px solid rgba(255,255,255,.1)}
        .bottle-label .bl-name{font-size:10px;font-weight:700;line-height:1.2;margin-bottom:2px;word-break:break-word}
        .bottle-label .bl-cas{font-size:8px;opacity:.6}
        .bottle-pct-label{text-align:center;margin-top:8px;font-size:24px;font-weight:900;text-shadow:0 2px 10px rgba(0,0,0,.5)}

        /* ═══ Top Header Bar - Premium Glassmorphism ═══ */
        .ar-header{position:fixed;top:0;left:0;right:0;z-index:200;padding:16px 20px;background:linear-gradient(to bottom,rgba(10,10,26,.98) 0%,rgba(10,10,26,.7) 40%,rgba(10,10,26,.3) 70%,transparent 100%);display:flex;align-items:center;justify-content:space-between;gap:12px;backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px)}
        .ar-header::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(99,102,241,.1) 0%,transparent 50%);pointer-events:none}
        .ar-header::after{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(99,102,241,.3),transparent)}
        .ar-header a,.ar-header button{width:44px;height:44px;border-radius:14px;background:rgba(255,255,255,.08);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;color:#fff;text-decoration:none;font-size:17px;cursor:pointer;transition:all .2s ease;outline:none}
        .ar-header a:focus,.ar-header button:focus{box-shadow:0 0 0 3px rgba(99,102,241,.4)}
        .ar-header a:hover,.ar-header button:hover{background:rgba(255,255,255,.16);transform:scale(1.05)}
        .ar-header a:active,.ar-header button:active{transform:scale(.95)}
        .ar-id-pill{background:rgba(0,0,0,.5);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:8px 16px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:8px;flex-shrink:1;min-width:0;overflow:hidden;letter-spacing:.3px;box-shadow:0 4px 16px rgba(0,0,0,.2)}
        .ar-id-pill i{color:#818cf8;flex-shrink:0}
        .ar-id-pill span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .ar-act-spatial{width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;padding:6px 10px;background:linear-gradient(135deg,#6366f1,#818cf8);border:1px solid rgba(99,102,241,.3);color:#fff;font-size:15px;cursor:pointer;transition:all .2s;box-shadow:0 4px 15px rgba(99,102,241,.3)}
        .ar-act-spatial:hover{box-shadow:0 6px 24px rgba(99,102,241,.45);transform:translateY(-2px)}
        .ar-act-spatial:active{transform:scale(.92)}
        .ar-act-spatial:focus{box-shadow:0 0 0 3px rgba(99,102,241,.4)}

        /* ═══ Hazard Strip (left) - Floating Cards ═══ */
        .ar-hazard-strip{position:fixed;top:80px;left:16px;z-index:200;display:flex;flex-direction:column;gap:8px}
        .ar-hz-diamond{width:48px;height:48px;position:relative;cursor:pointer;transition:all .25s cubic-bezier(.4,0,.2,1);filter:drop-shadow(0 4px 12px rgba(0,0,0,.3));outline:none}
        .ar-hz-diamond:focus{filter:drop-shadow(0 4px 12px rgba(0,0,0,.3));transform:scale(1.15)}
        .ar-hz-diamond:hover{transform:scale(1.15) translateX(4px)}
        .ar-hz-inner{position:absolute;inset:5px;transform:rotate(45deg);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:13px;border-width:2.5px;border-style:solid;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);transition:all .2s}
        .ar-hz-inner i{transform:rotate(-45deg)}
        .ar-hz-tip{position:absolute;left:calc(100% + 10px);top:50%;transform:translateY(-50%);background:rgba(10,10,26,.95);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);color:#fff;padding:6px 12px;border-radius:8px;font-size:11px;font-weight:600;white-space:nowrap;pointer-events:none;opacity:0;transition:all .25s cubic-bezier(.4,0,.2,1);box-shadow:0 4px 20px rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.1);z-index:10}
        .ar-hz-diamond:hover .ar-hz-tip{opacity:1;transform:translateY(-50%) translateX(4px)}
        .ar-hz-diamond:focus .ar-hz-tip{opacity:1;transform:translateY(-50%) translateX(4px)}

        /* Hazard color map */
        .hz-compressed_gas .ar-hz-inner{background:rgba(217,119,6,.2);border-color:#d97706;color:#fbbf24}
        .hz-flammable .ar-hz-inner{background:rgba(220,38,38,.2);border-color:#dc2626;color:#f87171}
        .hz-oxidizing .ar-hz-inner{background:rgba(217,119,6,.2);border-color:#d97706;color:#fbbf24}
        .hz-toxic .ar-hz-inner{background:rgba(220,38,38,.25);border-color:#dc2626;color:#fca5a5}
        .hz-corrosive .ar-hz-inner{background:rgba(124,58,237,.2);border-color:#7c3aed;color:#c4b5fd}
        .hz-irritant .ar-hz-inner{background:rgba(245,158,11,.2);border-color:#f59e0b;color:#fde68a}
        .hz-environmental .ar-hz-inner{background:rgba(22,163,74,.2);border-color:#16a34a;color:#86efac}
        .hz-health_hazard .ar-hz-inner{background:rgba(220,38,38,.2);border-color:#dc2626;color:#fca5a5}
        .hz-explosive .ar-hz-inner{background:rgba(234,88,12,.2);border-color:#ea580c;color:#fb923c}

        /* ═══ Fluid Level (right) - Animated Bar with Shimmer ═══ */
        .ar-fluid-col{position:fixed;top:80px;right:16px;z-index:200;text-align:center}
        .ar-fluid-bar{width:52px;height:150px;border:2px solid rgba(255,255,255,.25);border-radius:16px;position:relative;overflow:hidden;background:rgba(255,255,255,.04);backdrop-filter:blur(8px);box-shadow:0 8px 32px rgba(0,0,0,.2)}
        .ar-fluid-fill{position:absolute;bottom:0;left:0;right:0;border-radius:0 0 14px 14px;transition:height .8s cubic-bezier(.4,0,.2,1);box-shadow:0 -4px 20px rgba(255,255,255,.1)}
        .ar-fluid-fill::before{content:'';position:absolute;top:0;left:0;right:0;height:6px;background:linear-gradient(90deg,rgba(255,255,255,.3),rgba(255,255,255,.1),rgba(255,255,255,.3));border-radius:50%;animation:wave 3s ease-in-out infinite}
        .ar-fluid-fill::after{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);animation:shimmer 2.5s infinite}
        @keyframes wave{0%,100%{transform:scaleX(.9)}50%{transform:scaleX(1.1)}}
        @keyframes shimmer{0%{left:-100%}100%{left:100%}}
        .ar-fluid-pct{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:900;text-shadow:0 2px 8px rgba(0,0,0,.5);letter-spacing:.5px}
        .ar-fluid-label{font-size:10px;font-weight:600;opacity:.6;margin-top:8px;text-transform:uppercase;letter-spacing:1px}

        /* ═══ Signal Word (top center) - Glowing Badge ═══ */
        .ar-signal{position:fixed;top:72px;left:50%;transform:translateX(-50%);z-index:200;padding:8px 20px;border-radius:24px;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;display:none;align-items:center;gap:8px;backdrop-filter:blur(12px);box-shadow:0 8px 32px rgba(0,0,0,.3)}
        .ar-signal.danger{display:flex;background:rgba(220,38,38,.25);border:1.5px solid rgba(220,38,38,.5);color:#fca5a5;animation:signalPulse 2s infinite;box-shadow:0 0 30px rgba(220,38,38,.3)}
        .ar-signal.warning{display:flex;background:rgba(245,158,11,.25);border:1.5px solid rgba(245,158,11,.5);color:#fde68a;box-shadow:0 0 30px rgba(245,158,11,.3)}
        @keyframes signalPulse{0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.4)}50%{box-shadow:0 0 0 12px rgba(220,38,38,0)}}

        /* ═══ Bottom Sheet Card (mobile) / Side Panel (desktop) ═══ */
        .ar-card{position:fixed;bottom:0;left:0;right:0;z-index:200;background:linear-gradient(180deg,rgba(15,15,35,.98) 0%,rgba(10,10,26,.96) 100%);backdrop-filter:blur(28px);-webkit-backdrop-filter:blur(28px);border:1px solid rgba(255,255,255,.07);border-bottom:none;border-radius:28px 28px 0 0;transition:transform .45s cubic-bezier(.34,1.56,.64,1);padding:0;max-height:82vh;overflow-y:auto;box-shadow:0 -16px 60px rgba(0,0,0,.65),0 0 0 1px rgba(255,255,255,.04) inset}
        .ar-card::before{content:'';position:absolute;top:0;left:24px;right:24px;height:1px;background:linear-gradient(90deg,transparent,rgba(99,102,241,.5),transparent);pointer-events:none}
        .ar-card.minimized{transform:translateY(calc(100% - 76px))}

        /* ═══ Peek Handle Zone ═══ */
        .ar-peek{padding:12px 20px 6px;cursor:pointer;user-select:none;-webkit-user-select:none;flex-shrink:0;touch-action:none}
        .ar-peek-bar{width:48px;height:5px;border-radius:4px;background:linear-gradient(90deg,rgba(255,255,255,.12),rgba(255,255,255,.3),rgba(255,255,255,.12));margin:0 auto 10px;transition:all .25s}
        .ar-peek:hover .ar-peek-bar{width:64px;background:linear-gradient(90deg,rgba(255,255,255,.25),rgba(255,255,255,.5),rgba(255,255,255,.25))}
        .ar-peek-row{display:flex;align-items:center;gap:10px;min-height:36px}
        .ar-peek-icon{width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;opacity:0;transform:scale(.85);transition:opacity .3s,transform .3s}
        .ar-peek-text{flex:1;min-width:0;opacity:0;transform:translateY(5px);transition:opacity .3s,transform .3s}
        .ar-peek-name{font-size:13px;font-weight:700;color:rgba(255,255,255,.9);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2}
        .ar-peek-sub{font-size:10px;color:rgba(255,255,255,.35);margin-top:2px}
        .ar-peek-pct{font-size:18px;font-weight:900;flex-shrink:0;opacity:0;transform:scale(.85);transition:opacity .3s,transform .3s;letter-spacing:-0.5px}
        .ar-card.minimized .ar-peek-icon,.ar-card.minimized .ar-peek-text,.ar-card.minimized .ar-peek-pct{opacity:1;transform:none}
        .ar-card-head{padding:20px 28px 14px;display:flex;align-items:flex-start;gap:16px;position:relative}
        .ar-card-head .type-ic{width:52px;height:52px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;box-shadow:0 6px 20px rgba(0,0,0,.3),0 0 20px rgba(99,102,241,.15) inset;position:relative;overflow:hidden}
        .ar-card-head .type-ic::after{content:'';position:absolute;top:0;left:0;right:0;height:50%;background:linear-gradient(180deg,rgba(255,255,255,.15),transparent);pointer-events:none}
        .ar-card-head .info{flex:1;min-width:0;padding-top:2px}
        .ar-card-head .chem-name{font-size:20px;font-weight:800;line-height:1.25;margin-bottom:6px;background:linear-gradient(135deg,#fff 0%,rgba(255,255,255,.85) 50%,rgba(255,255,255,.7) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:-0.3px}
        .ar-card-head .chem-sub{font-size:13px;color:rgba(255,255,255,.45);display:flex;flex-wrap:wrap;gap:8px;align-items:center;line-height:1.4}
        .ar-card-head .chem-sub b{color:rgba(255,255,255,.8);font-weight:600}

        /* Tags - Enhanced Pill Style */
        .ar-card-tags{padding:0 28px;display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
        .ar-tag{font-size:11px;padding:6px 12px;border-radius:10px;font-weight:600;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);letter-spacing:.3px;transition:all .25s cubic-bezier(.4,0,.2,1);position:relative;overflow:hidden}
        .ar-tag::before{content:'';position:absolute;top:0;left:0;right:0;height:50%;background:linear-gradient(180deg,rgba(255,255,255,.08),transparent);pointer-events:none}
        .ar-tag:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.2)}
        .ar-tag-type{border-color:rgba(99,102,241,.35);color:#a5b4fc;background:rgba(99,102,241,.1)}
        .ar-tag-material{border-color:rgba(148,163,184,.35);color:#cbd5e1;background:rgba(148,163,184,.06)}
        .ar-tag-grade{border-color:rgba(34,197,94,.35);color:#4ade80;background:rgba(34,197,94,.08)}
        .ar-tag-danger{border-color:rgba(220,38,38,.45);background:rgba(220,38,38,.12);color:#fca5a5}
        .ar-tag-warning{border-color:rgba(245,158,11,.45);background:rgba(245,158,11,.12);color:#fde68a}

        /* Props grid - Enhanced Glass Style */
        .ar-props{display:grid;grid-template-columns:repeat(auto-fit,minmax(105px,1fr));gap:12px;padding:0 28px 20px}
        .ar-prop{background:linear-gradient(180deg,rgba(255,255,255,.06) 0%,rgba(255,255,255,.02) 100%);border:1px solid rgba(255,255,255,.05);border-radius:16px;padding:14px 10px;text-align:center;transition:all .3s cubic-bezier(.4,0,.2,1);position:relative;overflow:hidden}
        .ar-prop::before{content:'';position:absolute;top:0;left:0;right:0;height:50%;background:linear-gradient(180deg,rgba(255,255,255,.05),transparent);pointer-events:none;border-radius:16px 16px 0 0}
        .ar-prop:hover{background:linear-gradient(180deg,rgba(255,255,255,.1) 0%,rgba(255,255,255,.04) 100%);border-color:rgba(99,102,241,.2);transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.25)}
        .ar-prop .p-v{font-size:16px;font-weight:800;line-height:1.2;position:relative}
        .ar-prop .p-l{font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:1px;margin-top:6px;position:relative}

        /* Detail rows - Premium List Style */
        .ar-details{padding:0 28px 18px;display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .ar-detail{padding:10px 0;border-bottom:1px solid rgba(255,255,255,.04);transition:background .2s}
        .ar-detail:hover{background:rgba(255,255,255,.02)}
        .ar-detail .d-l{font-size:10px;color:rgba(255,255,255,.3);text-transform:uppercase;font-weight:700;letter-spacing:.8px;margin-bottom:4px;display:flex;align-items:center;gap:4px}
        .ar-detail .d-l i{font-size:8px;opacity:.7}
        .ar-detail .d-v{font-size:14px;font-weight:500;color:rgba(255,255,255,.9);margin-top:2px;line-height:1.3}
        .ar-detail-full{grid-column:1/-1}

        /* Expiry banner - Enhanced Status Card */
        .ar-expiry{margin:0 28px 18px;padding:12px 18px;border-radius:14px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:12px;box-shadow:0 6px 20px rgba(0,0,0,.2);position:relative;overflow:hidden}
        .ar-expiry::before{content:'';position:absolute;top:0;left:0;right:0;height:50%;background:linear-gradient(180deg,rgba(255,255,255,.08),transparent);pointer-events:none}
        .ar-expiry.ok{background:linear-gradient(135deg,rgba(34,197,94,.2) 0%,rgba(34,197,94,.12) 100%);border:1px solid rgba(34,197,94,.35);color:#4ade80}
        .ar-expiry.warn{background:linear-gradient(135deg,rgba(245,158,11,.2) 0%,rgba(245,158,11,.12) 100%);border:1px solid rgba(245,158,11,.35);color:#fde68a}
        .ar-expiry.expired{background:linear-gradient(135deg,rgba(220,38,38,.2) 0%,rgba(220,38,38,.12) 100%);border:1px solid rgba(220,38,38,.35);color:#fca5a5}

        /* Action buttons - Premium Glass Buttons */
        .ar-actions{padding:16px 28px 28px;display:flex;gap:12px;position:relative}
        .ar-actions::before{content:'';position:absolute;top:0;left:28px;right:28px;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.06),transparent)}
        .ar-actions a,.ar-actions button{flex:1;padding:16px;border:none;border-radius:16px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;text-decoration:none;transition:all .3s cubic-bezier(.4,0,.2,1);letter-spacing:.4px;box-shadow:0 6px 24px rgba(0,0,0,.25);outline:none;position:relative;overflow:hidden}
        .ar-actions a::before,.ar-actions button::before{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.15) 0%,transparent 50%);pointer-events:none;opacity:0;transition:opacity .25s}
        .ar-actions a:hover::before,.ar-actions button:hover::before{opacity:1}
        .ar-actions a:focus,.ar-actions button:focus{box-shadow:0 0 0 3px rgba(99,102,241,.5),0 6px 24px rgba(0,0,0,.25)}
        .ar-act-primary{background:linear-gradient(135deg,#6366f1 0%,#818cf8 100%);color:#fff}
        .ar-act-primary:hover{background:linear-gradient(135deg,#4f46e5 0%,#6366f1 100%);transform:translateY(-3px);box-shadow:0 12px 36px rgba(99,102,241,.45)}
        .ar-act-primary:active{transform:translateY(0)}
        .ar-act-secondary{background:linear-gradient(180deg,rgba(255,255,255,.1) 0%,rgba(255,255,255,.05) 100%);border:1px solid rgba(255,255,255,.15)!important;color:#fff}
        .ar-act-secondary:hover{background:linear-gradient(180deg,rgba(255,255,255,.15) 0%,rgba(255,255,255,.08) 100%);transform:translateY(-3px);box-shadow:0 12px 36px rgba(0,0,0,.3)}
        .ar-act-secondary:active{transform:translateY(0)}
        .ar-act-ar{background:linear-gradient(135deg,#0d9488 0%,#14b8a6 100%);color:#fff}
        .ar-act-ar:hover{background:linear-gradient(135deg,#0f766e 0%,#0d9488 100%);transform:translateY(-3px);box-shadow:0 12px 36px rgba(13,148,136,.45)}
        .ar-act-ar:active{transform:translateY(0)}

        /* ═══ AR Mode Button - Premium Floating Action ═══ */
        .ar-launch{position:fixed;bottom:28px;right:28px;z-index:300;width:64px;height:64px;border-radius:20px;background:linear-gradient(135deg,#0d9488 0%,#14b8a6 100%);color:#fff;border:none;font-size:26px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 40px rgba(13,148,136,.5),0 0 0 1px rgba(255,255,255,.1) inset;transition:all .3s cubic-bezier(.4,0,.2,1);overflow:hidden}
        .ar-launch::before{content:'';position:absolute;top:0;left:0;right:0;height:50%;background:linear-gradient(180deg,rgba(255,255,255,.2),transparent);pointer-events:none}
        .ar-launch:hover{transform:scale(1.1);box-shadow:0 16px 50px rgba(13,148,136,.6),0 0 0 2px rgba(255,255,255,.15) inset}
        .ar-launch:active{transform:scale(.95)}
        .ar-card:not(.minimized) ~ .ar-launch{display:none}

        /* Make viewer occupy full screen more cleanly */
        #viewerArea{position:fixed;inset:0;z-index:10}
        #viewerArea model-viewer, #viewerArea iframe, #viewerArea .fallback-3d{height:100vh;height:100dvh}

        /* ═══ Responsive ═══ */
        @media(max-width:768px){
            .ar-header{padding:14px 16px}
            .ar-id-pill{padding:6px 12px;font-size:11px}
            .ar-card{border-radius:24px 24px 0 0}
            .ar-card-head{padding:14px 18px}
            .ar-card-head .chem-name{font-size:16px}
            .ar-card-head .type-ic{width:42px;height:42px;font-size:18px}
            .ar-props{grid-template-columns:repeat(3,1fr);gap:8px;padding:0 18px 14px}
            .ar-props .ar-prop{padding:10px 6px}
            .ar-props .ar-prop .p-v{font-size:14px}
            .ar-details{padding:0 18px 12px;gap:8px}
            .ar-card-tags{padding:0 18px;gap:5px}
            .ar-expiry{margin:0 18px 12px;padding:10px 14px;font-size:11px}
            .ar-actions{padding:10px 18px 20px;gap:8px}
            .ar-actions a,.ar-actions button{padding:12px;font-size:12px}
            .ar-hazard-strip{left:10px;top:90px}
            .ar-hz-diamond{width:42px;height:42px}
            .ar-fluid-col{right:10px;top:90px}
            .ar-fluid-bar{width:44px;height:120px}
            .ar-signal{top:82px;padding:6px 16px;font-size:11px}
        }
        @media(max-width:480px){
            .ar-header{padding:12px 14px;gap:8px}
            .ar-header a,.ar-header button{width:40px;height:40px;font-size:15px}
            .ar-id-pill{max-width:140px;padding:5px 10px;font-size:10px}
            .ar-card{min-height:60vh}
            .ar-card-head{padding:12px 16px}
            .ar-card-head .chem-name{font-size:15px}
            .ar-card-head .type-ic{width:38px;height:38px;font-size:16px}
            .ar-props{grid-template-columns:repeat(3,1fr);gap:6px;padding:0 16px 12px}
            .ar-props .ar-prop{padding:8px 4px}
            .ar-props .ar-prop .p-v{font-size:13px}
            .ar-props .ar-prop .p-l{font-size:8px}
            .ar-details{padding:0 16px 10px;gap:6px}
            .ar-details .ar-detail{padding:6px 0}
            .ar-details .ar-detail .d-v{font-size:12px}
            .ar-card-tags{padding:0 16px;gap:4px}
            .ar-tag{font-size:9px;padding:4px 8px}
            .ar-expiry{margin:0 16px 10px;padding:8px 12px;font-size:10px}
            .ar-actions{padding:8px 16px 16px;gap:6px;flex-wrap:wrap}
            .ar-actions a,.ar-actions button{padding:10px 8px;font-size:11px}
            .ar-hazard-strip{left:8px;top:100px}
            .ar-hz-diamond{width:36px;height:36px}
            .ar-hz-inner{inset:4px;font-size:10px}
            .ar-fluid-col{right:8px;top:100px}
            .ar-fluid-bar{width:36px;height:100px}
            .ar-fluid-pct{font-size:12px}
            .ar-fluid-label{font-size:8px}
            .ar-signal{top:90px;padding:5px 12px;font-size:10px}
            .ar-launch{bottom:20px;right:20px;width:52px;height:52px;font-size:20px}
        }
        @media(max-width:400px){
            .ar-props{grid-template-columns:repeat(3,1fr)}
            .ar-card-head .chem-name{font-size:14px}
            .ar-header{padding:10px 12px}
            .ar-card{padding-bottom:10px}
            .ar-actions{padding:8px 12px 14px;flex-wrap:wrap}
            .ar-actions a,.ar-actions button{min-width:calc(50% - 4px);flex:1 1 calc(50% - 4px)}
        }
        /* ═══ Tab Navigation ═══ */
        .ar-tabs{display:flex;gap:0;margin:0 20px 16px;background:rgba(255,255,255,.05);border-radius:12px;padding:4px;border:1px solid rgba(255,255,255,.06)}
        .ar-tab{flex:1;padding:9px 4px;border-radius:9px;border:none;background:none;color:rgba(255,255,255,.45);font-size:11px;font-weight:700;cursor:pointer;transition:all .2s;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:5px;letter-spacing:.3px}
        .ar-tab i{font-size:10px}
        .ar-tab.active{background:linear-gradient(135deg,rgba(99,102,241,.35),rgba(99,102,241,.2));color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.2);border:1px solid rgba(99,102,241,.25)}
        .ar-tab:hover:not(.active){color:rgba(255,255,255,.7);background:rgba(255,255,255,.05)}
        .ar-tab-panel{display:none}.ar-tab-panel.active{display:block}

        /* ═══ Section Header ═══ */
        .ar-sec-hdr{display:flex;align-items:center;gap:8px;padding:0 24px 10px;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:1.2px;color:rgba(255,255,255,.3)}
        .ar-sec-hdr::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,rgba(255,255,255,.06),transparent)}

        /* ═══ Description Block ═══ */
        .ar-desc{margin:0 24px 16px;padding:12px 16px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:12px;font-size:12px;line-height:1.65;color:rgba(255,255,255,.55);position:relative}
        .ar-desc::before{content:'';position:absolute;left:0;top:8px;bottom:8px;width:3px;background:linear-gradient(to bottom,#6366f1,transparent);border-radius:0 2px 2px 0}

        /* ═══ Status/Quality Badges (inline) ═══ */
        .ar-status-row{display:flex;gap:8px;padding:0 24px;margin-bottom:16px;flex-wrap:wrap}
        .ar-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:8px;font-size:10.5px;font-weight:700;border:1px solid;letter-spacing:.3px}

        /* ═══ Enhanced Quantity Bar ═══ */
        .ar-qty-block{margin:0 24px 16px;padding:14px 16px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:14px}
        .ar-qty-header{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px}
        .ar-qty-num{font-size:22px;font-weight:900;line-height:1;letter-spacing:-0.5px}
        .ar-qty-of{font-size:11px;color:rgba(255,255,255,.35);font-weight:500}
        .ar-qty-pct{font-size:13px;font-weight:800}
        .ar-qty-track{height:8px;background:rgba(255,255,255,.08);border-radius:6px;overflow:hidden;margin-bottom:8px;position:relative}
        .ar-qty-fill{height:100%;border-radius:6px;position:relative;transition:width 1s cubic-bezier(.4,0,.2,1)}
        .ar-qty-fill::after{content:'';position:absolute;top:0;left:0;right:0;height:50%;background:rgba(255,255,255,.25);border-radius:6px 6px 0 0}
        .ar-qty-marks{display:flex;justify-content:space-between;font-size:8.5px;color:rgba(255,255,255,.2);font-weight:600}

        /* ═══ GHS Classification List ═══ */
        .ar-ghs-list{padding:0 24px 16px;display:flex;flex-direction:column;gap:6px}
        .ar-ghs-item{display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(255,255,255,.03);border-radius:10px;border:1px solid rgba(255,255,255,.05);transition:background .2s}
        .ar-ghs-item:hover{background:rgba(255,255,255,.06)}
        .ar-ghs-ic{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;transform:rotate(0);position:relative}
        .ar-ghs-diamond{width:22px;height:22px;transform:rotate(45deg);border-radius:3px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border-width:2px;border-style:solid}
        .ar-ghs-diamond i{transform:rotate(-45deg);font-size:10px}
        .ar-ghs-name{font-size:12px;font-weight:600;color:rgba(255,255,255,.85)}
        .ar-ghs-sub{font-size:10px;color:rgba(255,255,255,.35);margin-top:1px}

        /* ═══ Signal Block ═══ */
        .ar-signal-block{margin:0 24px 16px;padding:14px 18px;border-radius:14px;display:flex;align-items:center;gap:14px}
        .ar-signal-block .s-ic{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
        .ar-signal-block .s-info h4{font-size:14px;font-weight:800;margin:0 0 2px}
        .ar-signal-block .s-info p{font-size:11px;opacity:.6;margin:0}

        /* ═══ Location Card ═══ */
        .ar-loc-tree{padding:0 24px 16px;display:flex;flex-direction:column;gap:6px}
        .ar-loc-row{display:flex;align-items:center;gap:12px;padding:10px 14px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);border-radius:10px}
        .ar-loc-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
        .ar-loc-label{font-size:9.5px;color:rgba(255,255,255,.3);font-weight:700;text-transform:uppercase;letter-spacing:.8px;margin-bottom:2px}
        .ar-loc-val{font-size:13px;font-weight:600;color:rgba(255,255,255,.9)}
        .ar-loc-connector{width:1px;height:10px;background:rgba(255,255,255,.1);margin-left:40px}

        /* ═══ Key-Value Detail Rows (compact) ═══ */
        .ar-kv-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:0 24px 16px}
        .ar-kv{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);border-radius:10px;padding:10px 12px;transition:background .2s}
        .ar-kv:hover{background:rgba(255,255,255,.06)}
        .ar-kv.full{grid-column:1/-1}
        .ar-kv .kv-l{font-size:9px;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.8px;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:4px}
        .ar-kv .kv-l i{font-size:8px}
        .ar-kv .kv-v{font-size:13px;font-weight:600;color:rgba(255,255,255,.88);line-height:1.3}
        .ar-kv .kv-v.mono{font-family:'Courier New',monospace;color:#818cf8;font-size:11.5px}
        .ar-kv .kv-v.green{color:#4ade80}
        .ar-kv .kv-v.yellow{color:#fde68a}
        .ar-kv .kv-v.red{color:#f87171}

        /* Landscape orientation adjustments */
        @media (orientation: landscape) and (max-height: 500px) {
            .ar-card{max-height:80vh;overflow-y:auto}
            .ar-card-head{padding:10px 16px}
        }

        /* ═══ Desktop Side Panel (≥769px) ═══ */
        @media(min-width:769px){
            .ar-header{right:420px}
            #viewerArea{right:420px}
            .ar-card{top:0;right:0;bottom:0;left:auto!important;width:420px;max-height:100vh;border-radius:0!important;transform:none!important;overflow-y:auto;border:none;border-left:1px solid rgba(255,255,255,.08);box-shadow:-20px 0 60px rgba(0,0,0,.5)}
            .ar-card.minimized{transform:none!important}
            .ar-peek{display:none}
            .ar-card-head{padding:24px 24px 14px}
            .ar-card-head .chem-name{font-size:18px}
            .ar-fluid-col{right:436px}
            .ar-signal{left:calc((100vw - 420px)/2);transform:translateX(-50%)}
            .ar-launch{display:none!important}
            .ar-actions{padding:16px 20px 28px;flex-wrap:wrap;gap:8px}
            .ar-actions a,.ar-actions button{min-width:calc(50% - 4px);flex:1 1 calc(50% - 4px);padding:14px 12px}
            .ar-act-ar,.ar-act-spatial{font-size:13px}
        }
    </style>
</head>
<body>
<?php
require_once __DIR__ . '/../includes/database.php';

$qrCode = $_GET['qr'] ?? '';
$containerId = $_GET['id'] ?? '';
$source = $_GET['source'] ?? ''; // 'stock' or 'container' or auto-detect
$container = null;
$isStock = false;

// ── Container SELECT ──
$cnSelect = "SELECT cn.*, 
    ch.name as chemical_name, ch.cas_number, ch.hazard_pictograms,
    ch.signal_word, ch.ghs_classifications, ch.description as chem_description,
    ch.molecular_formula, ch.molecular_weight, ch.physical_state,
    ch.sds_url as chem_sds_url, ch.image_url as chem_image,
    u.first_name, u.last_name, u.full_name_th,
    l.name as lab_name,
    b.name as building_name, b.shortname as building_short,
    rm.name as room_name, rm.code as room_code,
    mfr.name as manufacturer_name,
    cab.name as cabinet_name
FROM containers cn
LEFT JOIN chemicals ch ON cn.chemical_id = ch.id
LEFT JOIN users u ON cn.owner_id = u.id
LEFT JOIN labs l ON cn.lab_id = l.id
LEFT JOIN buildings b ON cn.building_id = b.id
LEFT JOIN rooms rm ON cn.room_id = rm.id
LEFT JOIN manufacturers mfr ON cn.manufacturer_id = mfr.id
LEFT JOIN cabinets cab ON cn.cabinet_id = cab.id";

// ── Chemical Stock SELECT (maps to same column names) ──
$csSelect = "SELECT s.id, 'bottle' as container_type, 'glass' as container_material,
    s.package_size as initial_quantity, s.remaining_qty as current_quantity,
    s.unit as quantity_unit, s.remaining_pct as remaining_percentage,
    s.status, 'good' as quality_status, s.grade,
    NULL as cost, NULL as expiry_date, s.added_at as received_date,
    s.bottle_code, NULL as qr_code, NULL as batch_number, NULL as lot_number,
    NULL as building_id, NULL as room_id, NULL as container_3d_model,
    NULL as notes, s.created_at,
    s.chemical_name, s.cas_no as cas_number,
    COALESCE(ch2.hazard_pictograms, '[]') as hazard_pictograms,
    ch2.signal_word, ch2.ghs_classifications,
    ch2.description as chem_description,
    ch2.molecular_formula, ch2.molecular_weight, ch2.physical_state,
    ch2.sds_url as chem_sds_url, ch2.image_url as chem_image,
    u2.first_name, u2.last_name, u2.full_name_th,
    NULL as lab_name,
    NULL as building_name, NULL as building_short,
    NULL as room_name, NULL as room_code,
    NULL as manufacturer_name,
    s.storage_location
FROM chemical_stock s
LEFT JOIN chemicals ch2 ON s.chemical_id = ch2.id
LEFT JOIN users u2 ON s.owner_user_id = u2.id";

if ($qrCode) {
    // QR code is only for containers table
    $container = Database::fetch($cnSelect . " WHERE cn.qr_code = :qr", [':qr' => $qrCode]);
} elseif ($containerId) {
    $numId = (int)$containerId;
    
    // Auto-detect source: negative ID = stock, positive = container
    if ($source === 'stock' || $numId < 0) {
        $isStock = true;
        $realId = abs($numId);
        $container = Database::fetch($csSelect . " WHERE s.id = :id", [':id' => $realId]);
    } else {
        // Try containers first
        $container = Database::fetch($cnSelect . " WHERE cn.id = :id", [':id' => $numId]);
        // Fallback to stock if not found
        if (!$container) {
            $isStock = true;
            $container = Database::fetch($csSelect . " WHERE s.id = :id", [':id' => $numId]);
        }
    }
}

if (!$container) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@600;800&display=swap" rel="stylesheet">
    <style>body{margin:0;height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0c0c1d,#1a1a3e);font-family:Inter,sans-serif;color:#fff;text-align:center}
    .box{padding:40px}.box i{font-size:48px;color:#fbbf24;margin-bottom:16px;display:block}.box h2{font-size:20px;font-weight:800;margin:0 0 8px}.box p{font-size:13px;color:rgba(255,255,255,.5);margin:0 0 20px}
    .box a{display:inline-block;padding:10px 24px;background:linear-gradient(135deg,#6366f1,#818cf8);border-radius:12px;color:#fff;text-decoration:none;font-weight:700;font-size:13px}</style></head>
    <body><div class="box"><i class="fas fa-exclamation-triangle"></i><h2>ไม่พบข้อมูล Container</h2><p>QR Code หรือ ID ไม่ถูกต้อง</p><a href="/v1/pages/containers.php"><i class="fas fa-arrow-left"></i> กลับหน้าหลัก</a></div></body></html>';
    exit;
}

// Parse data
$hazardPictograms = json_decode($container['hazard_pictograms'] ?? '[]', true);
if (!is_array($hazardPictograms)) $hazardPictograms = [];
$ghsClassifications = json_decode($container['ghs_classifications'] ?? '[]', true);
if (!is_array($ghsClassifications)) $ghsClassifications = [];
$remainingPercent = (float)($container['remaining_percentage'] ?? 100);
$fluidColor = $remainingPercent > 50 ? '#3b82f6' : ($remainingPercent > 20 ? '#eab308' : '#ef4444');
$fluidGrad = $remainingPercent > 50 ? 'linear-gradient(to top,#2563eb,#60a5fa)' : ($remainingPercent > 20 ? 'linear-gradient(to top,#ca8a04,#fbbf24)' : 'linear-gradient(to top,#dc2626,#f87171)');
$signalWord = $container['signal_word'] ?? '';
$ownerName = $container['full_name_th'] ?? trim(($container['first_name'] ?? '') . ' ' . ($container['last_name'] ?? ''));
$chemName = $container['chemical_name'] ?? 'Unknown Chemical';
$casNumber = $container['cas_number'] ?? '';
$formula = $container['molecular_formula'] ?? '';
$mw = !empty($container['molecular_weight']) ? number_format((float)$container['molecular_weight'], 2) : '';
$physState = $container['physical_state'] ?? '';
$containerType = $container['container_type'] ?? 'bottle';
$containerMaterial = $container['container_material'] ?? '';
$grade = $container['grade'] ?? '';
$curQty = $container['current_quantity'] ?? 0;
$initQty = $container['initial_quantity'] ?? 0;
$unit = $container['quantity_unit'] ?? '';
$bottleCode = $container['bottle_code'] ?? '';
$labName = $container['lab_name'] ?? '';
$locationParts = [];
if ($isStock) {
    $locationText = $container['storage_location'] ?? '-';
} else {
    if (!empty($container['building_short'])) $locationParts[] = $container['building_short'];
    elseif (!empty($container['building_name'])) $locationParts[] = $container['building_name'];
    if (!empty($container['room_code'])) $locationParts[] = $container['room_code'];
    elseif (!empty($container['room_name'])) $locationParts[] = $container['room_name'];
    $locationText = implode(' › ', $locationParts) ?: '-';
}
$mfrName = $container['manufacturer_name'] ?? '';
$cabinetName = $container['cabinet_name'] ?? '';
$sdsUrl = $container['chem_sds_url'] ?? '';
$chemDescription = $container['chem_description'] ?? '';
$qualityStatus = $container['quality_status'] ?? '';
$invoiceNumber = $container['invoice_number'] ?? '';
$expiryDate = $container['expiry_date'] ?? '';
$isExpired = $expiryDate && strtotime($expiryDate) < time();
$isExpiringSoon = $expiryDate && !$isExpired && strtotime($expiryDate) <= strtotime('+30 days');
$daysToExpiry = $expiryDate ? (int)ceil((strtotime($expiryDate) - time()) / 86400) : null;
$containerStatus = $container['status'] ?? 'active';
$statusColors = ['active'=>'#4ade80','empty'=>'#94a3b8','expired'=>'#f87171','quarantined'=>'#fbbf24','low'=>'#fb923c'];
$statusLabels = ['active'=>'ปกติ','empty'=>'หมด','expired'=>'หมดอายุ','quarantined'=>'กักกัน','low'=>'ใกล้หมด'];
$qualityColors = ['good'=>'#4ade80','damaged'=>'#f87171','contaminated'=>'#fb923c','unknown'=>'#94a3b8'];
$qualityLabels = ['good'=>'ดี','damaged'=>'เสียหาย','contaminated'=>'ปนเปื้อน','unknown'=>'ไม่ทราบ'];

// ═══ Resolve 3D Model from packaging_3d_models ═══
$modelUrl = null;
$modelType = null; // 'glb' or 'embed'
$embedCode = null;

// 1. Check container_3d_model field first (direct assignment)
if (!empty($container['container_3d_model'])) {
    $modelUrl = $container['container_3d_model'];
    $modelType = 'glb';
}

// 2. Look up packaging_3d_models by container_type + material
if (!$modelUrl) {
    // Try exact match on type + material first
    $model3d = null;
    if (!empty($containerMaterial)) {
        $model3d = Database::fetch(
            "SELECT * FROM packaging_3d_models 
             WHERE container_type = :t 
             AND container_material = :m
             AND is_active = 1
             ORDER BY is_default DESC, id DESC LIMIT 1",
            [':t' => $containerType, ':m' => $containerMaterial]
        );
    }
    // Fallback: try just by type (any material or null)
    if (!$model3d) {
        $model3d = Database::fetch(
            "SELECT * FROM packaging_3d_models 
             WHERE container_type = :t AND is_active = 1 
             ORDER BY is_default DESC, id DESC LIMIT 1",
            [':t' => $containerType]
        );
    }
    // Last fallback: any default model
    if (!$model3d) {
        $model3d = Database::fetch(
            "SELECT * FROM packaging_3d_models 
             WHERE is_active = 1 AND is_default = 1 
             ORDER BY id DESC LIMIT 1"
        );
    }
    if ($model3d) {
        if ($model3d['source_type'] === 'embed' && !empty($model3d['embed_url'])) {
            $modelUrl = $model3d['embed_url'];
            $modelType = 'embed';
            $embedCode = $model3d['embed_code'] ?? null;
        } elseif (!empty($model3d['file_url'])) {
            $modelUrl = $model3d['file_url'];
            $modelType = 'glb';
        }
    }
}

$hasModel = !empty($modelUrl);

// GHS icon/label mappings
$ghsIcons = [
    'compressed_gas' => 'fa-wind', 'flammable' => 'fa-fire-flame-curved', 'oxidizing' => 'fa-circle-radiation',
    'toxic' => 'fa-skull-crossbones', 'corrosive' => 'fa-flask-vial', 'irritant' => 'fa-exclamation-triangle',
    'environmental' => 'fa-leaf', 'health_hazard' => 'fa-heart-crack', 'explosive' => 'fa-explosion'
];
$ghsLabels = [
    'compressed_gas' => 'ก๊าซอัด', 'flammable' => 'ไวไฟ', 'oxidizing' => 'วัตถุออกซิไดซ์',
    'toxic' => 'พิษเฉียบพลัน', 'corrosive' => 'กัดกร่อน', 'irritant' => 'ระคายเคือง',
    'environmental' => 'อันตรายต่อสิ่งแวดล้อม', 'health_hazard' => 'อันตรายต่อสุขภาพ', 'explosive' => 'วัตถุระเบิด'
];
$typeIcons = ['bottle'=>'fa-wine-bottle','vial'=>'fa-vial','flask'=>'fa-flask','canister'=>'fa-gas-pump','cylinder'=>'fa-fire-extinguisher','ampoule'=>'fa-syringe','bag'=>'fa-bag-shopping'];
$typeColors = ['bottle'=>'#818cf8','vial'=>'#c084fc','flask'=>'#34d399','canister'=>'#fb923c','cylinder'=>'#f472b6','ampoule'=>'#60a5fa','bag'=>'#a1a1aa'];
$typeBg = ['bottle'=>'rgba(99,102,241,.15)','vial'=>'rgba(168,85,247,.15)','flask'=>'rgba(16,185,129,.15)','canister'=>'rgba(234,88,12,.15)','cylinder'=>'rgba(236,72,153,.15)','ampoule'=>'rgba(59,130,246,.15)','bag'=>'rgba(161,161,170,.15)'];
?>

<!-- ═══ 3D / AR View Area ═══ -->
<div id="viewerArea">
<?php if ($hasModel && $modelType === 'glb'): ?>
    <model-viewer
        id="model-viewer"
        src="<?php echo htmlspecialchars($modelUrl); ?>"
        alt="3D model of <?php echo htmlspecialchars($chemName); ?> container"
        camera-controls
        auto-rotate
        rotation-per-second="20deg"
        ar
        ar-modes="webxr scene-viewer quick-look"
        ar-scale="auto"
        ar-placement="floor"
        xr-environment
        shadow-intensity="1.2"
        shadow-softness="0.8"
        exposure="0.9"
        environment-image="neutral"
        interaction-prompt="none"
        auto-rotate-delay="3000"
        style="display:block">
    </model-viewer>
    <!-- WebGL fallback (shown when GPU/driver doesn't support WebGL) -->
    <div class="fallback-3d" id="fallbackView" style="display:none">
        <div class="bottle-3d">
            <div class="bottle-cap"></div>
            <div class="bottle-neck"></div>
            <div class="bottle-body">
                <div class="bottle-fluid" style="height:<?php echo $remainingPercent; ?>%;background:<?php echo $fluidGrad; ?>"></div>
                <div class="bottle-label">
                    <div class="bl-name"><?php echo htmlspecialchars(mb_strimwidth($chemName, 0, 30, '…')); ?></div>
                    <?php if ($casNumber): ?><div class="bl-cas">CAS: <?php echo htmlspecialchars($casNumber); ?></div><?php endif; ?>
                </div>
            </div>
            <div class="bottle-pct-label" style="color:<?php echo $fluidColor; ?>"><?php echo round($remainingPercent); ?>%</div>
        </div>
        <div style="position:absolute;bottom:100px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.55);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:8px 16px;font-size:11px;color:rgba(255,255,255,.5);white-space:nowrap;text-align:center">
            <i class="fas fa-triangle-exclamation" style="color:#fbbf24;margin-right:6px"></i>3D viewer ไม่พร้อมใช้งาน (GPU ไม่รองรับ WebGL)
        </div>
    </div>
<?php elseif ($hasModel && $modelType === 'embed'): ?>
    <div class="embed-viewer">
        <iframe src="<?php echo htmlspecialchars($modelUrl); ?>" allow="autoplay; fullscreen; xr-spatial-tracking" allowfullscreen loading="lazy"></iframe>
    </div>
<?php else: ?>
    <!-- Fallback: Animated CSS Bottle -->
    <div class="fallback-3d">
        <div class="bottle-3d">
            <div class="bottle-cap"></div>
            <div class="bottle-neck"></div>
            <div class="bottle-body">
                <div class="bottle-fluid" style="height:<?php echo $remainingPercent; ?>%;background:<?php echo $fluidGrad; ?>"></div>
                <div class="bottle-label">
                    <div class="bl-name"><?php echo htmlspecialchars(mb_strimwidth($chemName, 0, 30, '…')); ?></div>
                    <?php if ($casNumber): ?><div class="bl-cas">CAS: <?php echo htmlspecialchars($casNumber); ?></div><?php endif; ?>
                </div>
            </div>
            <div class="bottle-pct-label" style="color:<?php echo $fluidColor; ?>"><?php echo round($remainingPercent); ?>%</div>
        </div>
    </div>
<?php endif; ?>
</div>

<!-- ═══ Top Header ═══ -->
<div class="ar-header">
    <a href="/v1/pages/containers.php" title="Back"><i class="fas fa-arrow-left"></i></a>
    <div class="ar-id-pill">
        <i class="fas <?php echo $isStock ? 'fa-database' : 'fa-box'; ?>"></i>
        <span><?php echo htmlspecialchars($bottleCode ?: ('ID: ' . ($isStock ? '-' : '') . $container['id'])); ?></span>
        <?php if ($isStock): ?><span style="font-size:8px;padding:1px 6px;border-radius:4px;background:rgba(245,158,11,.2);color:#fbbf24;margin-left:2px">CSV</span><?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <?php if ($hasModel && $modelType === 'glb'): ?>
        <button id="btnHeaderARSpatial" class="ar-act-spatial" title="AR เชิงพื้นที่"><i class="fas fa-cube"></i></button>
        <?php endif; ?>
        <button id="btnShare" title="Share"><i class="fas fa-share-alt"></i></button>
    </div>
</div>

<!-- ═══ Signal Word Badge ═══ -->
<?php if ($signalWord): ?>
<div class="ar-signal <?php echo $signalWord === 'Danger' ? 'danger' : 'warning'; ?>">
    <i class="fas <?php echo $signalWord === 'Danger' ? 'fa-radiation' : 'fa-exclamation-triangle'; ?>"></i>
    <?php echo $signalWord === 'Danger' ? 'อันตราย — DANGER' : 'ระวัง — WARNING'; ?>
</div>
<?php endif; ?>

<!-- ═══ Hazard Diamonds (Left) ═══ -->
<?php if (count($hazardPictograms) > 0): ?>
<div class="ar-hazard-strip">
    <?php foreach ($hazardPictograms as $picto): ?>
    <div class="ar-hz-diamond hz-<?php echo htmlspecialchars($picto); ?>">
        <div class="ar-hz-inner"><i class="fas <?php echo $ghsIcons[$picto] ?? 'fa-exclamation'; ?>"></i></div>
        <div class="ar-hz-tip"><?php echo $ghsLabels[$picto] ?? $picto; ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ Fluid Level (Right) ═══ -->
<div class="ar-fluid-col">
    <div class="ar-fluid-bar">
        <div class="ar-fluid-fill" style="height:<?php echo $remainingPercent; ?>%;background:<?php echo $fluidGrad; ?>"></div>
        <div class="ar-fluid-pct"><?php echo round($remainingPercent); ?>%</div>
    </div>
    <div class="ar-fluid-label">เหลือ</div>
</div>

<!-- ═══ Bottom Info Card ═══ -->
<div class="ar-card" id="arCard">
    <div class="ar-peek" id="cardHandle">
        <div class="ar-peek-bar"></div>
        <div class="ar-peek-row">
            <div class="ar-peek-icon" style="background:<?php echo $typeBg[$containerType]??'rgba(99,102,241,.15)';?>;color:<?php echo $typeColors[$containerType]??'#818cf8';?>">
                <i class="fas <?php echo $typeIcons[$containerType]??'fa-box';?>"></i>
            </div>
            <div class="ar-peek-text">
                <div class="ar-peek-name"><?php echo htmlspecialchars($chemName);?></div>
                <div class="ar-peek-sub"><?php echo $casNumber?'CAS: '.htmlspecialchars($casNumber):htmlspecialchars(ucfirst($containerType));?></div>
            </div>
            <div class="ar-peek-pct" style="color:<?php echo $fluidColor;?>"><?php echo round($remainingPercent);?>%</div>
        </div>
    </div>

    <!-- Header -->
    <div class="ar-card-head">
        <div class="type-ic" style="background:<?php echo $typeBg[$containerType] ?? 'rgba(99,102,241,.15)'; ?>;color:<?php echo $typeColors[$containerType] ?? '#818cf8'; ?>">
            <i class="fas <?php echo $typeIcons[$containerType] ?? 'fa-box'; ?>"></i>
        </div>
        <div class="info">
            <div class="chem-name"><?php echo htmlspecialchars($chemName); ?></div>
            <div class="chem-sub">
                <?php if ($casNumber): ?>CAS: <b><?php echo htmlspecialchars($casNumber); ?></b><?php endif; ?>
                <?php if ($formula): ?> &bull; <b><?php echo htmlspecialchars($formula); ?></b><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tags strip -->
    <div class="ar-card-tags">
        <span class="ar-tag ar-tag-type"><i class="fas <?php echo $typeIcons[$containerType] ?? 'fa-box'; ?>" style="font-size:8px"></i> <?php echo ucfirst($containerType); ?></span>
        <?php if ($containerMaterial): ?><span class="ar-tag ar-tag-material"><?php echo ucfirst($containerMaterial); ?></span><?php endif; ?>
        <?php if ($grade): ?><span class="ar-tag ar-tag-grade"><?php echo htmlspecialchars($grade); ?></span><?php endif; ?>
        <?php if ($signalWord === 'Danger'): ?><span class="ar-tag ar-tag-danger"><i class="fas fa-radiation" style="font-size:8px"></i> Danger</span>
        <?php elseif ($signalWord === 'Warning'): ?><span class="ar-tag ar-tag-warning"><i class="fas fa-exclamation-triangle" style="font-size:8px"></i> Warning</span><?php endif; ?>
        <?php if ($isStock): ?><span class="ar-tag" style="border-color:rgba(245,158,11,.3);color:#fbbf24;background:rgba(245,158,11,.1)">Stock DB</span><?php endif; ?>
    </div>

    <!-- Tab Navigation -->
    <div class="ar-tabs">
        <button class="ar-tab active" onclick="switchTab('overview',this)"><i class="fas fa-flask"></i> ภาพรวม</button>
        <button class="ar-tab" onclick="switchTab('safety',this)"><i class="fas fa-shield-halved"></i> ความปลอดภัย</button>
        <button class="ar-tab" onclick="switchTab('location',this)"><i class="fas fa-map-pin"></i> ที่ตั้ง</button>
    </div>

    <!-- ══ TAB: OVERVIEW ══ -->
    <div id="tab-overview" class="ar-tab-panel active">

        <!-- Status badges -->
        <div class="ar-status-row">
            <?php $sc = $statusColors[$containerStatus] ?? '#94a3b8'; ?>
            <span class="ar-badge" style="color:<?php echo $sc; ?>;border-color:<?php echo $sc; ?>33;background:<?php echo $sc; ?>18">
                <span style="width:6px;height:6px;border-radius:50%;background:<?php echo $sc; ?>;flex-shrink:0"></span>
                <?php echo $statusLabels[$containerStatus] ?? ucfirst($containerStatus); ?>
            </span>
            <?php if ($qualityStatus): $qc = $qualityColors[$qualityStatus] ?? '#94a3b8'; ?>
            <span class="ar-badge" style="color:<?php echo $qc; ?>;border-color:<?php echo $qc; ?>33;background:<?php echo $qc; ?>18">
                <i class="fas fa-star" style="font-size:8px"></i>
                <?php echo $qualityLabels[$qualityStatus] ?? ucfirst($qualityStatus); ?>
            </span>
            <?php endif; ?>
            <?php if ($physState): ?>
            <span class="ar-badge" style="color:#a5b4fc;border-color:rgba(99,102,241,.3);background:rgba(99,102,241,.1)">
                <i class="fas fa-atom" style="font-size:8px"></i> <?php echo ucfirst($physState); ?>
            </span>
            <?php endif; ?>
        </div>

        <!-- Quantity bar -->
        <div class="ar-qty-block">
            <div class="ar-qty-header">
                <div>
                    <span class="ar-qty-num" style="color:<?php echo $fluidColor; ?>"><?php echo number_format((float)$curQty,2); ?></span>
                    <span class="ar-qty-of"> / <?php echo number_format((float)$initQty,2); ?> <?php echo htmlspecialchars($unit); ?></span>
                </div>
                <span class="ar-qty-pct" style="color:<?php echo $fluidColor; ?>"><?php echo round($remainingPercent); ?>%</span>
            </div>
            <div class="ar-qty-track">
                <div class="ar-qty-fill" style="width:<?php echo min(100, $remainingPercent); ?>%;background:<?php echo $fluidGrad; ?>"></div>
            </div>
            <div class="ar-qty-marks">
                <span>0%</span><span>25%</span><span>50%</span><span>75%</span><span>100%</span>
            </div>
        </div>

        <!-- Expiry -->
        <?php if ($expiryDate): ?>
        <div class="ar-expiry <?php echo $isExpired ? 'expired' : ($isExpiringSoon ? 'warn' : 'ok'); ?>" style="margin:0 24px 16px">
            <i class="fas <?php echo $isExpired ? 'fa-circle-exclamation' : ($isExpiringSoon ? 'fa-hourglass-half' : 'fa-calendar-check'); ?>" style="font-size:18px;flex-shrink:0"></i>
            <div>
                <div style="font-size:12px;font-weight:800">
                    <?php echo $isExpired ? 'หมดอายุแล้ว' : ($isExpiringSoon ? 'ใกล้หมดอายุ' : 'ยังไม่หมดอายุ'); ?>
                </div>
                <div style="font-size:11px;opacity:.75;margin-top:2px">
                    <?php echo date('d M Y', strtotime($expiryDate)); ?>
                    <?php if ($daysToExpiry !== null): ?>
                    &nbsp;·&nbsp;
                    <?php if ($daysToExpiry < 0): ?>
                        <b><?php echo abs($daysToExpiry); ?> วันที่แล้ว</b>
                    <?php else: ?>
                        อีก <b><?php echo $daysToExpiry; ?> วัน</b>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Chemical info -->
        <?php if ($formula || $mw): ?>
        <div class="ar-sec-hdr"><i class="fas fa-atom"></i> คุณสมบัติเคมี</div>
        <div class="ar-kv-grid">
            <?php if ($formula): ?>
            <div class="ar-kv">
                <div class="kv-l"><i class="fas fa-atom"></i> สูตรโมเลกุล</div>
                <div class="kv-v" style="font-family:monospace;color:#c084fc;font-size:15px;font-weight:900"><?php echo htmlspecialchars($formula); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($mw): ?>
            <div class="ar-kv">
                <div class="kv-l"><i class="fas fa-weight-scale"></i> มวลโมเลกุล</div>
                <div class="kv-v"><?php echo $mw; ?> <span style="font-size:10px;opacity:.5">g/mol</span></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Description -->
        <?php if ($chemDescription): ?>
        <div class="ar-sec-hdr"><i class="fas fa-book"></i> คำอธิบาย</div>
        <div class="ar-desc"><?php echo htmlspecialchars($chemDescription); ?></div>
        <?php endif; ?>

        <!-- Container details -->
        <div class="ar-sec-hdr"><i class="fas fa-box"></i> รายละเอียดภาชนะ</div>
        <div class="ar-kv-grid">
            <div class="ar-kv">
                <div class="kv-l"><i class="fas fa-barcode"></i> รหัสขวด</div>
                <div class="kv-v mono"><?php echo htmlspecialchars($bottleCode ?: '-'); ?></div>
            </div>
            <?php if (!empty($container['batch_number'])): ?>
            <div class="ar-kv">
                <div class="kv-l"><i class="fas fa-hashtag"></i> Batch No.</div>
                <div class="kv-v mono"><?php echo htmlspecialchars($container['batch_number']); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($container['lot_number'])): ?>
            <div class="ar-kv">
                <div class="kv-l"><i class="fas fa-list-ol"></i> Lot No.</div>
                <div class="kv-v mono"><?php echo htmlspecialchars($container['lot_number']); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($mfrName): ?>
            <div class="ar-kv">
                <div class="kv-l"><i class="fas fa-industry"></i> ผู้ผลิต</div>
                <div class="kv-v"><?php echo htmlspecialchars($mfrName); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($container['cost'])): ?>
            <div class="ar-kv">
                <div class="kv-l"><i class="fas fa-coins"></i> ราคา</div>
                <div class="kv-v green"><?php echo number_format((float)$container['cost']); ?> ฿</div>
            </div>
            <?php endif; ?>
            <?php if ($invoiceNumber): ?>
            <div class="ar-kv">
                <div class="kv-l"><i class="fas fa-file-invoice"></i> Invoice</div>
                <div class="kv-v mono"><?php echo htmlspecialchars($invoiceNumber); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($container['received_date'])): ?>
            <div class="ar-kv">
                <div class="kv-l"><i class="fas fa-calendar-plus"></i> วันที่รับ</div>
                <div class="kv-v"><?php echo date('d M Y', strtotime($container['received_date'])); ?></div>
            </div>
            <?php endif; ?>
            <div class="ar-kv">
                <div class="kv-l"><i class="fas fa-user"></i> เจ้าของ</div>
                <div class="kv-v"><?php echo htmlspecialchars($ownerName ?: '-'); ?></div>
            </div>
            <?php if (!empty($container['notes'])): ?>
            <div class="ar-kv full">
                <div class="kv-l"><i class="fas fa-note-sticky"></i> หมายเหตุ</div>
                <div class="kv-v" style="font-size:12px;line-height:1.5"><?php echo htmlspecialchars($container['notes']); ?></div>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /tab-overview -->

    <!-- ══ TAB: SAFETY ══ -->
    <div id="tab-safety" class="ar-tab-panel">

        <?php if ($signalWord): ?>
        <div class="ar-sec-hdr"><i class="fas fa-triangle-exclamation"></i> คำสัญญาณ</div>
        <?php
            $sdanger = ($signalWord === 'Danger');
            $sbg = $sdanger ? 'rgba(220,38,38,.12)' : 'rgba(245,158,11,.12)';
            $sbc = $sdanger ? 'rgba(220,38,38,.3)' : 'rgba(245,158,11,.3)';
            $stxt = $sdanger ? '#fca5a5' : '#fde68a';
            $sicBg = $sdanger ? 'rgba(220,38,38,.2)' : 'rgba(245,158,11,.2)';
        ?>
        <div class="ar-signal-block" style="background:<?php echo $sbg; ?>;border:1px solid <?php echo $sbc; ?>;color:<?php echo $stxt; ?>">
            <div class="s-ic" style="background:<?php echo $sicBg; ?>">
                <i class="fas <?php echo $sdanger ? 'fa-radiation' : 'fa-exclamation-triangle'; ?>" style="font-size:18px"></i>
            </div>
            <div class="s-info">
                <h4><?php echo $sdanger ? 'อันตราย — DANGER' : 'ระวัง — WARNING'; ?></h4>
                <p><?php echo $sdanger ? 'สารอันตรายระดับสูง ใช้ความระมัดระวังสูงสุด' : 'สารที่ต้องระวัง ใช้ PPE ที่เหมาะสม'; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (count($hazardPictograms) > 0): ?>
        <div class="ar-sec-hdr"><i class="fas fa-biohazard"></i> สัญลักษณ์ GHS</div>
        <?php
        $ghsColors2 = [
            'compressed_gas'=>'#d97706','flammable'=>'#dc2626','oxidizing'=>'#d97706',
            'toxic'=>'#991b1b','corrosive'=>'#7c3aed','irritant'=>'#f59e0b',
            'environmental'=>'#16a34a','health_hazard'=>'#dc2626','explosive'=>'#ea580c'
        ];
        $ghsDesc = [
            'compressed_gas'=>'อาจระเบิดถ้าได้รับความร้อน',
            'flammable'=>'ติดไฟได้ง่าย ห่างจากเปลวไฟ',
            'oxidizing'=>'ออกซิไดซ์ เพิ่มความเสี่ยงไฟ',
            'toxic'=>'เป็นพิษถึงขั้นเสียชีวิต',
            'corrosive'=>'กัดกร่อนผิวหนัง/ดวงตา',
            'irritant'=>'ระคายเคืองต่อร่างกาย',
            'environmental'=>'เป็นอันตรายต่อสิ่งแวดล้อม',
            'health_hazard'=>'มีอันตรายต่อสุขภาพระยะยาว',
            'explosive'=>'อาจระเบิดได้'
        ];
        ?>
        <div class="ar-ghs-list">
            <?php foreach ($hazardPictograms as $picto): $gc = $ghsColors2[$picto] ?? '#dc2626'; ?>
            <div class="ar-ghs-item">
                <div class="ar-ghs-diamond" style="border-color:<?php echo $gc; ?>;background:<?php echo $gc; ?>22;color:<?php echo $gc; ?>">
                    <i class="fas <?php echo $ghsIcons[$picto] ?? 'fa-exclamation'; ?>"></i>
                </div>
                <div>
                    <div class="ar-ghs-name"><?php echo $ghsLabels[$picto] ?? $picto; ?></div>
                    <div class="ar-ghs-sub"><?php echo $ghsDesc[$picto] ?? ''; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (count($ghsClassifications) > 0): ?>
        <div class="ar-sec-hdr"><i class="fas fa-list-check"></i> การจัดประเภท GHS</div>
        <div class="ar-kv-grid">
            <?php foreach ($ghsClassifications as $gc): ?>
            <div class="ar-kv">
                <div class="kv-v" style="font-size:11.5px;color:rgba(255,255,255,.7)"><?php echo htmlspecialchars($gc); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($sdsUrl): ?>
        <div class="ar-sec-hdr"><i class="fas fa-file-pdf"></i> เอกสารความปลอดภัย</div>
        <div style="padding:0 24px 16px">
            <a href="<?php echo htmlspecialchars($sdsUrl); ?>" target="_blank"
               style="display:flex;align-items:center;gap:12px;padding:14px 18px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:12px;color:#fca5a5;text-decoration:none;font-weight:700;font-size:13px;transition:background .2s"
               onmouseover="this.style.background='rgba(239,68,68,.18)'" onmouseout="this.style.background='rgba(239,68,68,.1)'">
                <i class="fas fa-file-pdf" style="font-size:20px;color:#f87171"></i>
                <div>
                    <div>Safety Data Sheet (SDS)</div>
                    <div style="font-size:10px;opacity:.6;font-weight:500;margin-top:2px">คลิกเพื่อดาวน์โหลดเอกสาร PDF</div>
                </div>
                <i class="fas fa-arrow-up-right-from-square" style="margin-left:auto;font-size:12px;opacity:.5"></i>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!$signalWord && count($hazardPictograms) === 0): ?>
        <div style="padding:40px 24px;text-align:center;color:rgba(255,255,255,.25)">
            <i class="fas fa-shield-halved" style="font-size:36px;margin-bottom:12px;display:block;opacity:.4"></i>
            <div style="font-size:13px;font-weight:600">ไม่มีข้อมูลความเป็นอันตราย</div>
            <div style="font-size:11px;margin-top:4px">สารนี้ไม่มีการจัดประเภทความเป็นอันตราย GHS</div>
        </div>
        <?php endif; ?>

    </div><!-- /tab-safety -->

    <!-- ══ TAB: LOCATION ══ -->
    <div id="tab-location" class="ar-tab-panel">

        <div class="ar-sec-hdr"><i class="fas fa-map-pin"></i> ตำแหน่งจัดเก็บ</div>
        <div class="ar-loc-tree">
            <?php if (!$isStock): ?>
                <?php if ($container['building_name'] || $container['building_short']): ?>
                <div class="ar-loc-row">
                    <div class="ar-loc-icon" style="background:rgba(99,102,241,.15);color:#818cf8"><i class="fas fa-building"></i></div>
                    <div>
                        <div class="ar-loc-label">อาคาร</div>
                        <div class="ar-loc-val"><?php echo htmlspecialchars($container['building_name'] ?? $container['building_short']); ?></div>
                    </div>
                </div>
                <?php if ($container['room_name'] || $container['room_code']): ?><div class="ar-loc-connector"></div><?php endif; ?>
                <?php endif; ?>
                <?php if ($container['room_name'] || $container['room_code']): ?>
                <div class="ar-loc-row">
                    <div class="ar-loc-icon" style="background:rgba(16,185,129,.12);color:#34d399"><i class="fas fa-door-open"></i></div>
                    <div>
                        <div class="ar-loc-label">ห้อง</div>
                        <div class="ar-loc-val">
                            <?php echo htmlspecialchars($container['room_name'] ?? ''); ?>
                            <?php if ($container['room_code']): ?><span style="color:rgba(255,255,255,.4);font-size:11px;margin-left:6px">(<?php echo htmlspecialchars($container['room_code']); ?>)</span><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if ($cabinetName || $labName): ?><div class="ar-loc-connector"></div><?php endif; ?>
                <?php endif; ?>
                <?php if ($cabinetName): ?>
                <div class="ar-loc-row">
                    <div class="ar-loc-icon" style="background:rgba(245,158,11,.12);color:#fbbf24"><i class="fas fa-cabinet-filing"></i></div>
                    <div>
                        <div class="ar-loc-label">ตู้/ชั้น</div>
                        <div class="ar-loc-val"><?php echo htmlspecialchars($cabinetName); ?></div>
                    </div>
                </div>
                <?php if ($labName): ?><div class="ar-loc-connector"></div><?php endif; ?>
                <?php endif; ?>
                <?php if ($labName): ?>
                <div class="ar-loc-row">
                    <div class="ar-loc-icon" style="background:rgba(236,72,153,.12);color:#f472b6"><i class="fas fa-flask"></i></div>
                    <div>
                        <div class="ar-loc-label">ห้องปฏิบัติการ</div>
                        <div class="ar-loc-val"><?php echo htmlspecialchars($labName); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="ar-loc-row">
                    <div class="ar-loc-icon" style="background:rgba(245,158,11,.12);color:#fbbf24"><i class="fas fa-database"></i></div>
                    <div>
                        <div class="ar-loc-label">สถานที่จัดเก็บ</div>
                        <div class="ar-loc-val"><?php echo htmlspecialchars($locationText); ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Owner / Responsibility -->
        <div class="ar-sec-hdr"><i class="fas fa-user-shield"></i> รับผิดชอบ</div>
        <div class="ar-kv-grid">
            <div class="ar-kv full">
                <div class="kv-l"><i class="fas fa-user"></i> เจ้าของ / ผู้รับผิดชอบ</div>
                <div class="kv-v" style="font-size:15px"><?php echo htmlspecialchars($ownerName ?: 'ไม่ระบุ'); ?></div>
            </div>
            <?php if ($labName): ?>
            <div class="ar-kv full">
                <div class="kv-l"><i class="fas fa-flask"></i> ห้องปฏิบัติการ</div>
                <div class="kv-v"><?php echo htmlspecialchars($labName); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- QR + scan info -->
        <?php if ($bottleCode): ?>
        <div class="ar-sec-hdr"><i class="fas fa-qrcode"></i> รหัสสแกน</div>
        <div style="padding:0 24px 20px">
            <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:14px;display:flex;align-items:center;gap:14px">
                <div id="arQrMini" style="width:64px;height:64px;background:#fff;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;padding:3px"></div>
                <div>
                    <div style="font-size:10px;color:rgba(255,255,255,.4);font-weight:700;text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px">รหัสขวด</div>
                    <div style="font-family:'Courier New',monospace;font-size:13px;font-weight:700;color:#818cf8;letter-spacing:.5px"><?php echo htmlspecialchars($bottleCode); ?></div>
                    <div style="font-size:10px;color:rgba(255,255,255,.3);margin-top:4px">แสกน QR เพื่อเปิดหน้านี้</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /tab-location -->

    <!-- Actions -->
    <div class="ar-actions">
        <a href="/v1/pages/stock.php" class="ar-act-secondary"><i class="fas fa-arrow-left"></i> กลับ</a>
        <?php if ($hasModel && $modelType === 'glb'): ?>
        <button id="btnAR" class="ar-act-ar"><i class="fas fa-vr-cardboard"></i> AR</button>
        <button id="btnARSpatial" class="ar-act-spatial"><i class="fas fa-cube"></i> Spatial</button>
        <?php endif; ?>
        <?php $detailId = $isStock ? -(int)$container['id'] : (int)$container['id']; ?>
        <a href="/v1/pages/stock.php" class="ar-act-primary"><i class="fas fa-box-open"></i> คลังสาร</a>
    </div>
</div>

<!-- AR Launch FAB (visible when card is minimized) -->
<?php if ($hasModel && $modelType === 'glb'): ?>
<button class="ar-launch" id="arFab" title="เปิด AR"><i class="fas fa-vr-cardboard"></i></button>
<?php endif; ?>

<script>
// ═══ WebGL Detection ═══
(function(){
    const mv = document.getElementById('model-viewer');
    if (!mv) return; // no model-viewer on this page
    try {
        const c = document.createElement('canvas');
        const gl = c.getContext('webgl') || c.getContext('experimental-webgl');
        if (gl && gl instanceof WebGLRenderingContext) return; // WebGL OK
    } catch(e) {}
    // WebGL not available — swap to CSS fallback
    mv.style.display = 'none';
    const fb = document.getElementById('fallbackView');
    if (fb) fb.style.display = '';
    // Hide AR/3D action buttons, show disabled state
    ['btnAR','btnARSpatial','btnHeaderARSpatial','arFab'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
})();

// ═══ Card Toggle ═══
const card = document.getElementById('arCard');
const handle = document.getElementById('cardHandle');
const isDesktop = () => window.innerWidth >= 769;

// Start minimized on mobile
if (!isDesktop()) card.classList.add('minimized');

handle.addEventListener('click', () => {
    if (isDesktop()) return;
    card.classList.toggle('minimized');
});

// Swipe handle to expand / minimize
let _sy = 0;
handle.addEventListener('touchstart', e => { _sy = e.touches[0].clientY; }, {passive:true});
handle.addEventListener('touchend', e => {
    if (isDesktop()) return;
    const dy = e.changedTouches[0].clientY - _sy;
    if (dy > 40) card.classList.add('minimized');
    else if (dy < -40) card.classList.remove('minimized');
});

// ═══ Tab switching ═══
function switchTab(name, btn) {
    document.querySelectorAll('.ar-tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.ar-tab').forEach(b => b.classList.remove('active'));
    const panel = document.getElementById('tab-' + name);
    if (panel) panel.classList.add('active');
    if (btn) btn.classList.add('active');
    // Lazy-render QR on location tab
    if (name === 'location') renderMiniQR();
}

// ═══ Mini QR (location tab) ═══
let _miniQrDone = false;
function renderMiniQR() {
    if (_miniQrDone) return;
    const el = document.getElementById('arQrMini');
    if (!el) return;
    const url = <?php echo json_encode(((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'], JSON_UNESCAPED_UNICODE); ?>;
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
    s.onload = () => {
        try { new QRCode(el, { text: url, width: 58, height: 58, colorDark: '#000', colorLight: '#fff', correctLevel: QRCode.CorrectLevel.M }); _miniQrDone = true; }
        catch(e) { el.innerHTML = '<i class="fas fa-qrcode" style="font-size:30px;color:#999"></i>'; }
    };
    s.onerror = () => { el.innerHTML = '<i class="fas fa-qrcode" style="font-size:30px;color:#999"></i>'; };
    document.head.appendChild(s);
}

// ═══ Share ═══
document.getElementById('btnShare').addEventListener('click', () => {
    const data = {
        title: <?php echo json_encode($chemName, JSON_UNESCAPED_UNICODE); ?>,
        text: 'Chemical Container: ' + <?php echo json_encode($chemName . ' (' . $bottleCode . ')', JSON_UNESCAPED_UNICODE); ?>,
        url: window.location.href
    };
    if (navigator.share) navigator.share(data);
    else { navigator.clipboard.writeText(window.location.href); alert('คัดลอกลิงก์แล้ว!'); }
});

// ═══ AR Launch ═══
<?php if ($hasModel && $modelType === 'glb'): ?>
function launchAR() {
    const mv = document.getElementById('model-viewer');
    if (mv && mv.canActivateAR) {
        mv.activateAR();
    } else {
        alert('อุปกรณ์นี้ไม่รองรับ AR\nกรุณาใช้โทรศัพท์มือถือที่รองรับ ARCore/ARKit');
    }
}
document.getElementById('btnAR')?.addEventListener('click', launchAR);
document.getElementById('arFab')?.addEventListener('click', launchAR);

// AR Status Banner
(function() {
    const mv = document.getElementById('model-viewer');
    if (!mv) return;
    
    const banner = document.createElement('div');
    banner.style.cssText = 'position:fixed;top:100px;left:50%;transform:translateX(-50%);z-index:9999;display:none;align-items:center;gap:8px;padding:8px 18px;border-radius:12px;background:rgba(0,0,0,.85);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.1);font-size:12px;font-weight:600;color:#fff;white-space:nowrap';
    document.body.appendChild(banner);
    
    let anchored = false;
    mv.addEventListener('ar-status', e => {
        switch (e.detail) {
            case 'session-started':
                anchored = false;
                banner.innerHTML = '<span style="width:7px;height:7px;border-radius:50%;background:#fbbf24;flex-shrink:0"></span> สแกนพื้นผิว — เลื่อนกล้องช้าๆ';
                banner.style.display = 'flex';
                break;
            case 'object-placed':
                banner.innerHTML = '<span style="width:7px;height:7px;border-radius:50%;background:#22c55e;flex-shrink:0"></span> ✅ วางวัตถุแล้ว';
                setTimeout(() => {
                    anchored = true;
                    banner.innerHTML = '<span style="width:7px;height:7px;border-radius:50%;background:#a78bfa;flex-shrink:0"></span> 🔒 Spatial Anchor ยึดตำแหน่ง';
                }, 600);
                break;
            case 'failed':
            case 'not-presenting':
                banner.style.display = 'none';
                anchored = false;
                break;
        }
    });
    mv.addEventListener('ar-tracking', e => {
        if (e.detail === 'tracking' && !anchored) {
            banner.innerHTML = '<span style="width:7px;height:7px;border-radius:50%;background:#fbbf24;flex-shrink:0"></span> ตรวจจับพื้นผิวแล้ว — แตะเพื่อวาง';
        }
    });
})();
<?php endif; ?>

// ═══ AR Advanced - Full Featured AR with Spatial Anchors ═══
<?php if ($hasModel && $modelType === 'glb'): ?>
function openArSpatial() {
    const modelUrl = <?php echo json_encode($modelUrl); ?>;
    const chemName = <?php echo json_encode($chemName, JSON_UNESCAPED_UNICODE); ?>;
    const casNo = <?php echo json_encode($casNumber ?? '', JSON_UNESCAPED_UNICODE); ?>;
    const signalWord = <?php echo json_encode($signalWord ?? '', JSON_UNESCAPED_UNICODE); ?>;

    const params = new URLSearchParams({
        src: modelUrl,
        title: chemName,
        chem_name: chemName,
        cas: casNo,
        signal: signalWord
    });

    // attempt fullscreen before navigating for immersive experience
    if (document.fullscreenElement) {
        window.location.href = '/v1/ar/ar_spatial.php?' + params.toString();
    } else {
        document.documentElement.requestFullscreen().then(() => {
            // small delay so fullscreen state applies
            setTimeout(() => window.location.href = '/v1/ar/ar_spatial.php?' + params.toString(), 200);
        }).catch(() => {
            window.location.href = '/v1/ar/ar_spatial.php?' + params.toString();
        });
    }
}

document.getElementById('btnHeaderARSpatial')?.addEventListener('click', openArSpatial);
document.getElementById('btnARSpatial')?.addEventListener('click', openArSpatial);
<?php endif; ?>

// ═══ openDetail link back to containers page ═══
function openDetail(id) {
    window.location.href = '/v1/pages/containers.php#detail-' + id;
}
</script>
</body>
</html>
