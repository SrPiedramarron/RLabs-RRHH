<x-filament-panels::page>
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
        <div>
            <label for="fecha_corte" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Fecha de corte</label>
            <input type="date" id="fecha_corte" name="fecha_corte" value="{{ $fechaCorte }}" style="font:inherit;border-radius:8px;padding:6px 10px;border:1px solid #d1d5db;">
        </div>
        <x-filament::button type="submit">Aplicar</x-filament::button>
    </form>

    <div id="vac-dash">
        <header><div><h1>Control de vacaciones por periodo</h1><p id="sub"></p></div></header>
        <nav id="nav" role="tablist"></nav>
        <main>
            <section id="s0"></section><section id="s1"></section><section id="s2"></section><section id="s3"></section><section id="s4"></section>
        </main>
        <div id="vd-tip"></div>
    </div>

    <style>
        #vac-dash{--vd-bg:transparent;--vd-surface:#fcfcfb;--vd-ink:#0b0b0b;--vd-ink2:#52514e;--vd-muted:#8a8984;--vd-line:#e4e3de;--vd-blue:#2a78d6;--vd-aqua:#1baf7a;--vd-red:#e34948;--vd-orange:#eb6834;--vd-heat:42,120,214;color:var(--vd-ink);font:14px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif}
        @media(prefers-color-scheme:dark){#vac-dash{--vd-surface:#1a1a19;--vd-ink:#fff;--vd-ink2:#c3c2b7;--vd-muted:#8b8a82;--vd-line:#2e2e2b;--vd-blue:#3987e5;--vd-aqua:#199e70;--vd-red:#e66767;--vd-orange:#d95926;--vd-heat:57,135,229}}
        #vac-dash *{box-sizing:border-box}
        #vac-dash header{padding:0 0 12px;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
        #vac-dash h1{margin:0;font-size:20px}#vac-dash header p{margin:2px 0 0;color:var(--vd-ink2)}
        #vac-dash nav{margin:0 0 0;padding:0;display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid var(--vd-line)}
        #vac-dash nav button{font:inherit;border:0;border-bottom:2px solid transparent;border-radius:8px 8px 0 0;background:none;padding:10px 14px;color:var(--vd-ink2);cursor:pointer}
        #vac-dash nav button[aria-selected=true]{color:var(--vd-ink);border-bottom-color:var(--vd-blue);font-weight:600}
        #vac-dash main{padding:16px 0}
        #vac-dash section{display:none}#vac-dash section.on{display:block}
        #vac-dash .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px}
        #vac-dash .kpi{background:var(--vd-surface);border:1px solid var(--vd-line);border-radius:12px;padding:12px 14px}
        #vac-dash .kpi small{color:var(--vd-ink2);display:block}#vac-dash .kpi b{font-size:26px;font-weight:650;letter-spacing:-.5px}#vac-dash .kpi i{font-style:normal;color:var(--vd-muted);font-size:12px;display:block}
        #vac-dash .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:14px;margin-bottom:14px}
        @media(max-width:480px){#vac-dash .grid{grid-template-columns:1fr}}
        #vac-dash .card{background:var(--vd-surface);border:1px solid var(--vd-line);border-radius:12px;padding:14px;min-width:0}
        #vac-dash .card h3{margin:0 0 2px;font-size:15px}#vac-dash .card p.s{margin:0 0 10px;color:var(--vd-ink2);font-size:12.5px}
        #vac-dash .legend{display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--vd-ink2);margin-bottom:8px}
        #vac-dash .legend span:before{content:"";display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:5px;background:var(--c)}
        #vac-dash .bar{display:grid;grid-template-columns:minmax(90px,200px) 1fr 44px;gap:8px;align-items:center;margin:4px 0;font-size:12.5px}
        #vac-dash .bar .n{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--vd-ink2)}
        #vac-dash .bar .t{display:flex;height:16px;gap:2px}#vac-dash .bar .t div{border-radius:0 4px 4px 0;min-width:0}#vac-dash .bar .t div:first-child{border-radius:0}
        #vac-dash .bar .v{text-align:right;font-variant-numeric:tabular-nums}
        #vac-dash .col{display:flex;align-items:flex-end;gap:4px;height:190px;padding-top:8px}
        #vac-dash .col .c{flex:1;display:flex;flex-direction:column;justify-content:flex-end;height:100%;align-items:stretch;min-width:0;position:relative}
        #vac-dash .col .c div{margin-top:2px;border-radius:3px 3px 0 0}#vac-dash .col .c .l{position:absolute;bottom:-18px;left:0;right:0;text-align:center;font-size:10px;color:var(--vd-muted);overflow:hidden;margin:0}
        #vac-dash .colwrap{padding-bottom:22px}
        #vac-dash .tools{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
        #vac-dash input,#vac-dash select{font:inherit;color:var(--vd-ink);background:var(--vd-bg);border:1px solid var(--vd-line);border-radius:8px;padding:6px 10px}
        #vac-dash .tw{overflow:auto;max-height:520px;border:1px solid var(--vd-line);border-radius:10px}
        #vac-dash table{border-collapse:collapse;width:100%;font-size:12.5px}#vac-dash th,#vac-dash td{padding:6px 10px;text-align:left;white-space:nowrap;border-bottom:1px solid var(--vd-line)}
        #vac-dash th{position:sticky;top:0;background:var(--vd-surface);cursor:pointer;user-select:none;z-index:1}#vac-dash td.n,#vac-dash th.n{text-align:right;font-variant-numeric:tabular-nums}
        #vac-dash .pill{padding:1px 8px;border-radius:99px;font-size:11.5px;border:1px solid currentColor}
        #vac-dash .p-v{color:var(--vd-red)}#vac-dash .p-p{color:var(--vd-orange)}#vac-dash .p-g{color:var(--vd-blue)}#vac-dash .p-c{color:var(--vd-muted)}
        #vac-dash .heat td.h{text-align:center;padding:4px 6px;min-width:44px;font-variant-numeric:tabular-nums}
        #vd-tip{position:fixed;pointer-events:none;background:var(--vd-ink,#0b0b0b);color:#fff;padding:5px 9px;border-radius:6px;font-size:12px;display:none;z-index:9;max-width:260px}
        #vac-dash .notes li{margin:8px 0}#vac-dash .notes b{color:var(--vd-ink)}
        #vac-dash [data-tip]{cursor:default}
    </style>

    @push('scripts')
    <script>
    (function(){
        const D = @json($data);
        const $=s=>document.querySelector('#vac-dash '+s), fmt=n=>(+n).toLocaleString('es-PE',{maximumFractionDigits:2});
        const short=n=>{const p=n.split(',');return p.length>1?p[0].trim().split(' ').slice(0,2).join(' ')+', '+p[1].trim().split(' ')[0]:n.split(' ').slice(0,3).join(' ')};
        const MES=['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
        const mlabel=d=>MES[+d.slice(5,7)-1]+' '+d.slice(2,4);
        const cutoffRow=D.notas.find(r=>r[0]=='Fecha de corte');const corte=cutoffRow?cutoffRow[1]:'';
        $('#sub').textContent='Fecha de corte: '+corte.split('-').reverse().join('/')+' · '+D.resumen.length+' trabajadores';
        const tip=document.getElementById('vd-tip');
        document.addEventListener('mousemove',e=>{const t=e.target.closest('[data-tip]');if(!t){tip.style.display='none';return}tip.textContent=t.dataset.tip;tip.style.display='block';tip.style.left=Math.min(e.clientX+12,innerWidth-270)+'px';tip.style.top=(e.clientY+14)+'px'});
        const sum=(a,f)=>a.reduce((s,x)=>s+(+f(x)||0),0);
        const kpi=(l,v,s='')=>`<div class="kpi"><small>${l}</small><b>${v}</b><i>${s}</i></div>`;
        function hbars(items,opts={}){
         const mx=opts.max||Math.max(...items.map(i=>sum(i.segs,s=>s.v)),1),sf=opts.suf||'';
         return items.map(i=>{const tot=sum(i.segs,s=>s.v);return `<div class="bar"><span class="n" title="${i.n}">${i.s||i.n}</span><div class="t" style="width:${tot/mx*100}%">${i.segs.filter(s=>s.v>0).map(s=>`<div data-tip="${i.n} · ${s.l}: ${fmt(s.v)}" style="flex:${s.v};background:${s.c}"></div>`).join('')}</div><span class="v">${fmt(tot)}${sf}</span></div>`}).join('')}
        function cols(items,mxo){
         const mx=mxo||Math.max(...items.map(i=>sum(i.segs,s=>s.v)),1);
         return `<div class="colwrap"><div class="col">${items.map(i=>`<div class="c">${i.segs.filter(s=>s.v>0).map(s=>`<div data-tip="${i.l} · ${s.l}: ${fmt(s.v)}" style="height:${s.v/mx*170}px;background:${s.c}"></div>`).join('')}<p class="l">${i.l}</p></div>`).join('')}</div></div>`}
        const leg=a=>`<div class="legend">${a.map(([l,c])=>`<span style="--c:${c}">${l}</span>`).join('')}</div>`;
        function table(id,head,rowsF,data,nums=[]){
         return `<div class="tw"><table id="${id}"><thead><tr>${head.map((h,i)=>`<th data-i="${i}" class="${nums.includes(i)?'n':''}">${h} ↕</th>`).join('')}</tr></thead><tbody></tbody></table></div>`}
        function wireTable(id,getRows,render){
         let sk=-1,dir=1;const t=$('#'+id);
         const draw=()=>{let r=getRows();if(sk>=0)r=[...r].sort((a,b)=>(a.k[sk]>b.k[sk]?1:a.k[sk]<b.k[sk]?-1:0)*dir);t.tBodies[0].innerHTML=r.map(render).join('')||'<tr><td colspan=20>Sin resultados</td></tr>'};
         t.tHead.onclick=e=>{const th=e.target.closest('th');if(!th)return;const i=+th.dataset.i;dir=sk==i?-dir:1;sk=i;draw()};
         draw();return draw}
        const estPill=e=>`<span class="pill ${{'Vencida':'p-v','Pendiente en plazo':'p-p','Gozada':'p-g','En curso':'p-c'}[e]||''}">${e}</span>`;

        (function(){const R=D.resumen,s=$('#s0');
         const gan=sum(R,r=>r[4]),goz=sum(R,r=>r[5]),sal=sum(R,r=>r[6]),ven=sum(R,r=>r[7]),pl=sum(R,r=>r[8]);
         const conVen=R.filter(r=>r[7]>0).length,conSal=R.filter(r=>r[6]>0).length;
         const tr=[['0–2 años',0,2],['3–5 años',3,5],['6–9 años',6,9],['10+ años',10,99]].map(([l,a,b])=>({l,rs:R.filter(r=>r[3]>=a&&r[3]<=b)}));
         s.innerHTML=`<div class="kpis">${kpi('Trabajadores',R.length)}${kpi('Días ganados',fmt(gan))}${kpi('Días gozados',fmt(goz),(gan?Math.round(goz/gan*100):0)+'% de lo ganado')}${kpi('Saldo pendiente',fmt(sal),conSal+' trabajadores con saldo')}${kpi('Saldo vencido',fmt(ven),conVen+' trabajadores'+(sal?' · '+Math.round(ven/sal*100)+'% del saldo':''))}${kpi('Saldo en plazo',fmt(pl))}${kpi('Truncas',fmt(sum(R,r=>r[9])),'periodo en curso')}${kpi('Programados',fmt(sum(R,r=>r[10])),'después del corte')}</div>
         <div class="grid"><div class="card"><h3>Mayor saldo pendiente (Top 10)</h3><p class="s">Días de saldo por trabajador, separado en vencido y en plazo.</p>${leg([['Vencido','var(--vd-red)'],['En plazo','var(--vd-aqua)']])}${hbars([...R].sort((a,b)=>b[6]-a[6]).slice(0,10).map(r=>({n:r[1],s:short(r[1]),segs:[{v:r[7],c:'var(--vd-red)',l:'Vencido'},{v:r[8],c:'var(--vd-aqua)',l:'En plazo'}]})))}</div>
         <div class="card"><h3>Saldo por antigüedad</h3><p class="s">Periodos cumplidos agrupados. Pasa el cursor para ver el detalle.</p>${leg([['Vencido','var(--vd-red)'],['En plazo','var(--vd-aqua)']])}${cols(tr.map(t=>({l:t.l+' ('+t.rs.length+')',segs:[{v:sum(t.rs,r=>r[7]),c:'var(--vd-red)',l:'Vencido'},{v:sum(t.rs,r=>r[8]),c:'var(--vd-aqua)',l:'En plazo'}]})))}</div>
         <div class="card"><h3>Goce vs. ganado</h3><p class="s">Avance de goce acumulado por trabajador (10 con menor avance, solo con periodos cumplidos).</p>${hbars(R.filter(r=>r[4]>0).map(r=>({n:r[1],s:short(r[1]),p:r[5]/r[4],segs:[{v:r[5]/r[4]*100,c:'var(--vd-blue)',l:'% gozado'}]})).sort((a,b)=>a.p-b.p).slice(0,10),{max:100,suf:'%'})}</div></div>
         <div class="card"><h3>Detalle por trabajador</h3><div class="tools"><input id="q0" placeholder="Buscar trabajador…"><label><input type="checkbox" id="c0"> Solo con saldo vencido</label></div>${table('t0',['Trabajador','Ingreso','Periodos','Ganados','Gozados','Saldo','Vencido','En plazo','Truncas','Program.'],0,0,[2,3,4,5,6,7,8,9])}</div>`;
         const rd=wireTable('t0',()=>{const q=$('#q0').value.toLowerCase(),c=$('#c0').checked;return R.filter(r=>r[1].toLowerCase().includes(q)&&(!c||r[7]>0)).map(r=>({r,k:[r[1],r[2],r[3],r[4],r[5],r[6],r[7],r[8],r[9],r[10]]}))},({r})=>`<tr><td>${r[1]}</td><td>${r[2].split('-').reverse().join('/')}</td>${[3,4,5,6,7,8,9,10].map(i=>`<td class="n" ${i==7&&r[7]>0?'style="color:var(--vd-red);font-weight:600"':''}>${fmt(r[i])}</td>`).join('')}</tr>`);
         $('#q0').oninput=rd;$('#c0').onchange=rd;})();

        (function(){const P=D.periodo,s=$('#s1');
         const cnt=e=>P.filter(p=>p[9]==e).length,sm=e=>sum(P.filter(p=>p[9]==e),p=>p[7]);
         const E=[['Gozada','var(--vd-blue)'],['Pendiente en plazo','var(--vd-aqua)'],['Vencida','var(--vd-red)'],['En curso','var(--vd-muted)']];
         const años=[...new Set(P.map(p=>p[3]))].sort();
         const venc=P.filter(p=>p[9]=='Vencida').sort((a,b)=>a[8]>b[8]?1:-1);
         const cumplidos=P.length-cnt('En curso');
         s.innerHTML=`<div class="kpis">${kpi('Periodos totales',P.length)}${kpi('Gozados',cnt('Gozada'),'saldo 0')}${kpi('Pendientes en plazo',cnt('Pendiente en plazo'),fmt(sm('Pendiente en plazo'))+' días')}${kpi('Vencidos',cnt('Vencida'),fmt(sm('Vencida'))+' días en riesgo')}${kpi('En curso',cnt('En curso'),'aún no cumplen año')}${kpi('% periodos gozados',(cumplidos?Math.round(cnt('Gozada')/cumplidos*100):0)+'%','sobre periodos cumplidos')}</div>
         <div class="grid"><div class="card"><h3>Estado de periodos por año de inicio</h3><p class="s">Cantidad de periodos según estado.</p>${leg(E.map(([l,c])=>[l,c]))}${cols(años.map(a=>({l:String(a).slice(2),segs:E.map(([e,c])=>({v:P.filter(p=>p[3]==a&&p[9]==e).length,c,l:e}))})))}<p class="s" style="margin-top:6px">Eje: año de inicio del periodo (20xx).</p></div>
         <div class="card"><h3>Saldo vencido por trabajador</h3><p class="s">Días de periodos vencidos (ver detalle abajo).</p>${hbars(Object.entries(venc.reduce((o,p)=>(o[p[0]]=(o[p[0]]||0)+p[7],o),{})).sort((a,b)=>b[1]-a[1]).map(([n,v])=>({n,s:short(n),segs:[{v,c:'var(--vd-red)',l:'Vencido'}]})))}</div></div>
         <div class="card"><h3>Detalle por periodo</h3><div class="tools"><input id="q1" placeholder="Buscar trabajador…"><select id="e1"><option value="">Todos los estados</option>${E.map(e=>`<option>${e[0]}</option>`).join('')}</select><label><input type="checkbox" id="c1"> Solo con saldo</label></div>${table('t1',['Trabajador','Periodo','Derecho adquirido','Ganados','Gozados','Saldo','Límite de goce','Estado'],0,0,[3,4,5])}</div>`;
         const rd=wireTable('t1',()=>{const q=$('#q1').value.toLowerCase(),e=$('#e1').value,c=$('#c1').checked;return P.filter(p=>p[0].toLowerCase().includes(q)&&(!e||p[9]==e)&&(!c||p[7]>0)).map(p=>({p,k:[p[0],p[2],p[4],p[5],p[6],p[7],p[8]||'',p[9]]}))},({p})=>`<tr><td>${p[0]}</td><td>${p[2]}</td><td>${p[4]?p[4].split('-').reverse().join('/'):'—'}</td><td class="n">${p[5]}</td><td class="n">${p[6]}</td><td class="n">${fmt(p[7])}</td><td>${p[8]?p[8].split('-').reverse().join('/'):'—'}</td><td>${estPill(p[9])}</td></tr>`);
         ['q1','e1','c1'].forEach(i=>$('#'+i).oninput=rd);$('#c1').onchange=rd;$('#e1').onchange=rd;})();

        (function(){const S=D.saldo,C=D.saldo_cols,s=$('#s2'),N=C.length;const vis=C.map((c,j)=>j).filter(j=>S.some(r=>r[2+j]>0));
         const colT=C.map((c,j)=>sum(S,r=>r[2+j]));const tot=sum(S,r=>r[2+N]);
         const conSaldo=S.filter(r=>r[2+N]>0).length;
         const mxc=colT.indexOf(Math.max(...colT,0));const big=S.filter(r=>r[2+N]>60).length;
         s.innerHTML=`<div class="kpis">${kpi('Saldo total',fmt(tot),'días')}${kpi('Trabajadores con saldo',conSaldo,'de '+S.length)}${kpi('Saldo promedio',fmt(conSaldo?tot/conSaldo:0),'por trabajador con saldo')}${kpi('Periodo con más saldo',C[mxc]||'—',tot?fmt(colT[mxc])+' días ('+Math.round(colT[mxc]/tot*100)+'%)':'—')}${kpi('Saldo > 60 días',big,'trabajadores (más de 2 periodos)')}${kpi('Periodos con saldo',N,'columnas')}</div>
         <div class="grid"><div class="card"><h3>Saldo acumulado por periodo</h3><p class="s">Suma de días pendientes de todos los trabajadores, por periodo de origen.</p>${cols(C.map((c,j)=>({l:c,segs:[{v:colT[j],c:j<N-1?'var(--vd-red)':'var(--vd-blue)',l:'Periodo '+c}]})))}<p class="s" style="margin-top:6px">Rojo: periodos anteriores al último; azul: periodo más reciente.</p></div></div>
         <div class="card"><h3>Mapa de calor trabajador × periodo</h3><p class="s">Intensidad = días pendientes. Solo se muestran los periodos con saldo.</p><div class="tools"><input id="q2" placeholder="Buscar trabajador…"><label><input type="checkbox" id="c2" checked> Ocultar sin saldo</label></div><div class="tw"><table class="heat"><thead><tr><th>Trabajador</th>${vis.map(j=>C[j]).map(c=>`<th class="n" style="cursor:default">${c}</th>`).join('')}<th class="n" style="cursor:default">Total</th></tr></thead><tbody id="h2"></tbody></table></div></div>`;
         const rd=()=>{const q=$('#q2').value.toLowerCase(),c=$('#c2').checked;$('#h2').innerHTML=S.filter(r=>r[0].toLowerCase().includes(q)&&(!c||r[2+N]>0)).map(r=>`<tr><td>${r[0]}</td>${vis.map(j=>{const cc=C[j],v=r[2+j];return `<td class="h" data-tip="${short(r[0])} · ${cc}: ${v} días" style="${v?`background:rgba(var(--vd-heat),${.12+v/30*.78});${v>=18?'color:#fff':''}`:'color:var(--vd-muted)'}">${v||'·'}</td>`}).join('')}<td class="n"><b>${r[2+N]}</b></td></tr>`).join('')};
         ['q2','c2'].forEach(i=>$('#'+i).oninput=rd);$('#c2').onchange=rd;rd();})();

        (function(){const G=D.goce,s=$('#s3');
         const gz=G.filter(g=>g[3]!='Programado'),pg=G.filter(g=>g[3]=='Programado');
         const meses=[...new Set(G.map(g=>g[1]))].sort();const recent=meses.slice(-24);
         const byM=m=>({g:sum(gz.filter(x=>x[1]==m),x=>x[2]),p:sum(pg.filter(x=>x[1]==m),x=>x[2])});
         const pico=meses.length?meses.map(m=>[m,byM(m).g]).sort((a,b)=>b[1]-a[1])[0]:['',0];
         const wk=[...new Set(gz.map(g=>g[0]))];
         const byW=Object.entries(gz.reduce((o,g)=>(o[g[0]]=(o[g[0]]||0)+g[2],o),{})).sort((a,b)=>b[1]-a[1]);
         s.innerHTML=`<div class="kpis">${kpi('Días gozados registrados',fmt(sum(gz,g=>g[2])),gz.length+' registros')}${kpi('Días programados',fmt(sum(pg,g=>g[2])),pg.length+' registros futuros')}${kpi('Trabajadores con goce',wk.length)}${kpi('Promedio por trabajador',fmt(wk.length?sum(gz,g=>g[2])/wk.length:0),'días gozados')}${kpi('Mes pico',pico[0]?mlabel(pico[0]):'—',fmt(pico[1])+' días')}${kpi('Registros',G.length)}</div>
         <div class="grid"><div class="card"><h3>Días de goce por mes</h3><p class="s">${recent.length?('Últimos 24 meses con registros ('+mlabel(recent[0])+' – '+mlabel(recent[recent.length-1])+')'):'Sin registros'}; gozado/aprobado y programado.</p>${leg([['Gozado/aprobado','var(--vd-blue)'],['Programado','var(--vd-aqua)']])}${cols(recent.map(m=>({l:mlabel(m).slice(0,3),segs:[{v:byM(m).g,c:'var(--vd-blue)',l:mlabel(m)+' gozado'},{v:byM(m).p,c:'var(--vd-aqua)',l:mlabel(m)+' programado'}]})))}</div>
         <div class="card"><h3>Trabajadores con más días gozados</h3><p class="s">Top 10 según el detalle registrado.</p>${hbars(byW.slice(0,10).map(([n,v])=>({n,s:short(n),segs:[{v,c:'var(--vd-blue)',l:'Gozados'}]})))}</div></div>
         <div class="card"><h3>Registros de goce</h3><div class="tools"><input id="q3" placeholder="Buscar trabajador…"><select id="e3"><option value="">Todas las situaciones</option><option>Gozado/aprobado</option><option>Programado</option></select></div>${table('t3',['Trabajador','Mes','Días','Situación'],0,0,[2])}</div>`;
         const rd=wireTable('t3',()=>{const q=$('#q3').value.toLowerCase(),e=$('#e3').value;return G.filter(g=>g[0].toLowerCase().includes(q)&&(!e||g[3]==e)).map(g=>({g,k:[g[0],g[1],g[2],g[3]]}))},({g})=>`<tr><td>${g[0]}</td><td>${mlabel(g[1])}</td><td class="n">${g[2]}</td><td>${g[3]=='Programado'?'<span class="pill p-p">Programado</span>':'<span class="pill p-g">Gozado/aprobado</span>'}</td></tr>`);
         $('#q3').oninput=rd;$('#e3').onchange=rd;})();

        (function(){const N=D.notas,s=$('#s4');const par=k=>(N.find(r=>r[0]==k)||[])[1];
         const f=v=>/^\d{4}-\d\d-\d\d$/.test(v)?v.split('-').reverse().join('/'):v;
         const obs=N.filter(r=>r.length==2&&!['Fecha de corte','Días de vacaciones por periodo','Último mes de goce considerado (mes en curso)'].includes(r[0]));
         s.innerHTML=`<div class="kpis">${kpi('Fecha de corte',f(par('Fecha de corte')))}${kpi('Días por periodo',par('Días de vacaciones por periodo'))}${kpi('Último mes de goce',mlabel(par('Último mes de goce considerado (mes en curso)')))}${kpi('Observaciones',obs.length,'criterios y reglas')}</div>
         <div class="card"><h3>Criterios y observaciones</h3><ul class="notes">${obs.map(r=>`<li><b>${r[0]}:</b> ${r[1]}</li>`).join('')}</ul></div>`;})();

        const tabs=['Resumen','Por periodo','Saldo por periodo','Detalle goce','Notas'];
        $('#nav').innerHTML=tabs.map((t,i)=>`<button role="tab" type="button" data-i="${i}">${t}</button>`).join('');
        function go(i){document.querySelectorAll('#vac-dash #nav button').forEach(b=>b.setAttribute('aria-selected',b.dataset.i==i));document.querySelectorAll('#vac-dash section').forEach((s,j)=>s.classList.toggle('on',j==i))}
        $('#nav').onclick=e=>{const b=e.target.closest('button');if(b)go(b.dataset.i)};go(0);
    })();
    </script>
    @endpush
</x-filament-panels::page>
