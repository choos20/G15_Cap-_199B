import '../css/dashboard-analytics.css';
import {chartRows, chartTotals, countAxis, internalRecordUrl, periodRange} from './analytics-data';

const motion = matchMedia('(prefers-reduced-motion: reduce)');
const number = new Intl.NumberFormat(document.documentElement.lang || 'en');
const themes = {
    emerald: {top:'#58d6b0', bottom:'#057454', color:'#078d70'},
    amber: {top:'#ffda7a', bottom:'#d18b0d', color:'#c98208'},
    rose: {top:'#fc9ab4', bottom:'#d43c6b', color:'#d94e74'},
    blue: {top:'#8abaff', bottom:'#2f66db', color:'#2563eb'},
    purple: {top:'#c1a1ff', bottom:'#8050d8', color:'#7c3aed'},
    neutral: {top:'#c4d0c9', bottom:'#73897b', color:'#73897b'},
};
let nextId = 0;
let tooltip;
let tooltipTarget;

function themeFor(item) {
    const color = String(item.color || item.fill || '').toLowerCase();
    if (['#c98208','#d18a1c'].includes(color) || /pending|review/.test(item.key)) return 'amber';
    if (['#d94e74','#c95f5f'].includes(color) || /returned|rejected/.test(item.key)) return 'rose';
    if (color === '#2563eb') return 'blue';
    if (color === '#7c3aed') return 'purple';
    return 'emerald';
}

function html(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = String(text);
    return element;
}

function svg(tag, attributes = {}, text) {
    const element = document.createElementNS('http://www.w3.org/2000/svg', tag);
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
    if (text !== undefined) element.textContent = String(text);
    return element;
}

function hideTooltip() {
    if (tooltip) tooltip.hidden = true;
    tooltipTarget = null;
}

function showTooltip(target, title, rows, event, hint = '') {
    if (!tooltip) {
        tooltip = html('div', 'pl-analytics-tooltip');
        tooltip.id = 'projectlink-analytics-tooltip';
        tooltip.setAttribute('role', 'tooltip');
        document.body.append(tooltip);
    }
    if (tooltipTarget !== target) {
        tooltip.replaceChildren(html('strong', '', title));
        rows.forEach(row => {
            const line = html('div', 'pl-analytics-tooltip-row');
            const label = html('span', '', row.label);
            if (row.color) {
                const dot = html('i', 'pl-legend-dot');
                dot.style.setProperty('--item-color', row.color);
                label.prepend(dot);
            }
            line.append(label, html('b', '', row.value));
            tooltip.append(line);
        });
        if (hint) tooltip.append(html('small', 'pl-analytics-tooltip-hint', hint));
        tooltipTarget = target;
    }
    tooltip.hidden = false;
    const anchor = target.getBoundingClientRect();
    const bounds = tooltip.getBoundingClientRect();
    const x = event?.clientX ?? anchor.left + anchor.width / 2;
    const y = event?.clientY ?? anchor.top;
    tooltip.style.left = `${Math.max(10, Math.min(innerWidth - bounds.width - 10, x - bounds.width / 2))}px`;
    tooltip.style.top = `${Math.max(10, Math.min(innerHeight - bounds.height - 10, y - bounds.height - 12 < 10 ? y + 18 : y - bounds.height - 12))}px`;
    target.setAttribute('aria-describedby', tooltip.id);
}

function bindTooltip(target, title, rows, hint = '') {
    const show = event => showTooltip(target, title, rows, event, hint);
    target.addEventListener('pointerenter', show);
    target.addEventListener('pointermove', show);
    target.addEventListener('focusin', () => show());
    target.addEventListener('pointerleave', hideTooltip);
    target.addEventListener('focusout', hideTooltip);
    if (!target.closest('a')) target.addEventListener('click', show);
}

function renderDataTable(root, rows, series, unit, singularUnit) {
    const body = root.querySelector('[data-chart-table] tbody');
    body.replaceChildren(...rows.map(row => {
        const tr = html('tr');
        const heading = html('th', '', row.periodLabel);
        heading.scope = 'row';
        tr.append(heading, ...row.values.map(value => html('td', '', number.format(value))), html('td', '', number.format(row.total)));
        return tr;
    }));
    const legend = root.querySelector('[data-chart-legend]');
    const totals = chartTotals(rows, series);
    legend.replaceChildren(...series.map((item, index) => {
        const entry = html('div');
        const label = html('span', 'pl-legend-label', item.label);
        const dot = html('i', 'pl-legend-dot');
        dot.style.setProperty('--item-color', themes[themeFor(item)].color);
        label.prepend(dot);
        entry.append(label, html('strong', '', number.format(totals[index])), html('small', '', totals[index] === 1 ? singularUnit : unit));
        return entry;
    }));
}

