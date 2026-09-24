// FORSA AI Assistant — sidebar chat UI (PRD sections 30-33, 44).
//
// Phase 2 (AI Core): wired to a real LLM via ai_chat_api.php / conversations
// via ai_conversations_api.php. The model still has no tool/data access at
// all (ToolRegistry is empty — see modules/ai/tools/ToolRegistry.php), so it
// can only ever have a plain-text conversation; it cannot answer with real
// FTK/realisasi numbers until Phase 3 (database source gate) and beyond.
window.ForsaAi = window.ForsaAi || {};

window.ForsaAi.AiChat = (function () {
    const SUGGESTED_QUESTIONS = [
        'Berapa total FTK periode terbaru?',
        'SH/AP mana yang memiliki gap terbesar?',
        'Bandingkan FTK dengan realisasi.',
        'Tampilkan distribusi berdasarkan jenjang jabatan.',
    ];

    function create({ root, onClose }) {
        const messagesEl = root.querySelector('#ai-chat-messages');
        const suggestedEl = root.querySelector('#ai-suggested-questions');
        const greetingEl = root.querySelector('#ai-sidebar-greeting');
        const form = root.querySelector('#ai-chat-form');
        const input = root.querySelector('#ai-chat-input');
        const historyList = root.querySelector('#ai-history-list');
        let interactionReady = false;
        let historyPanelOpen = false;
        let currentConversationId = null;
        let state = 'closed'; // closed|opening|open|thinking|tool_running|responding|error|closing

        function setState(next) { state = next; root.dataset.sidebarState = next; }

        function renderSuggested() {
            suggestedEl.innerHTML = `
                <div class="label">Coba tanyakan</div>
                ${SUGGESTED_QUESTIONS.map(q => `<button type="button" class="ai-suggested-q">${q}</button>`).join('')}
            `;
            suggestedEl.querySelectorAll('.ai-suggested-q').forEach(btn => {
                btn.addEventListener('click', () => sendMessage(btn.textContent));
            });
        }

        // Metadata Jawaban (Tahap 4): a compact caption under an assistant
        // bubble — execution time, model, and a short honest source label
        // (never raw SQL, see AiOrchestrator::buildSourceLabel()).
        function addMessageMeta({ executionTimeMs, model, source }) {
            const div = document.createElement('div');
            div.className = 'ai-msg-meta';
            const parts = [];
            if (typeof executionTimeMs === 'number') parts.push(`⏱ ${(executionTimeMs / 1000).toFixed(1)}s`);
            if (model) parts.push(model);
            if (source) parts.push(source);
            div.textContent = parts.join(' · ');
            messagesEl.appendChild(div);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return div;
        }

        function addMessage(role, text) {
            const div = document.createElement('div');
            div.className = 'ai-msg ' + (role === 'user' ? 'ai-msg-user' : 'ai-msg-assistant');
            if (role === 'user') {
                // The user's own raw input — shown as plain text, never
                // interpreted as Markdown (nothing to render, and it avoids
                // ever turning pasted text into unexpected formatting).
                div.textContent = text;
            } else {
                // Assistant replies may contain Markdown (bold, lists, inline
                // code) — render it properly instead of showing raw "**...**"
                // to the user. renderMarkdown() escapes HTML before applying
                // any formatting, so this is safe even if the reply ever
                // contained literal "<" / ">".
                div.innerHTML = window.ForsaAi.renderMarkdown(text);
            }
            messagesEl.appendChild(div);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return div;
        }

        // Progress Time (Tahap 5): a live elapsed-time readout while the
        // model is working ("Menyusun jawaban... 3.2s"), ticking every
        // 100ms via performance.now() (monotonic, unaffected by system clock
        // changes) — replaced by the final server-measured execution time
        // (Tahap 4's .ai-msg-meta) the moment the real response arrives, so
        // the number the user watches count up and the number shown after
        // are two readings of the same thing, not two different clocks.
        function addThinkingBubble() {
            const div = document.createElement('div');
            div.className = 'ai-msg ai-msg-thinking';
            const startedAt = performance.now();
            const render = () => {
                const elapsedS = ((performance.now() - startedAt) / 1000).toFixed(1);
                div.textContent = `Menyusun jawaban... ${elapsedS}s`;
            };
            render();
            const timerId = setInterval(render, 100);
            messagesEl.appendChild(div);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return {
                stop() {
                    clearInterval(timerId);
                    div.remove();
                },
            };
        }

        function addErrorBubble(text) {
            const div = document.createElement('div');
            div.className = 'ai-msg ai-msg-assistant';
            div.style.color = 'var(--red)';
            div.textContent = text;
            messagesEl.appendChild(div);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        async function sendMessage(text) {
            if (!interactionReady) return;
            text = (text || '').trim();
            if (!text) return;
            suggestedEl.innerHTML = '';
            greetingEl.style.display = 'none';
            addMessage('user', text);
            input.value = '';

            setState('thinking');
            const thinking = addThinkingBubble();

            try {
                const res = await fetch('ai_chat_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify({ conversation_id: currentConversationId, message: text }),
                });
                const json = await res.json();
                thinking.stop();

                if (!json.success) {
                    setState('error');
                    addErrorBubble(json.message || 'Terjadi kesalahan.');
                    setState('open');
                    return;
                }

                currentConversationId = json.data.conversation_id;
                setState('responding');
                addMessage('assistant', json.data.reply);
                addMessageMeta({
                    executionTimeMs: json.data.execution_time_ms,
                    model: json.data.model,
                    source: json.data.source,
                });
                setState('open');
            } catch (err) {
                thinking.stop();
                setState('error');
                addErrorBubble('Tidak dapat menghubungi FORSA AI Assistant. Periksa koneksi Anda.');
                setState('open');
            }
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            sendMessage(input.value);
        });

        root.querySelector('#btn-ai-new-chat').addEventListener('click', () => {
            currentConversationId = null;
            messagesEl.innerHTML = '';
            greetingEl.style.display = '';
            renderSuggested();
            input.focus();
        });

        async function loadConversation(id) {
            const res = await fetch('ai_conversations_api.php?id=' + encodeURIComponent(id));
            const json = await res.json();
            if (!json.success) return;

            currentConversationId = json.data.conversation.id;
            messagesEl.innerHTML = '';
            greetingEl.style.display = 'none';
            suggestedEl.innerHTML = '';
            json.data.messages
                .filter(m => m.role === 'user' || m.role === 'assistant')
                .forEach(m => addMessage(m.role, m.content));
            historyList.hidden = true;
            historyPanelOpen = false;
        }

        async function renderHistory() {
            historyList.innerHTML = '<div class="ai-history-empty">Memuat…</div>';
            const res = await fetch('ai_conversations_api.php');
            const json = await res.json();
            const rows = json.success ? json.data.conversations : [];

            if (!rows.length) {
                historyList.innerHTML = '<div class="ai-history-empty">Belum ada percakapan tersimpan.</div>';
                return;
            }

            historyList.innerHTML = rows.map(c => `
                <button type="button" class="ai-suggested-q" data-conversation-id="${c.id}">${c.title || '(tanpa judul)'}</button>
            `).join('');
            historyList.querySelectorAll('[data-conversation-id]').forEach(btn => {
                btn.addEventListener('click', () => loadConversation(btn.dataset.conversationId));
            });
        }

        root.querySelector('#btn-ai-history').addEventListener('click', () => {
            historyPanelOpen = !historyPanelOpen;
            historyList.hidden = !historyPanelOpen;
            if (historyPanelOpen) renderHistory();
        });

        // Expand/collapse (header icon): normal = existing sidebar width,
        // expanded = ~50% of screen width (CSS .ai-sidebar.expanded).
        // MascotController reserves matching dashboard space on the panel side.
        const EXPAND_ICON = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 2H2v4M10 14h4v-4M2 2l4.5 4.5M14 14L9.5 9.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        const COLLAPSE_ICON = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2 6h4V2M14 10h-4v4M6 6L1.5 1.5M10 10l4.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        const expandBtn = root.querySelector('#btn-ai-expand');
        if (expandBtn) {
            expandBtn.addEventListener('click', () => {
                const expanded = root.classList.toggle('expanded');
                expandBtn.setAttribute('aria-pressed', expanded ? 'true' : 'false');
                expandBtn.setAttribute('aria-label', expanded ? 'Perkecil lebar sidebar' : 'Perbesar lebar sidebar');
                expandBtn.innerHTML = expanded ? COLLAPSE_ICON : EXPAND_ICON;
            });
        }

        root.querySelector('#btn-ai-close').addEventListener('click', () => onClose());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && root.classList.contains('open')) onClose();
        });

        function prepareOpen() {
            interactionReady = false;
            root.inert = true;
            setState('opening');
            root.classList.add('open');
            root.setAttribute('aria-hidden', 'false');
            if (!messagesEl.children.length) renderSuggested();
        }

        function activate() {
            interactionReady = true;
            root.inert = false;
            setState('open');
            input.focus();
        }

        function close() {
            interactionReady = false;
            root.inert = true;
            setState('closing');
            root.classList.remove('open');
            root.setAttribute('aria-hidden', 'true');
            setState('closed');
        }

        return { prepareOpen, activate, close };
    }

    return { create };
})();
