// PRD 25–29: one cancellable timeline owns pose, position, drawer and greeting.
window.ForsaAi = window.ForsaAi || {};
window.ForsaAi.MascotController = {
    create({ mascotEl, greetingEl, sidebarEl, onPrepareSidebar, onActivateSidebar, onCloseSidebar, appShellEl }) {
        const renderer = window.ForsaAi.MascotRenderer.create(mascotEl.querySelector('canvas'));
        const greeting = window.ForsaAi.MascotGreeting.create(
            greetingEl, window.ForsaAiConfig?.mascotGreetingIntervalMs
        );
        const configuredWalkMs = window.ForsaAiConfig?.mascotWalkDurationMs;
        const walkDurationMs = Number.isFinite(configuredWalkMs) && configuredWalkMs > 0 ? configuredWalkMs : 6000;
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        let state = 'hidden', elapsed = 0, clock = 0, previous = null, frame = null;
        let enabled = false, desiredHidden = true;
        // Normalized floor position survives viewport resizing (0 right, 1 left).
        let position = 0, walkFrom = 0, walkTo = 0, walkDirection = 1;
        function floorSpan() {
            return Math.max(0, window.innerWidth - 2*parseFloat(getComputedStyle(mascotEl).right) - mascotEl.offsetWidth);
        }
        function walkToSide(target) {
            if (!['idle', 'walking'].includes(state)) return;
            if (Math.abs(target-position) < .001) {
                if (state === 'walking') setState('idle');
                return;
            }
            walkFrom = position; walkTo = target;
            walkDirection = target > position ? -1 : 1;
            durations.walking = Math.max(1, Math.abs(target-position)*walkDurationMs);
            setState('walking');
        }
        function requestHidden(hidden) {
            desiredHidden = hidden;
            if (hidden && ['idle','walking'].includes(state)) setState('hiding');
            if (!hidden && state === 'hidden') setState('climbing');
        }
        let lastDraw = -Infinity;
        let panelSide = 'right';
        function syncPanelLayout() {
            if (!appShellEl) return;
            const bounds = sidebarEl.getBoundingClientRect();
            const visible = Math.max(0, Math.min(bounds.width,
                panelSide === 'left' ? bounds.right : window.innerWidth-bounds.left));
            const mobile = window.innerWidth <= 640;
            appShellEl.style.marginLeft = !mobile && panelSide === 'left' ? `${visible}px` : '0px';
            appShellEl.style.marginRight = !mobile && panelSide === 'right' ? `${visible}px` : '0px';
            // Full-screen mobile panel replaces the dashboard until it closes.
            appShellEl.inert = mobile && visible > 1;
            appShellEl.style.visibility = mobile && visible > 1 ? 'hidden' : '';
        }
        const durations = { hiding: 700, climbing: 1000, reacting: 300, grabbing: 350,
            pulling_sidebar: 750, sidebar_locked: 160, jumping_to_sidebar: 650 };
        const opening = () => ['reacting','grabbing','pulling_sidebar','sidebar_locked','jumping_to_sidebar'].includes(state);
        function setState(next) {
            state = next; elapsed = 0;
            mascotEl.dataset.state = next;
            mascotEl.dataset.scroll = ['hiding','hidden','climbing'].includes(next) ? next : 'idle';
            mascotEl.tabIndex = next === 'hidden' || next === 'sidebar_guide' ? -1 : 0;
            mascotEl.setAttribute('aria-expanded', opening() || next === 'sidebar_guide' ? 'true' : 'false');
        }
        const ease = p => p*p*(3-2*p);
        const mix = (a,b,p) => a+(b-a)*p;
        function render(progress) {
            const width = mascotEl.offsetWidth, height = mascotEl.offsetHeight;
            const style = getComputedStyle(mascotEl);
            const baseX = window.innerWidth-parseFloat(style.right)-width;
            const baseY = window.innerHeight-parseFloat(style.bottom)-height;
            const below = window.innerHeight-baseY+4;
            const panelWidth = sidebarEl.getBoundingClientRect().width;
            // The near hand tracks the panel edge on either side.
            const leftPanel = panelSide === 'left';
            const slideDirection = leftPanel ? -1 : 1;
            const grabX = leftPanel ? -baseX-width*.22 : window.innerWidth-baseX-width*.78;
            const pullX = grabX-slideDirection*panelWidth;
            const slot = sidebarEl.querySelector('.ai-sidebar-mascot-mini').getBoundingClientRect();
            const dockScale = slot.width/width;
            const dockX = slot.left-baseX-(width-slot.width)/2;
            const dockY = slot.top-baseY-(height-height*dockScale)/2;
            if (state === 'walking') position = mix(walkFrom,walkTo,ease(progress));
            const floorX = -position*floorSpan();
            let x=floorX,y=0,scale=1;
            mascotEl.dataset.side = position > .5 ? 'left' : 'right';
            if(state==='hiding') {
                if(progress<.2) { y=8*ease(progress/.2); scale=1-.06*Math.sin(progress/.2*Math.PI/2); }
                else if(progress<.45) y=mix(8,-24,ease((progress-.2)/.25));
                else y=mix(-24,below,ease((progress-.45)/.55));
            } else if(state==='hidden') y=below;
            else if(state==='climbing') {
                // Raised hands lead; pause with hands at the viewport lip,
                // then head and torso rise in separate beats.
                const lip = below-height*.34;
                if(progress<.25) y=mix(below,lip,ease(progress/.25));
                else if(progress<.4) y=lip;
                else if(progress<.72) y=mix(lip,height*.3,ease((progress-.4)/.32));
                else y=mix(height*.3,0,ease((progress-.72)/.28));
            } else if(state==='reacting') scale=1+.08*Math.sin(progress*Math.PI);
            else if(state==='grabbing') x=mix(floorX,grabX,ease(progress));
            else if(state==='pulling_sidebar') {
                x=mix(grabX,pullX,ease(progress));
                sidebarEl.style.transform=`translateX(${slideDirection*(1-ease(progress))*panelWidth}px)`;
            } else if(state==='sidebar_locked') x=pullX;
            else if(state==='jumping_to_sidebar') {
                x=mix(pullX,dockX,ease(progress)); y=mix(0,dockY,ease(progress))-60*Math.sin(progress*Math.PI);
                scale=mix(1,dockScale,ease(progress));
            } else if(state==='sidebar_guide') { x=dockX; y=dockY; scale=dockScale; }
            syncPanelLayout();
            mascotEl.style.transform=`translate(${x}px, ${y}px) scale(${scale})`;
            mascotEl.style.visibility=state==='hidden'?'hidden':'visible';
            if (state !== 'hidden' && clock-lastDraw >= 1000/30) {
                renderer.draw(clock,state,progress,reduced.matches,walkDirection);
                lastDraw = clock;
            }
        }
        function advance() {
            if(state==='walking') { position=walkTo; setState('idle'); }
            else if(state==='hiding') setState(desiredHidden?'hidden':'climbing');
            else if(state==='climbing') setState(desiredHidden?'hiding':'idle');
            else if(state==='reacting') setState('grabbing');
            else if(state==='grabbing') {
                onPrepareSidebar(); sidebarEl.style.transition='none';
                sidebarEl.style.transform=panelSide === 'left' ? 'translateX(-100%)' : 'translateX(100%)'; setState('pulling_sidebar');
            } else if(state==='pulling_sidebar') { sidebarEl.style.transform='translateX(0)'; setState('sidebar_locked'); }
            else if(state==='sidebar_locked') setState('jumping_to_sidebar');
            else if(state==='jumping_to_sidebar') {
                setState('sidebar_guide'); sidebarEl.style.transition=''; sidebarEl.style.transform=''; onActivateSidebar();
            }
        }
        function tick(now) {
            const delta=previous===null?0:now-previous;
            previous=now; clock+=delta; elapsed+=delta;
            const duration=reduced.matches?0:(durations[state] || 0);
            if (reduced.matches) {
                while (durations[state]) advance();
            } else if (durations[state] && elapsed >= duration) advance();
            render(durations[state] ? Math.min(1,elapsed/(reduced.matches?1:durations[state])) : 0);
            greeting.tick(enabled ? delta : 0,state==='idle');
            frame=requestAnimationFrame(tick);
        }
        function visibility() {
            if(frame!==null) cancelAnimationFrame(frame);
            frame=null; previous=null;
            if(!document.hidden) frame=requestAnimationFrame(tick);
        }
        const scroll=window.ForsaAi.MascotScroll.create(() => {
            if (!enabled || opening() || state==='sidebar_guide') return;
            requestHidden(true);
        }, () => {
            if (!enabled || opening() || state==='sidebar_guide') return;
            requestHidden(false);
        });
        scroll.start();
        mascotEl.addEventListener('click', () => {
            if(state!=='idle') return;
            panelSide = position > .5 ? 'left' : 'right';
            // Re-anchor offscreen without sliding the closed panel across the page.
            sidebarEl.style.transition = 'none';
            sidebarEl.dataset.side = panelSide;
            sidebarEl.style.transform = panelSide === 'left' ? 'translateX(-100%)' : 'translateX(100%)';
            sidebarEl.getBoundingClientRect();
            sidebarEl.style.transition = '';
            desiredHidden=false; setState('reacting');
        });
        // Native button already supports Enter and Space without duplicate handlers.
        document.addEventListener('visibilitychange',visibility);
        function typingOrInteractive(event) {
            // composedPath also protects editors inside shadow DOM.
            const nodes = [...(event.composedPath?.() || [event.target]), document.activeElement];
            return nodes.some(node => node && (node.isContentEditable || node.closest?.(
                'input, textarea, select, [contenteditable]:not([contenteditable="false"]), [role="textbox"], [role="combobox"], [role="searchbox"], [role="application"], .monaco-editor, .cm-editor, .CodeMirror, .ace_editor, button:not(#ai-mascot), a[href], [role="slider"], [role="spinbutton"]'
            )));
        }
        document.addEventListener('keydown', event => {
            if (event.defaultPrevented || event.repeat || event.isComposing || event.keyCode === 229 || typingOrInteractive(event)) return;
            const key = event.key.toLowerCase();
            const toggle = key === 'k' && event.shiftKey && !event.altKey && (event.ctrlKey !== event.metaKey);
            if (toggle) {
                event.preventDefault();
                enabled = !enabled;
                if (!enabled && (opening() || state === 'sidebar_guide')) {
                    onCloseSidebar?.();
                    sidebarEl.style.transition=''; sidebarEl.style.transform='';
                    setState('idle');
                }
                requestHidden(!enabled);
                return;
            }
            if (!enabled || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey || opening() || state==='sidebar_guide') return;
            if (!['w','s','a','d'].includes(key)) return;
            if ((key==='a' || key==='d') && !['idle','walking'].includes(state)) return;
            event.preventDefault();
            if (key==='w' || key==='s') requestHidden(key==='s');
            else walkToSide(key==='a' ? 1 : 0);
        });
        setState('hidden'); render(0); greeting.tick(0,false); visibility();
        return {
            notifySidebarClosed() {
                sidebarEl.style.transition=''; sidebarEl.style.transform='';
                desiredHidden=!enabled; setState(enabled?'idle':'hidden'); render(0);
                if (enabled) mascotEl.focus();
            }
        };
    }
};
