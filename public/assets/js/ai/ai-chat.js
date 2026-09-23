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

        function addMessage(role, text) {
            const div = document.createElement('div');
            div.className = 'ai-msg ' + (role === 'user' ? 'ai-msg-user' : 'ai-msg-assistant');
            div.textContent = text;
            messagesEl.appendChild(div);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return div;
        }

        function addThinkingBubble() {
            const div = document.createElement('div');
            div.className = 'ai-msg ai-msg-thinking';
            div.innerHTML = 'Memahami pertanyaan<span class="dots"><span style="--i:0">.</span><span style="--i:1">.</span><span style="--i:2">.</span></span>';
            messagesEl.appendChild(div);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return div;
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
                thinking.remove();

                if (!json.success) {
                    setState('error');
                    addErrorBubble(json.message || 'Terjadi kesalahan.');
                    setState('open');
                    return;
                }

                currentConversationId = json.data.conversation_id;
                setState('responding');
                addMessage('assistant', json.data.reply);
                setState('open');
            } catch (err) {
                thinking.remove();
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

        root.querySelector('#btn-ai-close').addEventListener('click', () => onClose());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && root.classList.contains('open')) onClose();
        });

        function open() {
            setState('opening');
            root.classList.add('open');
            root.setAttribute('aria-hidden', 'false');
            if (!messagesEl.children.length) renderSuggested();
            setState('open');
            setTimeout(() => input.focus(), 350);
        }

        function close() {
            setState('closing');
            root.classList.remove('open');
            root.setAttribute('aria-hidden', 'true');
            setState('closed');
        }

        return { open, close };
    }

    return { create };
})();
