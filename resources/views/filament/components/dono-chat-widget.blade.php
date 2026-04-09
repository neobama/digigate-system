<div id="dono-widget" style="position:fixed;right:16px;bottom:16px;z-index:2147483000;font-family:Inter,Arial,sans-serif;">
    <button id="dono-toggle" type="button" style="background:#2563eb;color:#fff;border:none;border-radius:9999px;padding:10px 16px;font-size:14px;font-weight:600;box-shadow:0 8px 20px rgba(0,0,0,.28);cursor:pointer;">
        Dono
    </button>
    <div id="dono-panel" style="display:none;width:360px;max-width:calc(100vw - 24px);margin-top:10px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 20px 40px rgba(0,0,0,.25);overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid #e5e7eb;">
            <strong style="font-size:14px;color:#111827;">Dono Assistant</strong>
            <button id="dono-close" type="button" style="border:none;background:transparent;color:#6b7280;font-size:16px;cursor:pointer;">x</button>
        </div>
        <div id="dono-messages" style="height:280px;overflow-y:auto;padding:10px;background:#fff;"></div>
        <div id="dono-action" style="padding:0 10px 10px 10px;"></div>
        <div style="display:flex;gap:8px;padding:10px;border-top:1px solid #e5e7eb;">
            <input id="dono-input" type="text" placeholder="Ketik perintah..." style="flex:1;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;" />
            <button id="dono-send" type="button" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:8px 12px;font-size:13px;cursor:pointer;">Kirim</button>
        </div>
    </div>
</div>
<script>
(() => {
    if (window.__donoMounted) return;
    window.__donoMounted = true;

    const panel = document.getElementById('dono-panel');
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
                body: JSON.stringify({ message: text }),
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
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    });
    closeBtn.addEventListener('click', () => { panel.style.display = 'none'; });
    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            send();
        }
    });

    addMessage('assistant', 'Halo, saya Dono. Saya bisa bantu isi cepat form fitur apa saja.');
})();
</script>

