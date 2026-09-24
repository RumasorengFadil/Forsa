// Original FORSA data steward: lit, articulated 3D ellipsoid meshes projected
// onto Canvas2D. No external model, texture, CDN or GPU dependency.
window.ForsaAi = window.ForsaAi || {};
window.ForsaAi.MascotRenderer = {
    create(canvas) {
        const ctx = canvas.getContext('2d');
        const blue = [28, 100, 209], white = [235, 244, 255], ink = [22, 35, 61];
        const mesh = [];
        const point = (a, b) => [Math.sin(a) * Math.cos(b), Math.cos(a), Math.sin(a) * Math.sin(b)];
        for (let i = 0; i < 10; i++) for (let j = 0; j < 16; j++) {
            mesh.push([point(i*Math.PI/10,j*Math.PI/8), point((i+1)*Math.PI/10,j*Math.PI/8),
                point((i+1)*Math.PI/10,(j+1)*Math.PI/8), point(i*Math.PI/10,(j+1)*Math.PI/8)]);
        }
        function draw(t, pose = 'idle', progress = 0, reduced = false, direction = 1) {
            if (!ctx) return;
            const ratio = Math.min(window.devicePixelRatio || 1, 2);
            const size = Math.max(1, canvas.clientWidth);
            if (canvas.width !== Math.round(size*ratio)) {
                canvas.width = Math.round(size*ratio); canvas.height = Math.round(size*ratio*1.2);
            }
            ctx.setTransform(canvas.width/120, 0, 0, canvas.height/144, 0, 0);
            ctx.clearRect(0, 0, 120, 144);
            const idle = pose === 'idle' || pose === 'sidebar_guide';
            const time = reduced ? 0 : t / 1000;
            const walking = pose === 'walking' && !reduced;
            const stride = walking ? Math.sin(time*10) : 0;
            const yaw = walking ? direction * Math.PI/2 : idle ? Math.sin(time*.7)*.18 : -.16;
            const bounce = walking ? Math.abs(stride)*2 : idle ? Math.sin(time*1.7)*1.2 + Math.pow(Math.max(0, Math.sin(time*.9)), 18)*2 : 0;
            const head = idle ? Math.sin(time*.8)*1.5 : pose === 'reacting' ? -4*Math.sin(progress*Math.PI) : 0;
            const blink = !reduced && idle && time%4.7 > 4.52 ? .12 : 1;
            const climbing = pose === 'climbing';
            const reaching = climbing || pose === 'grabbing' || pose === 'pulling_sidebar';
            const faces = [];
            function part(x,y,z,rx,ry,rz,color) {
                for (const polygon of mesh) {
                    const verts = polygon.map(([a,b,c]) => {
                        const px = x+a*rx, pz = z+c*rz;
                        return [px*Math.cos(yaw)+pz*Math.sin(yaw), y+b*ry-bounce, pz*Math.cos(yaw)-px*Math.sin(yaw)];
                    });
                    const n = polygon.reduce((v,p) => v.map((x,i) => x+p[i]/4), [0,0,0]);
                    const light = .63 + .32*Math.max(0, -n[0]*.4-n[1]*.6+n[2]*.7);
                    faces.push({ verts, z: verts.reduce((s,p)=>s+p[2],0)/4, color: `rgb(${color.map(c=>Math.round(c*light)).join(',')})` });
                }
            }
            part(0,92,0,23,27+(idle?Math.sin(time*2)*.6:0),17,blue);
            part(-13,120-Math.max(0,stride)*5,4+stride*12,10,7,13,ink);
            part(13,120-Math.max(0,-stride)*5,4-stride*12,10,7,13,ink);
            const hand = reaching ? 38 : 88 + Math.sin(time*1.4)*2;
            part(-31,hand,4-stride*10,8,12,8,white); part(31,hand,4+stride*10,8,12,8,white);
            part(0,49+head,2,32,25,22,white);
            part(0,50+head,19,25,15,7,ink);
            part(-10,48+head,26,4,5*blink,2,white); part(10,48+head,26,4,5*blink,2,white);
            part(0,59+head,26,7,1.3,1,blue);
            // Asymmetric data fin and three-column workforce core.
            part(-20,24+head,1,4,12,5,blue); part(-20,14+head,1,5,3,5,white);
            part(0,91,17,14,14,3,white);
            for(let i=0;i<3;i++) part((i-1)*7,94-i*3,21,2,4+i*3,1.5,blue);
            faces.sort((a,b)=>a.z-b.z).forEach(({verts,color}) => {
                ctx.beginPath(); verts.forEach(([x,y,z],i) => {
                    const perspective = 360/(360-z);
                    const px=60+x*perspective, py=72+(y-72)*perspective;
                    if(i===0) ctx.moveTo(px,py); else ctx.lineTo(px,py);
                }); ctx.closePath(); ctx.fillStyle=color; ctx.fill();
            });
        }
        return { draw };
    }
};
