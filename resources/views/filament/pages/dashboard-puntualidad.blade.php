<x-filament-panels::page>
    <form method="GET" id="pd-filtros" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
        <div>
            <label for="pd_corte" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Periodo</label>
            <select id="pd_corte" name="corte">
                @foreach($cortes as $k => $label)
                    <option value="{{ $k }}" @selected($clave === $k)>{{ $label }}</option>
                @endforeach
                <option value="custom" @selected($clave === 'custom')>Rango personalizado…</option>
            </select>
        </div>
        <div id="pd_rango" style="display:{{ $clave === 'custom' ? 'flex' : 'none' }};gap:8px;align-items:flex-end;">
            <div>
                <label for="pd_desde" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Desde</label>
                <input type="date" id="pd_desde" name="desde" value="{{ $desde }}">
            </div>
            <div>
                <label for="pd_hasta" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Hasta</label>
                <input type="date" id="pd_hasta" name="hasta" value="{{ $hasta }}">
            </div>
        </div>
        <div>
            <label for="pd_sede" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Sede</label>
            <select id="pd_sede" name="sede">
                <option value="">Todas las sedes</option>
                @foreach($sedes as $id => $nombre)
                    <option value="{{ $id }}" @selected((int) $sede === (int) $id)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="pd_area" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Área</label>
            <select id="pd_area" name="area">
                <option value="">Todas las áreas</option>
                @foreach($areas as $id => $nombre)
                    <option value="{{ $id }}" @selected((int) $area === (int) $id)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <x-filament::button type="submit">Aplicar</x-filament::button>
    </form>

    <div id="pd-dash">
        <header><div><h1>Tardanzas y puntualidad</h1><p id="pd-sub"></p></div></header>
        <nav id="pd-nav" role="tablist"></nav>
        <main>
            <section id="pd-s0"></section><section id="pd-s1"></section><section id="pd-s2"></section><section id="pd-s3"></section><section id="pd-s4"></section>
        </main>
        <div id="pd-tip"></div>
    </div>

    <style>
        /* Controles del filtro: heredan el color del tema (claro u oscuro) para que el texto siempre se vea. */
        #pd-filtros select,#pd-filtros input{font:inherit;color:inherit;background:transparent;border:1px solid rgba(128,128,128,.45);border-radius:8px;padding:6px 10px;color-scheme:light dark;min-width:140px}
        #pd-filtros select:focus,#pd-filtros input:focus{outline:2px solid rgba(42,120,214,.6);outline-offset:1px}
        #pd-filtros option{color:#111;background:#fff}
        #pd-dash{--pd-surface:#fcfcfb;--pd-ink:#0b0b0b;--pd-ink2:#52514e;--pd-muted:#8a8984;--pd-line:#e4e3de;--pd-blue:#2a78d6;--pd-green:#1baf7a;--pd-amber:#e0a31b;--pd-red:#e34948;color:var(--pd-ink);font:14px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif}
        @media(prefers-color-scheme:dark){#pd-dash{--pd-surface:#1a1a19;--pd-ink:#fff;--pd-ink2:#c3c2b7;--pd-muted:#8b8a82;--pd-line:#2e2e2b;--pd-blue:#3987e5;--pd-green:#199e70;--pd-amber:#d9a21f;--pd-red:#e66767}}
        .dark #pd-dash{--pd-surface:#1a1a19;--pd-ink:#fff;--pd-ink2:#c3c2b7;--pd-muted:#8b8a82;--pd-line:#2e2e2b;--pd-blue:#3987e5;--pd-green:#199e70;--pd-amber:#d9a21f;--pd-red:#e66767}
        #pd-dash *{box-sizing:border-box}
        #pd-dash header{padding:0 0 12px}
        #pd-dash h1{margin:0;font-size:20px}#pd-dash header p{margin:2px 0 0;color:var(--pd-ink2)}
        #pd-dash nav{display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid var(--pd-line)}
        #pd-dash nav button{font:inherit;border:0;border-bottom:2px solid transparent;border-radius:8px 8px 0 0;background:none;padding:10px 14px;color:var(--pd-ink2);cursor:pointer}
        #pd-dash nav button[aria-selected=true]{color:var(--pd-ink);border-bottom-color:var(--pd-blue);font-weight:600}
        #pd-dash main{padding:16px 0}
        #pd-dash section{display:none}#pd-dash section.on{display:block}
        #pd-dash .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px}
        #pd-dash .kpi{background:var(--pd-surface);border:1px solid var(--pd-line);border-radius:12px;padding:12px 14px}
        #pd-dash .kpi small{color:var(--pd-ink2);display:block}#pd-dash .kpi b{font-size:26px;font-weight:650;letter-spacing:-.5px}#pd-dash .kpi i{font-style:normal;color:var(--pd-muted);font-size:12px;display:block}
        #pd-dash .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:14px;margin-bottom:14px}
        @media(max-width:480px){#pd-dash .grid{grid-template-columns:1fr}}
        #pd-dash .card{background:var(--pd-surface);border:1px solid var(--pd-line);border-radius:12px;padding:14px;min-width:0}
        #pd-dash .card h3{margin:0 0 2px;font-size:15px}#pd-dash .card p.s{margin:0 0 10px;color:var(--pd-ink2);font-size:12.5px}
        #pd-dash .legend{display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--pd-ink2);margin-bottom:8px}
        #pd-dash .legend span:before{content:"";display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:5px;background:var(--c)}
        #pd-dash .bar{display:grid;grid-template-columns:minmax(90px,200px) 1fr 62px;gap:8px;align-items:center;margin:4px 0;font-size:12.5px}
        #pd-dash .bar .n{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--pd-ink2)}
        #pd-dash .bar .t{display:flex;height:16px;gap:2px;background:color-mix(in srgb,var(--pd-line) 55%,transparent);border-radius:0 4px 4px 0}
        #pd-dash .bar .t div{min-width:0}
        #pd-dash .bar .v{text-align:right;font-variant-numeric:tabular-nums}
        #pd-dash .col{display:flex;align-items:flex-end;gap:3px;height:190px;padding-top:8px}
        #pd-dash .col .c{flex:1;display:flex;flex-direction:column;justify-content:flex-end;height:100%;min-width:0;position:relative}
        #pd-dash .col .c div{border-radius:3px 3px 0 0}#pd-dash .col .c .l{position:absolute;bottom:-18px;left:0;right:0;text-align:center;font-size:10px;color:var(--pd-muted);overflow:hidden;margin:0;white-space:nowrap}
        #pd-dash .colwrap{padding-bottom:22px;overflow-x:auto}
        #pd-dash .tools{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;align-items:center}
        #pd-dash input[type=text],#pd-dash select{font:inherit;color:var(--pd-ink);background:transparent;border:1px solid var(--pd-line);border-radius:8px;padding:6px 10px}
        #pd-dash .tw{overflow:auto;max-height:560px;border:1px solid var(--pd-line);border-radius:10px}
        #pd-dash table{border-collapse:collapse;width:100%;font-size:12.5px}#pd-dash th,#pd-dash td{padding:6px 10px;text-align:left;white-space:nowrap;border-bottom:1px solid var(--pd-line)}
        #pd-dash th{position:sticky;top:0;background:var(--pd-surface);cursor:pointer;user-select:none;z-index:1}#pd-dash td.n,#pd-dash th.n{text-align:right;font-variant-numeric:tabular-nums}
        #pd-dash .pill{padding:1px 8px;border-radius:99px;font-size:11.5px;border:1px solid currentColor;font-variant-numeric:tabular-nums}
        #pd-dash .g{color:var(--pd-green)}#pd-dash .a{color:var(--pd-amber)}#pd-dash .r{color:var(--pd-red)}#pd-dash .m{color:var(--pd-muted)}
        #pd-dash .heat td.h{text-align:center;padding:5px 8px;min-width:70px;font-variant-numeric:tabular-nums;color:#0b0b0b}
        #pd-tip{position:fixed;pointer-events:none;background:#0b0b0b;color:#fff;padding:5px 9px;border-radius:6px;font-size:12px;display:none;z-index:9;max-width:260px}
        #pd-dash .vacio{padding:24px;text-align:center;color:var(--pd-ink2)}
    </style>

    @push('scripts')
    <script>
    (function(){
        const D = @json($data);
        const $ = s => document.querySelector('#pd-dash ' + s);
        const fmt = n => (+n).toLocaleString('es-PE', {maximumFractionDigits: 1});
        const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const short = n => { const p = String(n).split(','); return p.length > 1 ? p[0].trim().split(' ').slice(0, 2).join(' ') + ', ' + p[1].trim().split(' ')[0] : n; };
        const U = D.umbral || 90;
        const cls = p => p === null ? 'm' : (p >= U ? 'g' : (p >= U - 15 ? 'a' : 'r'));
        const colorVar = p => ({g: 'var(--pd-green)', a: 'var(--pd-amber)', r: 'var(--pd-red)', m: 'var(--pd-muted)'})[cls(p)];
        const heat = p => p === null ? 'transparent' : (p >= U ? '#7fd8b3' : (p >= U - 15 ? '#f2cf74' : '#f19a9a'));
        const pctTxt = p => p === null ? '—' : fmt(p) + '%';
        const pill = p => `<span class="pill ${cls(p)}">${pctTxt(p)}</span>`;
        const ddhhmm = m => { m = Math.round(+m || 0); if (m <= 0) return '—'; const p = n => String(n).padStart(2, '0'); return p(Math.floor(m / 1440)) + ':' + p(Math.floor(m % 1440 / 60)) + ':' + p(m % 60); };
        const sum = (a, f) => a.reduce((s, x) => s + (+f(x) || 0), 0);
        const sumP = a => sum(a, x => x.p), sumT = a => sum(a, x => x.t);
        const pctOf = (p, t) => (p + t) > 0 ? Math.round(1000 * p / (p + t)) / 10 : null;
        const kpi = (l, v, s = '', c = '') => `<div class="kpi"><small>${l}</small><b class="${c}">${v}</b><i>${s}</i></div>`;
        const dmy = d => d.split('-').reverse().join('/');
        const vacio = '<div class="vacio">No hay marcaciones en el periodo y filtros elegidos.</div>';

        $('#pd-sub').textContent = 'Del ' + dmy(D.periodo.desde) + ' al ' + dmy(D.periodo.hasta) + ' · ' + D.kpi.personas + ' trabajadores con marcaciones · puntualidad = días puntual ÷ (puntual + tarde) · meta ' + U + '%';

        const tip = document.getElementById('pd-tip');
        document.addEventListener('mousemove', e => { const t = e.target.closest('#pd-dash [data-tip]'); if (!t) { tip.style.display = 'none'; return; } tip.textContent = t.dataset.tip; tip.style.display = 'block'; tip.style.left = Math.min(e.clientX + 12, innerWidth - 270) + 'px'; tip.style.top = (e.clientY + 14) + 'px'; });

        // Barras horizontales del % de puntualidad (0–100)
        const barsPct = (items) => items.map(i => `<div class="bar"><span class="n" title="${esc(i.n)}">${esc(i.s || i.n)}</span><div class="t"><div data-tip="${esc(i.n)} · ${pctTxt(i.pct)} (${i.p} puntual / ${i.t} tarde)" style="width:${i.pct === null ? 0 : i.pct}%;background:${colorVar(i.pct)}"></div></div><span class="v">${pctTxt(i.pct)}</span></div>`).join('') || vacio;
        // Barras de días tarde vs puntual (apiladas, escala común)
        const barsDias = (items) => { const mx = Math.max(...items.map(i => i.p + i.t), 1); return items.map(i => `<div class="bar"><span class="n" title="${esc(i.n)}">${esc(i.s || i.n)}</span><div class="t" style="width:${(i.p + i.t) / mx * 100}%"><div data-tip="${esc(i.n)} · Puntual: ${i.p}" style="flex:${i.p};background:var(--pd-green)"></div><div data-tip="${esc(i.n)} · Tarde: ${i.t}" style="flex:${i.t};background:var(--pd-red)"></div></div><span class="v">${i.p}/${i.t}</span></div>`).join('') || vacio; };
        const leg = a => `<div class="legend">${a.map(([l, c]) => `<span style="--c:${c}">${l}</span>`).join('')}</div>`;

        // Tabla ordenable con buscador opcional
        function tabla(id, cols, rows, opts = {}) {
            const root = $('#' + id); let sk = opts.sort ?? -1, dir = opts.dir ?? 1;
            root.innerHTML = (opts.search ? `<div class="tools"><input type="text" placeholder="Buscar…" class="q">${opts.extra || ''}</div>` : '') +
                `<div class="tw"><table><thead><tr>${cols.map((c, i) => `<th data-i="${i}" class="${c.n ? 'n' : ''}">${c.h} ↕</th>`).join('')}</tr></thead><tbody></tbody></table></div>`;
            const body = root.querySelector('tbody');
            const draw = () => {
                const q = (root.querySelector('.q')?.value || '').toLowerCase();
                const solo = root.querySelector('.solo')?.checked;
                let r = rows.filter(x => (!q || (x.n + ' ' + (x.sede || '') + ' ' + (x.area || '')).toLowerCase().includes(q)) && (!solo || x.t > 0));
                if (sk >= 0) { const f = cols[sk].k; r = [...r].sort((a, b) => { const A = f(a), B = f(b); return ((A === null) - (B === null)) || (A > B ? 1 : A < B ? -1 : 0) * dir; }); }
                body.innerHTML = r.map(x => `<tr>${cols.map(c => `<td class="${c.n ? 'n' : ''}">${c.f(x)}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="${cols.length}">Sin resultados</td></tr>`;
            };
            root.querySelector('thead').onclick = e => { const th = e.target.closest('th'); if (!th) return; const i = +th.dataset.i; dir = sk === i ? -dir : (cols[i].n ? -1 : 1); sk = i; draw(); };
            root.querySelectorAll('.q,.solo').forEach(el => { el.oninput = draw; el.onchange = draw; });
            draw();
        }
        const colsBase = [
            {h: 'Puntual', n: 1, k: x => x.p, f: x => x.p},
            {h: 'Tarde', n: 1, k: x => x.t, f: x => x.t},
            {h: '% puntualidad', n: 1, k: x => x.pct, f: x => pill(x.pct)},
            {h: 'Tiempo tarde (dd:hh:mm)', n: 1, k: x => x.min, f: x => ddhhmm(x.min)},
            {h: 'Min. por tardanza', n: 1, k: x => x.t ? x.min / x.t : 0, f: x => x.t ? fmt(x.min / x.t) : '—'},
            {h: 'Faltas injust.', n: 1, k: x => x.aus, f: x => x.aus},
        ];

        // ── Pestañas ────────────────────────────────────────────────────────────
        const nav = $('#pd-nav'), secs = ['Resumen', 'Por sede', 'Por área', 'Por persona', 'Tendencia'];
        nav.innerHTML = secs.map((s, i) => `<button role="tab" aria-selected="${i === 0}" data-i="${i}">${s}</button>`).join('');
        const show = i => { nav.querySelectorAll('button').forEach(b => b.setAttribute('aria-selected', +b.dataset.i === i)); secs.forEach((_, j) => document.getElementById('pd-s' + j).classList.toggle('on', j === i)); try { sessionStorage.setItem('pd-tab', i); } catch (e) {} };
        nav.onclick = e => { const b = e.target.closest('button'); if (b) show(+b.dataset.i); };

        const K = D.kpi, hayDatos = K.p + K.t + K.aus > 0;
        const sedes = D.sedes.map(x => ({...x, s: x.n})).sort((a, b) => (b.pct ?? -1) - (a.pct ?? -1));
        const areas = D.areas.map(x => ({...x, s: x.n})).sort((a, b) => (b.pct ?? -1) - (a.pct ?? -1));
        const personas = D.personas.map(x => ({...x, s: short(x.n)}));

        // Resumen
        (function () {
            const s = document.getElementById('pd-s0');
            if (!hayDatos) { s.innerHTML = vacio; return; }
            const evaluables = personas.filter(x => x.p + x.t >= 5);
            const tardones = [...personas].filter(x => x.t > 0).sort((a, b) => b.t - a.t || a.pct - b.pct).slice(0, 10);
            const puntuales = [...evaluables].sort((a, b) => b.pct - a.pct || b.p - a.p).slice(0, 10);
            s.innerHTML = `<div class="kpis">
                ${kpi('% de puntualidad', pctTxt(K.pct), 'meta ' + U + '%', cls(K.pct))}
                ${kpi('Días puntual', fmt(K.p))}
                ${kpi('Días tarde', fmt(K.t), K.t ? 'promedio ' + fmt(K.min / K.t) + ' min por tardanza' : '')}
                ${kpi('Tiempo tarde total', ddhhmm(K.min), 'dd:hh:mm')}
                ${kpi('Con alguna tardanza', K.con_tardanza, 'de ' + K.personas + ' trabajadores')}
                ${kpi('Sin ninguna tardanza', K.sin_tardanza, 'puntualidad 100%')}
                ${kpi('Faltas injustificadas', K.aus)}
            </div>
            <div class="grid">
              <div class="card"><h3>Puntualidad por sede</h3><p class="s">% de puntualidad (puntual ÷ puntual + tarde). Verde = en la meta.</p>${barsPct(sedes)}</div>
              <div class="card"><h3>Puntualidad por área</h3><p class="s">Ordenado de mayor a menor.</p>${barsPct(areas)}</div>
              <div class="card"><h3>Más tardanzas (Top 10)</h3><p class="s">Personas con más días tarde en el periodo.</p>${leg([['Puntual', 'var(--pd-green)'], ['Tarde', 'var(--pd-red)']])}${barsDias(tardones)}</div>
              <div class="card"><h3>Más puntuales (Top 10)</h3><p class="s">Entre quienes tienen al menos 5 días evaluables.</p>${barsPct(puntuales)}</div>
            </div>`;
        })();

        // Sedes
        (function () {
            const s = document.getElementById('pd-s1');
            if (!hayDatos) { s.innerHTML = vacio; return; }
            s.innerHTML = `<div class="grid"><div class="card"><h3>Días puntual vs. tarde por sede</h3><p class="s">Escala común: la barra más larga es la sede con más días registrados.</p>${leg([['Puntual', 'var(--pd-green)'], ['Tarde', 'var(--pd-red)']])}${barsDias(sedes)}</div></div>
                <div class="card"><h3>Detalle por sede</h3><div id="pd-t1"></div></div>`;
            tabla('pd-t1', [{h: 'Sede', k: x => x.n, f: x => esc(x.n)}, {h: 'Trabajadores', n: 1, k: x => x.personas, f: x => x.personas}, ...colsBase], sedes, {sort: 4, dir: -1});
        })();

        // Áreas
        (function () {
            const s = document.getElementById('pd-s2');
            if (!hayDatos) { s.innerHTML = vacio; return; }
            const sedN = [...new Set(D.matriz.map(m => m.sede))].sort(), areN = [...new Set(D.matriz.map(m => m.area))].sort();
            const cel = (a, se) => D.matriz.find(m => m.area === a && m.sede === se);
            const mapa = areN.length && sedN.length ? `<div class="card"><h3>Áreas dentro de cada sede</h3><p class="s">% de puntualidad de cada área en cada sede. Verde = en la meta, ámbar = cerca, rojo = lejos.</p><div class="tw"><table class="heat"><thead><tr><th>Área</th>${sedN.map(x => `<th class="n">${esc(x)}</th>`).join('')}</tr></thead><tbody>${areN.map(a => `<tr><td>${esc(a)}</td>${sedN.map(se => { const c = cel(a, se); return c ? `<td class="h" data-tip="${esc(a)} · ${esc(se)}: ${c.p} puntual / ${c.t} tarde" style="background:${heat(c.pct)}">${pctTxt(c.pct)}</td>` : '<td class="h m">·</td>'; }).join('')}</tr>`).join('')}</tbody></table></div></div>` : '';
            s.innerHTML = `<div class="grid"><div class="card"><h3>Días puntual vs. tarde por área</h3><p class="s">Escala común.</p>${leg([['Puntual', 'var(--pd-green)'], ['Tarde', 'var(--pd-red)']])}${barsDias(areas)}</div></div>
                ${mapa}<div class="card" style="margin-top:14px"><h3>Detalle por área</h3><div id="pd-t2"></div></div>`;
            tabla('pd-t2', [{h: 'Área', k: x => x.n, f: x => esc(x.n)}, {h: 'Trabajadores', n: 1, k: x => x.personas, f: x => x.personas}, ...colsBase], areas, {sort: 4, dir: -1});
        })();

        // Personas
        (function () {
            const s = document.getElementById('pd-s3');
            if (!hayDatos) { s.innerHTML = vacio; return; }
            s.innerHTML = `<div class="card"><h3>Detalle por persona</h3><p class="s">Pulsa un encabezado para ordenar. Por defecto, de menor a mayor puntualidad.</p><div id="pd-t3"></div></div>`;
            tabla('pd-t3', [
                {h: 'Persona', k: x => x.n, f: x => esc(x.n)},
                {h: 'Sede', k: x => x.sede, f: x => esc(x.sede)},
                {h: 'Área', k: x => x.area, f: x => esc(x.area)},
                ...colsBase,
            ], personas, {search: true, extra: '<label><input type="checkbox" class="solo"> Solo con tardanzas</label>', sort: 5, dir: 1});
        })();

        // Tendencia
        (function () {
            const s = document.getElementById('pd-s4');
            if (!hayDatos) { s.innerHTML = vacio; return; }
            const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
            const dias = D.dias.filter(d => d.p + d.t > 0);
            const mx = Math.max(...dias.map(d => d.p + d.t), 1);
            const cols = `<div class="colwrap"><div class="col">${dias.map(d => { const tot = d.p + d.t, h = tot / mx * 170; return `<div class="c" style="min-width:16px"><div data-tip="${dmy(d.f)} · ${d.p} puntual / ${d.t} tarde · ${pctTxt(d.pct)}" style="height:${h * (d.t / tot)}px;background:var(--pd-red)"></div><div data-tip="${dmy(d.f)} · ${d.p} puntual / ${d.t} tarde · ${pctTxt(d.pct)}" style="height:${h * (d.p / tot)}px;background:var(--pd-green)"></div><p class="l">${d.f.slice(8)}</p></div>`; }).join('')}</div></div>`;
            const sem = D.semana.map(x => ({...x, n: DIAS[x.d], s: DIAS[x.d]}));
            s.innerHTML = `<div class="grid">
                <div class="card" style="grid-column:1/-1"><h3>Marcaciones por día</h3><p class="s">Cada columna es un día del periodo (número = día del mes): días puntual en verde y tarde en rojo. Pasa el cursor para ver el detalle.</p>${leg([['Puntual', 'var(--pd-green)'], ['Tarde', 'var(--pd-red)']])}${cols}</div>
                <div class="card"><h3>Puntualidad por día de la semana</h3><p class="s">¿Qué días se llega más tarde?</p>${barsPct(sem)}</div>
                <div class="card"><h3>Tardanzas por día de la semana</h3><p class="s">Días puntual / días tarde de cada día de la semana.</p>${barsDias(sem)}</div>
            </div>`;
        })();

        let tab = 0; try { tab = +(sessionStorage.getItem('pd-tab') || 0); } catch (e) {}
        show(tab >= 0 && tab < secs.length ? tab : 0);

        const corte = document.getElementById('pd_corte');
        if (corte) corte.onchange = () => { document.getElementById('pd_rango').style.display = corte.value === 'custom' ? 'flex' : 'none'; };
    })();
    </script>
    @endpush
</x-filament-panels::page>
