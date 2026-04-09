<style>
    #dono-widget .dono-fab {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        padding: 0;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        color: #f8fafc;
        background: linear-gradient(145deg, #3b82f6 0%, #1d4ed8 55%, #1e40af 100%);
        box-shadow: 0 8px 22px rgba(37, 99, 235, 0.45), 0 2px 6px rgba(0, 0, 0, 0.15);
        transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.35s ease, filter 0.35s ease;
    }
    #dono-widget .dono-fab:hover {
        transform: translateY(-4px) scale(1.06);
        box-shadow: 0 14px 32px rgba(37, 99, 235, 0.55), 0 6px 14px rgba(0, 0, 0, 0.18);
        filter: brightness(1.08) saturate(1.05);
    }
    #dono-widget .dono-fab:active {
        transform: translateY(-1px) scale(1.02);
    }
    #dono-widget .dono-fab::after {
        content: "";
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        border: 2px solid rgba(96, 165, 250, 0.55);
        opacity: 0;
        transform: scale(0.92);
        transition: opacity 0.35s ease, transform 0.35s ease;
        pointer-events: none;
    }
    #dono-widget .dono-fab:hover::after {
        opacity: 1;
        transform: scale(1.08);
        animation: dono-ring-pulse 1.8s ease-in-out infinite;
    }
    @keyframes dono-ring-pulse {
        0%, 100% { opacity: 0.65; transform: scale(1.05); }
        50% { opacity: 1; transform: scale(1.12); }
    }
    #dono-widget .dono-robot-icon {
        width: 30px;
        height: 30px;
        display: block;
        transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    #dono-widget .dono-fab:hover .dono-robot-icon {
        transform: translateY(-1px) rotate(-4deg);
    }
    #dono-widget .dono-antenna {
        transform-box: fill-box;
        transform-origin: 50% 100%;
        transition: transform 0.3s ease;
    }
    #dono-widget .dono-fab:hover .dono-antenna {
        animation: dono-antenna-wiggle 0.65s ease-in-out;
    }
    @keyframes dono-antenna-wiggle {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(-14deg); }
        50% { transform: rotate(10deg); }
        75% { transform: rotate(-6deg); }
    }
    #dono-widget .dono-eye {
        transform-origin: center;
        transition: transform 0.15s ease;
    }
    #dono-widget .dono-fab:hover .dono-eye {
        animation: dono-blink 0.5s ease 0.15s;
    }
    @keyframes dono-blink {
        0%, 100% { transform: scaleY(1); }
        45% { transform: scaleY(0.12); }
        55% { transform: scaleY(0.12); }
    }
</style>
<div id="dono-widget" style="position:fixed;right:16px;bottom:16px;z-index:2147483000;font-family:Inter,Arial,sans-serif;color-scheme:light;color:#111827;">
    <button id="dono-toggle" class="dono-fab" type="button" title="Dono Assistant" aria-label="Buka asisten Dono">
        <svg class="dono-robot-icon" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <g class="dono-antenna">
                <path d="M16 3.5c0-1.1.9-2 2-2s2 .9 2 2v2.2a3.2 3.2 0 01-4 0V3.5z" fill="currentColor" opacity=".95"/>
                <circle cx="18" cy="2" r="1.6" fill="currentColor"/>
            </g>
            <rect x="7" y="9" width="18" height="16" rx="5" fill="currentColor"/>
            <rect x="10" y="14" width="4.5" height="5" rx="1.2" fill="#1e3a5f" class="dono-eye"/>
            <rect x="17.5" y="14" width="4.5" height="5" rx="1.2" fill="#1e3a5f" class="dono-eye"/>
            <path d="M12 23.5c1.2 1.6 3.3 2.5 4 2.5s2.8-.9 4-2.5" stroke="#1e3a5f" stroke-width="1.6" stroke-linecap="round"/>
            <rect x="5" y="17" width="3" height="5" rx="1.5" fill="currentColor" opacity=".85"/>
            <rect x="24" y="17" width="3" height="5" rx="1.5" fill="currentColor" opacity=".85"/>
        </svg>
    </button>
    <div id="dono-panel" style="display:none;width:360px;max-width:calc(100vw - 24px);margin-top:10px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 20px 40px rgba(0,0,0,.25);overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid #e5e7eb;">
            <strong style="font-size:14px;color:#111827;">Dono Assistant</strong>
            <button id="dono-close" type="button" style="border:none;background:transparent;color:#6b7280;font-size:16px;cursor:pointer;">x</button>
        </div>
        <div id="dono-messages" style="height:280px;overflow-y:auto;padding:10px;background:#fff;"></div>
        <div id="dono-action" style="padding:0 10px 10px 10px;"></div>
        <div style="display:flex;gap:8px;padding:10px;border-top:1px solid #e5e7eb;">
            <input id="dono-input" type="text" placeholder="Ketik perintah..." style="flex:1;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;background:#fff;color:#111827;caret-color:#111827;" />
            <button id="dono-send" type="button" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:8px 12px;font-size:13px;cursor:pointer;">Kirim</button>
        </div>
    </div>
