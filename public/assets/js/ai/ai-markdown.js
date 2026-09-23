// FORSA AI Assistant — minimal Markdown-subset renderer for assistant chat
// bubbles. Not a full CommonMark implementation: only the constructs the
// model's own answers actually use (bold, italic, inline code, bullet
// lists, paragraphs/line breaks) — enough to turn
// "- **PLN NP**: Gap sebesar -950" into a real bold list item instead of
// showing raw asterisks/dashes to the user.
//
// Security: HTML-escapes the raw text FIRST, then applies formatting on
// top of the escaped string — a reply that happened to contain "<script>"
// renders as literal text, never as markup (defense against a model output
// or upstream data ever containing HTML).
window.ForsaAi = window.ForsaAi || {};

window.ForsaAi.renderMarkdown = function renderMarkdown(text) {
    function escapeHtml(s) {
        return s
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function inlineFormat(raw) {
        let s = escapeHtml(raw);
        s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/(^|[^*])\*([^*\s][^*]*)\*(?!\*)/g, '$1<em>$2</em>');
        return s;
    }

    const lines = String(text ?? '').split('\n');
    let html = '';
    let inList = false;

    const closeList = () => {
        if (inList) {
            html += '</ul>';
            inList = false;
        }
    };

    for (const line of lines) {
        const listItem = line.match(/^\s*[-*]\s+(.+)$/);
        if (listItem) {
            if (!inList) {
                html += '<ul>';
                inList = true;
            }
            html += '<li>' + inlineFormat(listItem[1]) + '</li>';
            continue;
        }

        closeList();
        if (line.trim() === '') continue;
        html += '<p>' + inlineFormat(line) + '</p>';
    }
    closeList();

    return html || escapeHtml(String(text ?? ''));
};
