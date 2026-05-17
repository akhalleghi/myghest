/**
 * نمودار خطی تعاملی داشبورد ادمین — بدون وابستگی CDN (SVG + vanilla JS).
 */

function faDigits(value) {
    const s = String(value ?? '');
    const fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    const ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    return s
        .replace(/[0-9]/g, (d) => fa[Number(d)])
        .replace(/[٠-٩]/g, (d) => fa[ar.indexOf(d)] ?? d);
}

function formatNumberFa(n) {
    return faDigits(Number(n || 0).toLocaleString('en-US'));
}

function formatAxisFa(n) {
    const v = Number(n || 0);
    if (v >= 1_000_000_000) {
        return faDigits((v / 1_000_000_000).toFixed(v >= 10_000_000_000 ? 0 : 1)) + 'B';
    }
    if (v >= 1_000_000) {
        return faDigits((v / 1_000_000).toFixed(v >= 10_000_000 ? 0 : 1)) + 'M';
    }
    if (v >= 1_000) {
        return faDigits((v / 1_000).toFixed(v >= 10_000 ? 0 : 1)) + 'K';
    }

    return faDigits(Math.round(v));
}

function hexToRgb(hex) {
    const h = String(hex || '#2563eb').replace('#', '');
    if (h.length !== 6) {
        return { r: 37, g: 99, b: 235 };
    }

    return {
        r: parseInt(h.slice(0, 2), 16),
        g: parseInt(h.slice(2, 4), 16),
        b: parseInt(h.slice(4, 6), 16),
    };
}

/**
 * @param {HTMLElement} host
 * @param {{ color?: string, title?: string, series?: Array<{label:string,period:string,value:number,valueLabel:string}> }} config
 */