</div>
<script>
(() => {
    if (window.__donoMounted) return;
    window.__donoMounted = true;

    const panelEl = document.getElementById('dono-panel');
    const toggle = document.getElementById('dono-toggle');
    const closeBtn = document.getElementById('dono-close');
    const messages = document.getElementById('dono-messages');
    const actionEl = document.getElementById('dono-action');
    const input = document.getElementById('dono-input');
    const sendBtn = document.getElementById('dono-send');
    let loading = false;

    const addMessage = (role, text) => {
        const wrap = document.createElement('div');
        wrap.style.textAlign = role === 'user' ? 'right' : 'left';
        wrap.style.marginBottom = '8px';
        const bubble = document.createElement('span');
        bubble.textContent = text;
        bubble.style.display = 'inline-block';
        bubble.style.padding = '8px 10px';
        bubble.style.borderRadius = '10px';
        bubble.style.fontSize = '13px';
        bubble.style.lineHeight = '1.35';
        bubble.style.whiteSpace = 'pre-wrap';
        bubble.style.maxWidth = '90%';
        bubble.style.background = role === 'user' ? '#2563eb' : '#f3f4f6';
        bubble.style.color = role === 'user' ? '#fff' : '#111827';
        wrap.appendChild(bubble);
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
    };

    const setAction = (action) => {
        actionEl.innerHTML = '';
        if (!action || !action.url) return;
        const a = document.createElement('a');
        a.href = action.url;
        a.textContent = action.label || 'Buka Form';
        a.style.display = 'inline-block';
        a.style.background = '#059669';
        a.style.color = '#fff';
        a.style.padding = '8px 10px';
        a.style.borderRadius = '8px';
        a.style.fontSize = '13px';
        a.style.textDecoration = 'none';
        actionEl.appendChild(a);
    };

    const send = async () => {
        const text = (input.value || '').trim();
        if (!text || loading) return;
        loading = true;
        input.value = '';
        addMessage('user', text);
        try {
            const res = await fetch('/dono/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    message: text,
                    panel: window.location.pathname.startsWith('/employee') ? 'employee' : 'admin',
                }),
            });
            const data = await res.json();
            addMessage('assistant', data.reply || 'Siap.');
            setAction(data.action || null);
        } catch (e) {
            addMessage('assistant', 'Maaf, Dono sedang error. Coba lagi.');
        } finally {
            loading = false;
        }
    };

    toggle.addEventListener('click', () => {
        panelEl.style.display = panelEl.style.display === 'none' ? 'block' : 'none';
    });
    closeBtn.addEventListener('click', () => { panelEl.style.display = 'none'; });
    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            send();
        }
    });

    addMessage('assistant', 'Halo, saya Dono. Tanya cara pakai menu di DigiGate, minta buka halaman tertentu, atau buat task / buka form (sesuai akses Anda).');
})();
</script>

