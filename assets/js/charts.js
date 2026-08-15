/* =====================================================================
   GreenAcres — SVG chart engine
   ---------------------------------------------------------------------
   A small purpose built charting library. It is written from scratch so
   the system has no external dependency and works with XAMPP offline.

   Supported: area, line, bar, stacked bar, donut, sparkline.

   Markup:
     <div data-chart>
       <script type="application/json">
         { "type": "area", "labels": [...], "series": [...] }
       </script>
     </div>
   ===================================================================== */
(function () {
  'use strict';

  const SVG_NS = 'http://www.w3.org/2000/svg';
  const PALETTE = ['#16874a', '#d9911f', '#2b78d4', '#7355d1', '#0e9c96', '#d6453f', '#63bd90', '#b47415'];

  /* ------------------------------------------------------------------
     Helpers
     ------------------------------------------------------------------ */
  function el(name, attrs, parent) {
    const node = document.createElementNS(SVG_NS, name);
    for (const key in attrs) {
      if (attrs[key] !== null && attrs[key] !== undefined) {
        node.setAttribute(key, attrs[key]);
      }
    }
    if (parent) parent.appendChild(node);
    return node;
  }

  function niceCeil(value) {
    if (value <= 0) return 10;
    const magnitude = Math.pow(10, Math.floor(Math.log10(value)));
    const normalised = value / magnitude;
    let step;
    if (normalised <= 1) step = 1;
    else if (normalised <= 2) step = 2;
    else if (normalised <= 2.5) step = 2.5;
    else if (normalised <= 5) step = 5;
    else step = 10;
    return step * magnitude;
  }

  function formatValue(value, cfg) {
    const decimals = cfg.decimals !== undefined ? cfg.decimals : 0;
    const prefix   = cfg.prefix || '';
    const suffix   = cfg.suffix || '';

    let out;
    if (cfg.compact && Math.abs(value) >= 1000) {
      out = Math.abs(value) >= 1000000
        ? (value / 1000000).toFixed(1).replace(/\.0$/, '') + 'M'
        : (value / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
    } else {
      out = value.toLocaleString(undefined, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
      });
    }
    return prefix + out + suffix;
  }

  /* Smooth path through points using a Catmull-Rom to bezier conversion. */
  function smoothPath(points, tension) {
    if (points.length < 2) return '';
    tension = tension === undefined ? 0.32 : tension;

    let d = 'M' + points[0].x + ',' + points[0].y;

    for (let i = 0; i < points.length - 1; i++) {
      const p0 = points[i - 1] || points[i];
      const p1 = points[i];
      const p2 = points[i + 1];
      const p3 = points[i + 2] || p2;

      const c1x = p1.x + (p2.x - p0.x) * tension / 2;
      const c1y = p1.y + (p2.y - p0.y) * tension / 2;
      const c2x = p2.x - (p3.x - p1.x) * tension / 2;
      const c2y = p2.y - (p3.y - p1.y) * tension / 2;

      d += ' C' + c1x + ',' + c1y + ' ' + c2x + ',' + c2y + ' ' + p2.x + ',' + p2.y;
    }
    return d;
  }

  /* ------------------------------------------------------------------
     Shared tooltip
     ------------------------------------------------------------------ */
  const Tip = {
    node: null,
    ensure() {
      if (!this.node) {
        this.node = document.createElement('div');
        this.node.className = 'chart-tip';
        document.body.appendChild(this.node);
      }
      return this.node;
    },
    show(ev, label, value) {
      const tip = this.ensure();
      tip.innerHTML = '';
      const small = document.createElement('span');
      small.className = 'chart-tip__label';
      small.textContent = label;
      tip.appendChild(small);
      tip.appendChild(document.createTextNode(value));
      tip.style.left = ev.clientX + 'px';
      tip.style.top  = ev.clientY + 'px';
      tip.classList.add('is-visible');
    },
    move(ev) {
      if (!this.node) return;
      this.node.style.left = ev.clientX + 'px';
      this.node.style.top  = ev.clientY + 'px';
    },
    hide() {
      if (this.node) this.node.classList.remove('is-visible');
    }
  };

  function attachTip(node, label, value) {
    node.addEventListener('mouseenter', (ev) => Tip.show(ev, label, value));
    node.addEventListener('mousemove',  (ev) => Tip.move(ev));
    node.addEventListener('mouseleave', () => Tip.hide());
  }

  /* ------------------------------------------------------------------
     Cartesian charts (area, line, bar, stacked bar)
     ------------------------------------------------------------------ */
  function renderCartesian(host, cfg) {
    const W = 760;
    const H = cfg.height || 280;
    const pad = { top: 18, right: 16, bottom: 34, left: 54 };

    const labels = cfg.labels || [];
    const series = cfg.series || [];
    const stacked = cfg.type === 'stacked-bar';
    const isBar = cfg.type === 'bar' || stacked;

    // Work out the value ceiling
    let maxValue = 0;
    if (stacked) {
      labels.forEach((_, i) => {
        let sum = 0;
        series.forEach((s) => { sum += Number(s.data[i]) || 0; });
        maxValue = Math.max(maxValue, sum);
      });
    } else {
      series.forEach((s) => {
        (s.data || []).forEach((v) => { maxValue = Math.max(maxValue, Number(v) || 0); });
      });
    }
    const ceiling = niceCeil(maxValue * 1.1) || 10;

    const plotW = W - pad.left - pad.right;
    const plotH = H - pad.top - pad.bottom;
    const xFor = (i) => pad.left + (labels.length <= 1
      ? plotW / 2
      : (plotW / (labels.length - 1)) * i);
    const yFor = (v) => pad.top + plotH - (Math.max(0, v) / ceiling) * plotH;

    const svg = el('svg', {
      class: 'chart',
      viewBox: '0 0 ' + W + ' ' + H,
      preserveAspectRatio: 'none',
      role: 'img',
      style: 'height:' + H + 'px'
    });
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');

    const defs = el('defs', {}, svg);

    // Horizontal grid + y axis labels
    const ticks = 4;
    for (let t = 0; t <= ticks; t++) {
      const value = (ceiling / ticks) * t;
      const y = yFor(value);
      el('line', { class: 'grid-line', x1: pad.left, y1: y, x2: W - pad.right, y2: y }, svg);
      const text = el('text', { x: pad.left - 10, y: y + 4, 'text-anchor': 'end' }, svg);
      text.textContent = formatValue(value, Object.assign({ compact: true }, cfg));
    }

    // X axis labels — thin them out when there are many
    const every = labels.length > 14 ? Math.ceil(labels.length / 12) : 1;
    labels.forEach((lab, i) => {
      if (i % every !== 0 && i !== labels.length - 1) return;
      const text = el('text', {
        x: isBar ? barGroupCentre(i) : xFor(i),
        y: H - pad.bottom + 20,
        'text-anchor': 'middle'
      }, svg);
      text.textContent = lab;
    });

    function barGroupCentre(i) {
      const slot = plotW / labels.length;
      return pad.left + slot * i + slot / 2;
    }

    // --- Bars ---------------------------------------------------------
    if (isBar) {
      const slot = plotW / Math.max(1, labels.length);
      const groupCount = stacked ? 1 : series.length;
      const barW = Math.max(6, Math.min(34, (slot * 0.62) / groupCount));
      const groupW = barW * groupCount + (groupCount - 1) * 4;

      labels.forEach((lab, i) => {
        let stackTop = pad.top + plotH;

        series.forEach((s, si) => {
          const value = Number(s.data[i]) || 0;
          const colour = s.color || PALETTE[si % PALETTE.length];
          const barH = (value / ceiling) * plotH;

          let x, y;
          if (stacked) {
            x = barGroupCentre(i) - barW / 2;
            y = stackTop - barH;
            stackTop = y;
          } else {
            x = barGroupCentre(i) - groupW / 2 + si * (barW + 4);
            y = pad.top + plotH - barH;
          }

          // Gradient per series for a sense of volume
          const gradId = 'bg' + Math.random().toString(36).slice(2, 8);
          const grad = el('linearGradient', { id: gradId, x1: '0', y1: '0', x2: '0', y2: '1' }, defs);
          el('stop', { offset: '0%',   'stop-color': colour, 'stop-opacity': '1' }, grad);
          el('stop', { offset: '100%', 'stop-color': colour, 'stop-opacity': '.62' }, grad);

          const rect = el('rect', {
            class: 'chart-bar',
            x: x,
            y: barH < 1 ? pad.top + plotH - 1 : y,
            width: barW,
            height: Math.max(1, barH),
            rx: Math.min(5, barW / 2),
            fill: 'url(#' + gradId + ')'
          }, svg);

          attachTip(rect, lab + ' · ' + (s.name || ''), formatValue(value, cfg));

          // Grow-in animation
          const anim = el('animate', {
            attributeName: 'height',
            from: 0,
            to: Math.max(1, barH),
            dur: '.6s',
            fill: 'freeze',
            begin: (0.03 * i + 0.05 * si) + 's'
          }, rect);
          el('animate', {
            attributeName: 'y',
            from: pad.top + plotH,
            to: barH < 1 ? pad.top + plotH - 1 : y,
            dur: '.6s',
            fill: 'freeze',
            begin: (0.03 * i + 0.05 * si) + 's'
          }, rect);
          void anim;
        });
      });
    }

    // --- Lines and areas ---------------------------------------------
    if (!isBar) {
      series.forEach((s, si) => {
        const colour = s.color || PALETTE[si % PALETTE.length];
        const points = (s.data || []).map((v, i) => ({ x: xFor(i), y: yFor(Number(v) || 0) }));
        if (!points.length) return;

        const path = smoothPath(points, cfg.tension);

        if (cfg.type === 'area') {
          const gradId = 'ag' + Math.random().toString(36).slice(2, 8);
          const grad = el('linearGradient', { id: gradId, x1: '0', y1: '0', x2: '0', y2: '1' }, defs);
          el('stop', { offset: '0%',   'stop-color': colour, 'stop-opacity': '.34' }, grad);
          el('stop', { offset: '100%', 'stop-color': colour, 'stop-opacity': '0' }, grad);

          const areaD = path +
            ' L' + points[points.length - 1].x + ',' + (pad.top + plotH) +
            ' L' + points[0].x + ',' + (pad.top + plotH) + ' Z';

          el('path', { d: areaD, fill: 'url(#' + gradId + ')' }, svg);
        }

        const stroke = el('path', {
          d: path,
          fill: 'none',
          stroke: colour,
          'stroke-width': 2.6,
          'stroke-linecap': 'round',
          'stroke-linejoin': 'round',
          filter: 'drop-shadow(0 4px 8px ' + colour + '55)'
        }, svg);

        // Draw-on animation
        try {
          const length = stroke.getTotalLength ? stroke.getTotalLength() : 0;
          if (length) {
            stroke.setAttribute('stroke-dasharray', length);
            stroke.setAttribute('stroke-dashoffset', length);
            el('animate', {
              attributeName: 'stroke-dashoffset',
              from: length, to: 0, dur: '1.1s', fill: 'freeze',
              calcMode: 'spline', keySplines: '.16 1 .3 1', keyTimes: '0;1'
            }, stroke);
          }
        } catch (e) {}

        // Interactive points
        points.forEach((p, i) => {
          el('circle', { cx: p.x, cy: p.y, r: 3.4, fill: 'var(--surface)', stroke: colour, 'stroke-width': 2.2 }, svg);
          const hit = el('circle', {
            class: 'chart-dot', cx: p.x, cy: p.y, r: 12, fill: 'transparent'
          }, svg);
          attachTip(hit, labels[i] + ' · ' + (s.name || ''), formatValue(Number(s.data[i]) || 0, cfg));
        });
      });
    }

    // Baseline
    el('line', {
      class: 'axis-line',
      x1: pad.left, y1: pad.top + plotH, x2: W - pad.right, y2: pad.top + plotH
    }, svg);

    host.appendChild(svg);
    if (cfg.legend !== false && series.length > 1) host.appendChild(buildLegend(series, cfg));
  }

  /* ------------------------------------------------------------------
     Donut
     ------------------------------------------------------------------ */
  function renderDonut(host, cfg) {
    const size = cfg.size || 230;
    const stroke = cfg.thickness || 30;
    const r = (size - stroke) / 2 - 4;
    const cx = size / 2, cy = size / 2;

    const data = (cfg.data || []).filter((d) => Number(d.value) > 0);
    const total = data.reduce((sum, d) => sum + Number(d.value), 0);

    const wrap = document.createElement('div');
    wrap.className = 'donut-wrap';

    const svg = el('svg', {
      class: 'chart',
      viewBox: '0 0 ' + size + ' ' + size,
      style: 'max-width:' + size + 'px;margin:0 auto'
    });

    // Track
    el('circle', {
      cx: cx, cy: cy, r: r, fill: 'none',
      stroke: 'var(--surface-3)', 'stroke-width': stroke
    }, svg);

    if (total > 0) {
      const circumference = 2 * Math.PI * r;
      let offset = 0;

      // The whole ring is rotated by a group rather than by each arc, so
      // that the hover scale on a slice stays centred on the circle.
      const rotor = el('g', { transform: 'rotate(-90 ' + cx + ' ' + cy + ')' }, svg);

      data.forEach((d, i) => {
        const value = Number(d.value);
        const fraction = value / total;
        const colour = d.color || PALETTE[i % PALETTE.length];

        const arc = el('circle', {
          class: 'chart-slice',
          cx: cx, cy: cy, r: r,
          fill: 'none',
          stroke: colour,
          'stroke-width': stroke,
          'stroke-linecap': data.length > 1 ? 'butt' : 'round',
          'stroke-dasharray': (fraction * circumference) + ' ' + circumference,
          'stroke-dashoffset': -offset
        }, rotor);

        el('animate', {
          attributeName: 'stroke-dasharray',
          from: '0 ' + circumference,
          to: (fraction * circumference) + ' ' + circumference,
          dur: '.85s', fill: 'freeze',
          begin: (i * 0.09) + 's',
          calcMode: 'spline', keySplines: '.16 1 .3 1', keyTimes: '0;1'
        }, arc);

        attachTip(arc, d.label,
          formatValue(value, cfg) + '  (' + (fraction * 100).toFixed(1) + '%)');

        offset += fraction * circumference;
      });
    }

    wrap.appendChild(svg);

    // Centre label
    const centre = document.createElement('div');
    centre.className = 'donut-center';
    centre.innerHTML = '<strong></strong><span></span>';
    centre.querySelector('strong').textContent =
      cfg.centerValue !== undefined ? cfg.centerValue : formatValue(total, Object.assign({ compact: true }, cfg));
    centre.querySelector('span').textContent = cfg.centerLabel || 'Total';
    wrap.appendChild(centre);

    host.appendChild(wrap);

    if (cfg.legend !== false) {
      host.appendChild(buildLegend(
        data.map((d, i) => ({
          name: d.label,
          color: d.color || PALETTE[i % PALETTE.length],
          total: Number(d.value)
        })),
        cfg,
        true
      ));
    }
  }

  /* ------------------------------------------------------------------
     Sparkline
     ------------------------------------------------------------------ */
  function renderSparkline(host, cfg) {
    const W = 120, H = cfg.height || 34;
    const values = (cfg.data || []).map(Number);
    if (values.length < 2) return;

    const max = Math.max.apply(null, values);
    const min = Math.min.apply(null, values);
    const span = (max - min) || 1;
    const colour = cfg.color || '#16874a';

    const points = values.map((v, i) => ({
      x: (W / (values.length - 1)) * i,
      y: H - 3 - ((v - min) / span) * (H - 8)
    }));

    const svg = el('svg', {
      class: 'chart',
      viewBox: '0 0 ' + W + ' ' + H,
      preserveAspectRatio: 'none',
      style: 'height:' + H + 'px;width:100%'
    });

    const defs = el('defs', {}, svg);
    const gradId = 'sp' + Math.random().toString(36).slice(2, 8);
    const grad = el('linearGradient', { id: gradId, x1: '0', y1: '0', x2: '0', y2: '1' }, defs);
    el('stop', { offset: '0%',   'stop-color': colour, 'stop-opacity': '.32' }, grad);
    el('stop', { offset: '100%', 'stop-color': colour, 'stop-opacity': '0' }, grad);

    const path = smoothPath(points, 0.3);
    el('path', { d: path + ' L' + W + ',' + H + ' L0,' + H + ' Z', fill: 'url(#' + gradId + ')' }, svg);
    el('path', { d: path, fill: 'none', stroke: colour, 'stroke-width': 2, 'stroke-linecap': 'round' }, svg);

    const last = points[points.length - 1];
    el('circle', { cx: last.x - 1.5, cy: last.y, r: 2.6, fill: colour }, svg);

    host.appendChild(svg);
  }

  /* ------------------------------------------------------------------
     Legend
     ------------------------------------------------------------------ */
  function buildLegend(series, cfg, showValues) {
    const legend = document.createElement('div');
    legend.className = 'legend';

    series.forEach((s, i) => {
      const total = s.total !== undefined
        ? s.total
        : (s.data || []).reduce((sum, v) => sum + (Number(v) || 0), 0);

      const item = document.createElement('div');
      item.className = 'legend__item';
      if (showValues) item.style.width = '100%';

      const swatch = document.createElement('span');
      swatch.className = 'legend__swatch';
      swatch.style.background = s.color || PALETTE[i % PALETTE.length];

      const name = document.createElement('span');
      name.textContent = s.name;

      item.appendChild(swatch);
      item.appendChild(name);

      if (showValues) {
        const value = document.createElement('span');
        value.className = 'legend__value';
        value.textContent = formatValue(total, Object.assign({ compact: true }, cfg));
        item.appendChild(value);
      }

      legend.appendChild(item);
    });

    return legend;
  }

  /* ------------------------------------------------------------------
     Boot — find every [data-chart] host and draw it
     ------------------------------------------------------------------ */
  function readConfig(host) {
    const script = host.querySelector('script[type="application/json"]');
    if (script) {
      try { return JSON.parse(script.textContent); }
      catch (e) { console.error('Chart JSON is not valid', e, host); return null; }
    }
    if (host.dataset.chart && host.dataset.chart !== '') {
      try { return JSON.parse(host.dataset.chart); }
      catch (e) { console.error('Chart JSON is not valid', e, host); return null; }
    }
    return null;
  }

  function renderAll() {
    document.querySelectorAll('[data-chart]').forEach((host) => {
      const cfg = readConfig(host);
      if (!cfg) return;

      host.innerHTML = '';

      switch (cfg.type) {
        case 'donut':
        case 'pie':
          renderDonut(host, cfg); break;
        case 'sparkline':
          renderSparkline(host, cfg); break;
        default:
          renderCartesian(host, cfg); break;
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderAll);
  } else {
    renderAll();
  }

  // Redraw on theme change so the surface colours stay in step
  const observer = new MutationObserver((records) => {
    records.forEach((r) => {
      if (r.attributeName === 'data-theme') renderAll();
    });
  });
  observer.observe(document.documentElement, { attributes: true });

  window.GACharts = { renderAll: renderAll, PALETTE: PALETTE };
})();