function renderDashLineChart(host, config) {
    const series = Array.isArray(config?.series) ? config.series : [];
    const color = config?.color || '#0ea5e9';
    const rgb = hexToRgb(color);
    const title = config?.title || '';

    host.innerHTML = '';
    host.classList.add('dash-line-chart');
    host.setAttribute('role', 'img');
    host.setAttribute('aria-label', title || 'نمودار');

    if (series.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'dash-line-chart__empty';
        empty.textContent = 'داده‌ای برای نمایش وجود ندارد.';
        host.appendChild(empty);

        return;
    }

    const wrap = document.createElement('div');
    wrap.className = 'dash-line-chart__wrap';

    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'dash-line-chart__svg');
    svg.setAttribute('viewBox', '0 0 480 200');
    svg.setAttribute('preserveAspectRatio', 'none');

    const tooltip = document.createElement('div');
    tooltip.className = 'dash-line-chart__tooltip';
    tooltip.hidden = true;

    const plot = document.createElement('div');
    plot.className = 'dash-line-chart__plot';
    plot.appendChild(svg);
    plot.appendChild(tooltip);

    wrap.appendChild(plot);
    host.appendChild(wrap);

    const W = 480;
    const H = 200;
    const pad = { top: 16, right: 12, bottom: 36, left: 48 };
    const innerW = W - pad.left - pad.right;
    const innerH = H - pad.top - pad.bottom;

    const values = series.map((p) => Number(p.value || 0));
    const maxVal = Math.max(1, ...values);
    const niceMax = Math.ceil(maxVal * 1.08);
    const gridLines = 4;

    const ns = 'http://www.w3.org/2000/svg';

    function el(name, attrs) {
        const node = document.createElementNS(ns, name);
        if (attrs) {
            Object.keys(attrs).forEach((k) => node.setAttribute(k, attrs[k]));
        }

        return node;
    }

    const defs = el('defs');
    const gradId = 'dash-grad-' + Math.random().toString(36).slice(2, 9);
    const grad = el('linearGradient', { id: gradId, x1: '0', y1: '0', x2: '0', y2: '1' });
    grad.appendChild(el('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': '0.28' }));
    grad.appendChild(el('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': '0.02' }));
    defs.appendChild(grad);
    svg.appendChild(defs);

    const gGrid = el('g', { class: 'dash-line-chart__grid' });
    for (let i = 0; i <= gridLines; i++) {
        const y = pad.top + (innerH * i) / gridLines;
        gGrid.appendChild(el('line', {
            x1: String(pad.left),
            y1: String(y),
            x2: String(W - pad.right),
            y2: String(y),
            class: 'dash-line-chart__grid-line',
        }));
        const tickVal = niceMax - (niceMax * i) / gridLines;
        const label = el('text', {
            x: String(pad.left - 8),
            y: String(y + 4),
            class: 'dash-line-chart__axis-y',
            'text-anchor': 'end',
        });
        label.textContent = formatAxisFa(tickVal);
        gGrid.appendChild(label);
    }
    svg.appendChild(gGrid);

    const coords = series.map((point, i) => {
        const x = pad.left + (series.length > 1 ? (innerW * i) / (series.length - 1) : innerW / 2);
        const ratio = Number(point.value || 0) / niceMax;
        const y = pad.top + innerH * (1 - ratio);

        return { x, y, point, i };
    });

    const areaPath =
        `M ${coords[0].x} ${pad.top + innerH} ` +
        coords.map((c) => `L ${c.x} ${c.y}`).join(' ') +
        ` L ${coords[coords.length - 1].x} ${pad.top + innerH} Z`;

    svg.appendChild(el('path', { d: areaPath, fill: `url(#${gradId})`, class: 'dash-line-chart__area' }));

    const linePath = coords.map((c, idx) => `${idx === 0 ? 'M' : 'L'} ${c.x} ${c.y}`).join(' ');
    svg.appendChild(el('path', {
        d: linePath,
        fill: 'none',
        stroke: color,
        'stroke-width': '2.75',
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
        class: 'dash-line-chart__line',
    }));

    const crosshair = el('line', {
        class: 'dash-line-chart__crosshair',
        y1: String(pad.top),
        y2: String(pad.top + innerH),
        stroke: color,
        'stroke-width': '1',
        'stroke-dasharray': '4 4',
        opacity: '0',
    });
    svg.appendChild(crosshair);

    const gDots = el('g', { class: 'dash-line-chart__dots' });
    const hitTargets = [];

    coords.forEach((c) => {
        const outer = el('circle', {
            cx: String(c.x),
            cy: String(c.y),
            r: '10',
            class: 'dash-line-chart__hit',
            fill: 'transparent',
            'data-index': String(c.i),
        });
        const dot = el('circle', {
            cx: String(c.x),
            cy: String(c.y),
            r: '4.5',
            class: 'dash-line-chart__dot',
            fill: '#fff',
            stroke: color,
            'stroke-width': '2.5',
            'data-index': String(c.i),
        });
        gDots.appendChild(outer);
        gDots.appendChild(dot);
        hitTargets.push({ outer, dot, ...c });

        const xLabel = el('text', {
            x: String(c.x),
            y: String(H - 10),
            class: 'dash-line-chart__axis-x',
            'text-anchor': 'middle',
        });
        xLabel.textContent = c.point.label || '';
        svg.appendChild(xLabel);
    });
    svg.appendChild(gDots);

    let activeIndex = -1;

    function showTooltip(index, clientX, clientY) {
        const c = coords[index];
        if (!c) {
            return;
        }

        activeIndex = index;
        crosshair.setAttribute('x1', String(c.x));
        crosshair.setAttribute('x2', String(c.x));
        crosshair.setAttribute('opacity', '0.55');

        hitTargets.forEach((t, i) => {
            const on = i === index;
            t.dot.setAttribute('r', on ? '6.5' : '4.5');
            t.dot.setAttribute('stroke-width', on ? '3' : '2.5');
            t.outer.setAttribute('r', on ? '12' : '10');
        });

        tooltip.innerHTML =
            '<div class="dash-line-chart__tooltip-period">' +
            (c.point.period || c.point.label || '') +
            '</div>' +
            '<div class="dash-line-chart__tooltip-value">' +
            (c.point.valueLabel || formatNumberFa(c.point.value) + ' تومان') +
            '</div>' +
            (title ? '<div class="dash-line-chart__tooltip-meta">' + title + '</div>' : '');

        tooltip.hidden = false;

        const plotRect = plot.getBoundingClientRect();
        const hostRect = host.getBoundingClientRect();
        let left = clientX - hostRect.left + 14;
        let top = clientY - hostRect.top - 12;

        tooltip.style.left = '0';
        tooltip.style.top = '0';
        tooltip.style.visibility = 'hidden';
        tooltip.hidden = false;

        const tw = tooltip.offsetWidth;
        const th = tooltip.offsetHeight;

        if (left + tw > plotRect.width - 8) {
            left = clientX - hostRect.left - tw - 14;
        }
        if (top < 8) {
            top = 8;
        }
        if (top + th > plotRect.height - 8) {
            top = plotRect.height - th - 8;
        }

        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
        tooltip.style.visibility = 'visible';
        tooltip.style.setProperty('--tip-accent', color);
        tooltip.style.setProperty('--tip-rgb', `${rgb.r},${rgb.g},${rgb.b}`);
    }

    function hideTooltip() {
        activeIndex = -1;
        crosshair.setAttribute('opacity', '0');
        tooltip.hidden = true;
        hitTargets.forEach((t) => {
            t.dot.setAttribute('r', '4.5');
            t.dot.setAttribute('stroke-width', '2.5');
            t.outer.setAttribute('r', '10');
        });
    }

    function indexFromClientX(clientX) {
        const rect = svg.getBoundingClientRect();
        const rel = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
        const xSvg = pad.left + rel * innerW;
        let best = 0;
        let bestDist = Infinity;
        coords.forEach((c, i) => {
            const d = Math.abs(c.x - xSvg);
            if (d < bestDist) {
                bestDist = d;
                best = i;
            }
        });

        return best;
    }

    plot.addEventListener('mousemove', (e) => {
        showTooltip(indexFromClientX(e.clientX), e.clientX, e.clientY);
    });

    plot.addEventListener('mouseleave', hideTooltip);

    hitTargets.forEach((t) => {
        t.outer.addEventListener('mouseenter', (e) => {
            showTooltip(t.i, e.clientX, e.clientY);
        });
    });

    plot.addEventListener(
        'touchstart',
        (e) => {
            const touch = e.touches[0];
            if (!touch) {
                return;
            }
            showTooltip(indexFromClientX(touch.clientX), touch.clientX, touch.clientY);
        },
        { passive: true },
    );

    plot.addEventListener(
        'touchmove',
        (e) => {
            const touch = e.touches[0];
            if (!touch) {
                return;
            }
            showTooltip(indexFromClientX(touch.clientX), touch.clientX, touch.clientY);
        },
        { passive: true },
    );

    plot.addEventListener('touchend', hideTooltip);
}

function initAdminDashboardCharts() {
    const configEl = document.getElementById('dash-charts-config');
    if (!configEl) {
        return;
    }

    let config;
    try {
        config = JSON.parse(configEl.textContent || '{}');
    } catch {
        return;
    }

    const installmentsHost = document.getElementById('dash-chart-installments');
    const loansHost = document.getElementById('dash-chart-new-loans');

    if (installmentsHost && config.installments) {
        renderDashLineChart(installmentsHost, config.installments);
    }
    if (loansHost && config.new_loans) {
        renderDashLineChart(loansHost, config.new_loans);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminDashboardCharts);
} else {
    initAdminDashboardCharts();
}