function initColumns(root) {
    if (root.dataset.analyticsReady) return;
    root.dataset.analyticsReady = 'true';
    const config = JSON.parse(root.querySelector('[data-analytics-config]').textContent);
    const rows = chartRows(config.months, config.series);
    const canvas = root.querySelector('[data-chart-canvas]');
    const from = root.querySelector('[data-period-from]');
    const to = root.querySelector('[data-period-to]');
    let mode = config.mode;
    let previousWidth = 0;
    let resizeFrame;
    const id = `pl-chart-${++nextId}`;

    const render = (announce = false, animate = true) => {
        hideTooltip();
        const selected = periodRange(rows, from.value, to.value);
        const total = selected.reduce((sum, row) => sum + row.total, 0);
        const width = Math.max(240, Math.round(canvas.clientWidth));
        const height = canvas.clientHeight || 245;
        const top = 25, bottom = height - 33, left = 34, right = width - 12;
        const plotHeight = bottom - top;
        const axis = countAxis(Math.max(0, ...selected.map(row => mode === 'grouped' ? Math.max(...row.values) : row.total)));
        const chart = svg('svg', {viewBox:`0 0 ${width} ${height}`, class:'pl-column-svg', role:'group', 'aria-label':`${root.querySelector('h3').textContent}. ${selected[0]?.periodLabel || ''} to ${selected.at(-1)?.periodLabel || ''}. ${total} ${config.unit}.`});
        const definitions = svg('defs');
        [...config.series, {key:'total',color:'#078d70'}].forEach((item, index) => {
            const theme = themes[themeFor(item)];
            const gradient = svg('linearGradient', {id:`${id}-fill-${index}`, x1:'0',y1:'0',x2:'0',y2:'1'});
            gradient.append(svg('stop',{offset:'0%', 'stop-color':theme.top}), svg('stop',{offset:'100%','stop-color':theme.bottom}));
            definitions.append(gradient);
        });
        chart.append(definitions);
        axis.ticks.forEach(tick => {
            const y = bottom - tick / axis.max * plotHeight;
            chart.append(svg('line',{x1:left,y1:y,x2:right,y2:y,class:'pl-gridline'}), svg('text',{x:left-10,y:y+4,'text-anchor':'end',class:'pl-axis-label'},number.format(tick)));
        });
        const slot = (right - left) / Math.max(selected.length, 1);
        selected.forEach((row, index) => {
            const center = left + slot * (index + 0.5);
            const barWidth = Math.min(54, slot * .56);
            const parameters = {from:row.period, to:row.period};
            const href = internalRecordUrl(config.recordUrl, parameters, location.origin);
            const rowUnit = row.total === 1 ? config.singularUnit : config.unit;
            const group = svg(href ? 'a' : 'g', {class:`pl-column-hit${href ? ' pl-column-link' : ''}`,tabindex:'0',role:href?'link':'img','aria-label':`${row.periodLabel}: ${row.total} ${rowUnit}. ${config.series.map((item,i)=>`${item.label}: ${row.values[i]}`).join('; ')}`,...(href ? {href} : {})});
            group.append(svg('rect',{x:center-slot*.44,y:8,width:slot*.88,height:bottom+20,rx:12,class:'pl-column-highlight'}));
            const tracker = svg('rect',{x:center-barWidth/2,y:top,width:barWidth,height:plotHeight,rx:9,fill:'#f2f5f3'});
            group.append(tracker);
            const bars = svg('g',{'data-column-bars':''});
            if (mode === 'total') {
                const barHeight = row.total / axis.max * plotHeight;
                if (row.total) bars.append(svg('rect',{x:center-barWidth/2,y:bottom-barHeight,width:barWidth,height:barHeight,rx:9,fill:`url(#${id}-fill-${config.series.length})`,class:'pl-bar','data-count':row.total}));
            } else if (mode === 'grouped') {
                row.values.forEach((value, seriesIndex) => {
                    const barHeight = value / axis.max * plotHeight;
                    const individualWidth = Math.max(3,(barWidth-4)/config.series.length);
                    if (value) bars.append(svg('rect',{x:center-barWidth/2+seriesIndex*(individualWidth+2),y:bottom-barHeight,width:individualWidth,height:barHeight,rx:Math.min(5,individualWidth/2),fill:`url(#${id}-fill-${seriesIndex})`,class:'pl-bar','data-count':value}));
                    group.append(svg('text',{x:center-barWidth/2+seriesIndex*(individualWidth+2)+individualWidth/2,y:bottom-barHeight-8,'text-anchor':'middle',class:'pl-total-label'},number.format(value)));
                });
            } else {
                const clipId = `${id}-clip-${index}`;
                const stackHeight = row.total / axis.max * plotHeight;
                const clip = svg('clipPath',{id:clipId});
                clip.append(svg('rect',{x:center-barWidth/2,y:bottom-stackHeight,width:barWidth,height:stackHeight,rx:9}));
                definitions.append(clip);
                let stacked = 0;
                row.values.forEach((value, seriesIndex) => {
                    const barHeight = value / axis.max * plotHeight;
                    const y = bottom - (stacked + value) / axis.max * plotHeight;
                    if (value) {
                        bars.append(svg('rect',{x:center-barWidth/2,y,width:barWidth,height:barHeight,fill:`url(#${id}-fill-${seriesIndex})`,'clip-path':`url(#${clipId})`,class:'pl-bar','data-count':value}));
                        if (barHeight >= 24) group.append(svg('text',{x:center,y:y+barHeight/2+4,'text-anchor':'middle',class:'pl-segment-label','data-tone':themeFor(config.series[seriesIndex])},number.format(value)));
                    }
                    stacked += value;
                });
            }
            // Insert the bars behind their text labels while keeping the hit track.
            group.insertBefore(bars,group.children[2] || null);
            const visibleHeight = (mode === 'grouped' ? Math.max(0,...row.values) : row.total) / axis.max * plotHeight;
            if (mode !== 'grouped') group.append(svg('text',{x:center,y:bottom-visibleHeight-9,'text-anchor':'middle',class:'pl-total-label'},number.format(row.total)));
            group.append(svg('text',{x:center,y:height-9,'text-anchor':'middle',class:'pl-axis-label'},row.label));
            group.append(svg('rect',{x:center-slot*.42,y:top-15,width:slot*.84,height:plotHeight+41,fill:'transparent','pointer-events':'all'}));
            const tooltipRows = config.series.map((item,i)=>({label:item.label,value:number.format(row.values[i]),color:themes[themeFor(item)].color}));
            tooltipRows.push({label:'Total',value:`${number.format(row.total)} ${rowUnit}`});
            bindTooltip(group,row.periodLabel,tooltipRows,href ? 'Open the matching records' : 'Counts from the recorded dashboard data');
            chart.append(group);
            if (animate && !motion.matches && row.total > 0) {
                bars.style.transformOrigin = `${center}px ${bottom}px`;
                bars.animate([{transform:'scaleY(0)'},{transform:'scaleY(1)'}],{duration:520,easing:'cubic-bezier(.2,.7,.2,1)'});
            }
        });
        canvas.replaceChildren(chart);
        root.querySelector('[data-period-total]').textContent = number.format(total);
        root.querySelector('[data-period-label]').textContent = selected.length ? `${selected[0].periodLabel} – ${selected.at(-1).periodLabel}` : 'No dates';
        root.querySelector('[data-chart-empty]').hidden = total !== 0;
        renderDataTable(root,selected,config.series,config.unit,config.singularUnit);
        root.dataset.analyticsState = 'ready';
        root.setAttribute('aria-busy','false');
        if (announce) root.querySelector('[data-chart-announcement]').textContent = `${number.format(total)} ${config.unit}; ${selected[0]?.periodLabel || ''} to ${selected.at(-1)?.periodLabel || ''}. ${mode === 'total' ? 'Total' : 'Breakdown'} view.`;
        previousWidth = width;
    };
    root.querySelectorAll('button,select').forEach(control=>control.disabled=false);
    root.querySelectorAll('[data-chart-mode]').forEach(button=>button.addEventListener('click',()=>{
        if(mode === button.dataset.chartMode) return;
        mode=button.dataset.chartMode;
        root.querySelectorAll('[data-chart-mode]').forEach(other=>other.setAttribute('aria-pressed',String(other===button)));
        render(true);
    }));
    const onPeriod = event => {
        if (Number(from.value)>Number(to.value)) {
            if(event.target===from) to.value=from.value;
            else from.value=to.value;
        }
        render(true);
    };
    from.addEventListener('change',onPeriod);
    to.addEventListener('change',onPeriod);
    root.querySelectorAll('[data-period-preset]').forEach(button=>button.addEventListener('click',()=>{
        from.value=String(button.dataset.periodPreset==='all' ? 0 : Math.max(0,rows.length-Number(button.dataset.periodPreset)));
        to.value=String(rows.length-1);
        render(true);
        root.querySelector('[data-analytics-period]').open=false;
    }));
    render();
    const observer = new ResizeObserver(()=>{
        if(Math.abs(canvas.clientWidth-previousWidth)<1)return;
        cancelAnimationFrame(resizeFrame);
        resizeFrame=requestAnimationFrame(()=>render(false,false));
    });
    observer.observe(canvas);
}

