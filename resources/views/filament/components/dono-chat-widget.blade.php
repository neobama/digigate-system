<div
    x-data="{
        open: false,
        loading: false,
        message: '',
        messages: [{ role: 'assistant', text: 'Halo, saya Dono. Saya bisa bantu isi cepat form fitur apa saja.' }],
        action: null,
        async send() {
            if (!this.message.trim() || this.loading) return
            const userMessage = this.message.trim()
            this.messages.push({ role: 'user', text: userMessage })
            this.message = ''
            this.loading = true
            try {
                const res = await fetch('/dono/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? '',
                    },
                    body: JSON.stringify({ message: userMessage }),
                })
                const data = await res.json()
                this.messages.push({ role: 'assistant', text: data.reply ?? 'Siap.' })
                this.action = data.action ?? null
            } catch (e) {
                this.messages.push({ role: 'assistant', text: 'Maaf, Dono sedang error. Coba lagi.' })
            } finally {
                this.loading = false
            }
        }
    }"
    class="fixed bottom-5 right-5 z-50"
>
    <button
        x-show="!open"
        @click="open = true"
        class="rounded-full bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg"
    >
        Dono
    </button>

    <div x-show="open" x-transition class="w-96 rounded-xl border border-gray-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b px-4 py-3">
            <h3 class="font-semibold">Dono Assistant</h3>
            <button @click="open = false" class="text-gray-500">x</button>
        </div>
        <div class="h-80 space-y-2 overflow-y-auto p-3 text-sm">
            <template x-for="(m, i) in messages" :key="i">
                <div :class="m.role === 'user' ? 'text-right' : 'text-left'">
                    <span
                        class="inline-block rounded-lg px-3 py-2"
                        :class="m.role === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800'"
                        x-text="m.text"
                    ></span>
                </div>
            </template>
            <template x-if="action">
                <div class="pt-2">
                    <a :href="action.url" class="inline-block rounded bg-emerald-600 px-3 py-2 text-white" x-text="action.label"></a>
                </div>
            </template>
        </div>
        <div class="border-t p-3">
            <div class="flex gap-2">
                <input
                    x-model="message"
                    @keydown.enter.prevent="send()"
                    type="text"
                    placeholder="Ketik perintah..."
                    class="flex-1 rounded border px-3 py-2 text-sm"
                />
                <button @click="send()" :disabled="loading" class="rounded bg-blue-600 px-3 py-2 text-white">
                    Kirim
                </button>
            </div>
        </div>
    </div>
</div>