function initRadial(root) {
    if(root.dataset.analyticsReady)return;
    root.dataset.analyticsReady='true';
    root.querySelectorAll('[data-radial-item]').forEach(item=>{
        const legend=root.querySelector(`[data-radial-legend="${item.dataset.radialItem}"]`);
        const progress=item.querySelector('.pl-ring-value');
        const rows=[{label:'Count',value:number.format(Number(item.dataset.tooltipValue))},{label:'Share',value:`${item.dataset.tooltipPercent}%`}];
        for(const target of new Set([item.closest('a')||item,legend].filter(Boolean))){
            bindTooltip(target,item.dataset.tooltipTitle,rows);
            const highlight=()=>{root.classList.add('is-highlighted');progress?.classList.add('is-active');legend?.classList.add('is-active');};
            const clear=()=>{root.classList.remove('is-highlighted');progress?.classList.remove('is-active');legend?.classList.remove('is-active');};
            target.addEventListener('pointerenter',highlight);target.addEventListener('focusin',highlight);
            target.addEventListener('pointerleave',clear);target.addEventListener('focusout',clear);
        }
    });
}

function initCounters() {
    const active = new Map();
    const observer=new IntersectionObserver(entries=>{
        entries.forEach(entry=>{
            if(!entry.isIntersecting)return;
            observer.unobserve(entry.target);
            const element=entry.target;
            const value=Number(element.dataset.countUp);
            if(!Number.isFinite(value)||motion.matches||value===0)return;
            const start=performance.now();
            const tick=now=>{
                const progress=Math.min(1,(now-start)/650);
                element.textContent=number.format(Math.round(value*(1-(1-progress)**3)));
                if(progress<1)active.set(element,requestAnimationFrame(tick));else active.delete(element);
            };
            active.set(element,requestAnimationFrame(tick));
        });
    },{threshold:.15});
    document.querySelectorAll('[data-count-up]').forEach(element=>observer.observe(element));
    motion.addEventListener('change',()=>{
        if(!motion.matches)return;
        active.forEach((frame,element)=>{cancelAnimationFrame(frame);element.textContent=number.format(Number(element.dataset.countUp));});
        active.clear();
        document.querySelectorAll('.pl-analytics,.pl-pipeline').forEach(root=>root.getAnimations({subtree:true}).forEach(animation=>animation.finish()));
    });
    const appear=new IntersectionObserver(entries=>entries.forEach(entry=>{
        if(!entry.isIntersecting)return;
        entry.target.classList.add('is-animated');appear.unobserve(entry.target);
    }),{threshold:.15});
    document.querySelectorAll('[data-analytics-radial],[data-analytics-pipeline]').forEach(element=>appear.observe(element));
}

function init() {
    document.querySelectorAll('[data-analytics-columns]').forEach(root=>{
        try { initColumns(root); } catch(error) {
            console.error('Dashboard chart failed:',error);
            root.dataset.analyticsState='error';root.setAttribute('aria-busy','false');
            root.querySelector('[data-chart-skeleton]')?.remove();
            root.querySelector('[data-chart-error]').hidden=false;
            root.querySelector('.pl-chart-data').open=true;
        }
    });
    document.querySelectorAll('[data-analytics-radial]').forEach(initRadial);
    initCounters();
    document.addEventListener('keydown',event=>{if(event.key==='Escape'){hideTooltip();document.querySelectorAll('[data-analytics-period][open]').forEach(menu=>menu.open=false);}});
    document.addEventListener('pointerdown',event=>{document.querySelectorAll('[data-analytics-period][open]').forEach(menu=>{if(!menu.contains(event.target))menu.open=false;});if(!event.target.closest('.pl-analytics'))hideTooltip();});
    window.addEventListener('scroll',hideTooltip,{passive:true,capture:true});
}

if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
