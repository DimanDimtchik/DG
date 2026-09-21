/**
 * Rezeptur R7 — Ablaufgraph (SVG/Vanilla).
 * Liest BOM + Routing aus #dg-recipe-form und zeichnet einen Lese-Graphen.
 */
(function () {
  'use strict';

  var root = document.getElementById('dg-recipe-flow');
  var form = document.getElementById('dg-recipe-form');
  if (!root || !form) {
    return;
  }

  var canvas = root.querySelector('[data-rf-canvas]');
  var emptyEl = root.querySelector('[data-rf-empty]');
  if (!canvas) {
    return;
  }

  var wcNames = {};
  try {
    var raw = root.getAttribute('data-wc-names') || '{}';
    wcNames = JSON.parse(raw) || {};
  } catch (e) {
    wcNames = {};
  }

  var timer = null;

  function escapeXml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function truncate(s, n) {
    s = String(s || '').trim();
    if (s.length <= n) {
      return s;
    }
    return s.slice(0, n - 1) + '…';
  }

  function readBom() {
    var lines = [];
    form.querySelectorAll('#dg-recipe-bom tbody tr').forEach(function (tr) {
      var labelEl = tr.querySelector('[name*="[material_label]"]');
      var qtyEl = tr.querySelector('[name*="[qty]"]');
      var unitEl = tr.querySelector('[name*="[unit]"]');
      var label = labelEl ? String(labelEl.value || '').trim() : '';
      if (!label) {
        return;
      }
      lines.push({
        label: label,
        qty: qtyEl ? String(qtyEl.value || '') : '',
        unit: unitEl ? String(unitEl.value || '') : ''
      });
    });
    return lines;
  }

  function readRouting() {
    var steps = [];
    form.querySelectorAll('#dg-recipe-routing tbody tr').forEach(function (tr, idx) {
      var labelEl = tr.querySelector('[name*="[label]"]');
      var wcEl = tr.querySelector('[name*="[work_center_id]"]');
      var setupEl = tr.querySelector('[name*="[setup_min]"]');
      var runEl = tr.querySelector('[name*="[run_min]"]');
      var wcId = wcEl ? parseInt(wcEl.value, 10) || 0 : 0;
      var label = labelEl ? String(labelEl.value || '').trim() : '';
      var wcName = wcId > 0 ? (wcNames[String(wcId)] || ('Maschine #' + wcId)) : '';
      if (!label && !wcName && !(setupEl && setupEl.value) && !(runEl && runEl.value)) {
        return;
      }
      steps.push({
        index: idx + 1,
        label: label || ('Schritt ' + (idx + 1)),
        machine: wcName || '—',
        setup: setupEl ? String(setupEl.value || '0') : '0',
        run: runEl ? String(runEl.value || '0') : '0'
      });
    });
    return steps;
  }

  function titleOfRecipe() {
    var el = form.querySelector('[name="title"]');
    var t = el ? String(el.value || '').trim() : '';
    return t || 'Fertigprodukt';
  }

  function layout(bom, steps) {
    var nodeW = 168;
    var nodeH = 58;
    var gapX = 48;
    var gapY = 14;
    var pad = 20;
    var matColX = pad;
    var mergeX = pad + nodeW + gapX;
    var stepStartX = mergeX + 36;
    var matCount = Math.max(bom.length, 1);
    var matBlockH = matCount * nodeH + Math.max(0, matCount - 1) * gapY;
    var stepCount = Math.max(steps.length, 1);
    var centerY = pad + Math.max(matBlockH, nodeH) / 2;

    var nodes = [];
    var edges = [];

    // Material nodes (left column)
    var matNodes = [];
    if (bom.length === 0) {
      matNodes.push({
        id: 'mat-empty',
        kind: 'mat',
        x: matColX,
        y: centerY - nodeH / 2,
        title: 'Keine Materialien',
        meta: 'BOM leer'
      });
    } else {
      var startY = centerY - matBlockH / 2;
      bom.forEach(function (m, i) {
        var id = 'mat-' + i;
        var y = startY + i * (nodeH + gapY);
        matNodes.push({
          id: id,
          kind: 'mat',
          x: matColX,
          y: y,
          title: truncate(m.label, 22),
          meta: truncate((m.qty || '') + ' ' + (m.unit || ''), 20)
        });
      });
    }
    nodes = nodes.concat(matNodes);

    // Routing steps (horizontal)
    var stepNodes = [];
    if (steps.length === 0) {
      stepNodes.push({
        id: 'step-empty',
        kind: 'step',
        x: stepStartX,
        y: centerY - nodeH / 2,
        title: 'Kein Arbeitsplan',
        meta: 'Routing leer'
      });
    } else {
      steps.forEach(function (s, i) {
        stepNodes.push({
          id: 'step-' + i,
          kind: 'step',
          x: stepStartX + i * (nodeW + gapX),
          y: centerY - nodeH / 2,
          title: truncate(s.label, 22),
          meta: truncate(s.machine + ' · R' + s.setup + '/L' + s.run, 26)
        });
      });
    }
    nodes = nodes.concat(stepNodes);

    // End node
    var lastStep = stepNodes[stepNodes.length - 1];
    var endNode = {
      id: 'end',
      kind: 'end',
      x: lastStep.x + nodeW + gapX,
      y: centerY - nodeH / 2,
      title: truncate(titleOfRecipe(), 22),
      meta: 'Fertig'
    };
    nodes.push(endNode);

    // Edges: materials → first step
    var firstStep = stepNodes[0];
    matNodes.forEach(function (m) {
      edges.push({
        x1: m.x + nodeW,
        y1: m.y + nodeH / 2,
        x2: firstStep.x,
        y2: firstStep.y + nodeH / 2
      });
    });
    // step → step
    for (var i = 0; i < stepNodes.length - 1; i++) {
      var a = stepNodes[i];
      var b = stepNodes[i + 1];
      edges.push({
        x1: a.x + nodeW,
        y1: a.y + nodeH / 2,
        x2: b.x,
        y2: b.y + nodeH / 2
      });
    }
    // last → end
    edges.push({
      x1: lastStep.x + nodeW,
      y1: lastStep.y + nodeH / 2,
      x2: endNode.x,
      y2: endNode.y + nodeH / 2
    });

    var width = endNode.x + nodeW + pad;
    var height = Math.max(pad * 2 + matBlockH, pad * 2 + nodeH, endNode.y + nodeH + pad);

    return { nodes: nodes, edges: edges, width: width, height: height, nodeW: nodeW, nodeH: nodeH };
  }

  function render() {
    var bom = readBom();
    var steps = readRouting();
    var hasContent = bom.length > 0 || steps.length > 0;
    if (emptyEl) {
      emptyEl.hidden = hasContent;
    }
    var L = layout(bom, steps);
    var parts = [];
    parts.push('<svg xmlns="http://www.w3.org/2000/svg" width="' + L.width + '" height="' + L.height + '" viewBox="0 0 ' + L.width + ' ' + L.height + '" role="img" aria-label="Ablaufgraph Rezeptur">');
    parts.push('<defs><marker id="dg-rf-arrow" markerWidth="8" markerHeight="8" refX="7" refY="3" orient="auto"><path d="M0,0 L7,3 L0,6 Z" fill="#8a93a3"/></marker></defs>');
    L.edges.forEach(function (e) {
      var mx = (e.x1 + e.x2) / 2;
      parts.push(
        '<path class="dg-rf-edge" d="M' + e.x1 + ',' + e.y1 +
          ' C' + mx + ',' + e.y1 + ' ' + mx + ',' + e.y2 + ' ' + e.x2 + ',' + e.y2 + '"/>'
      );
    });
    L.nodes.forEach(function (n) {
      parts.push('<g class="dg-rf-node dg-rf-node--' + n.kind + '" transform="translate(' + n.x + ',' + n.y + ')">');
      parts.push('<rect width="' + L.nodeW + '" height="' + L.nodeH + '"/>');
      parts.push('<text class="dg-rf-node__title" x="12" y="22">' + escapeXml(n.title) + '</text>');
      parts.push('<text class="dg-rf-node__meta" x="12" y="40">' + escapeXml(n.meta) + '</text>');
      parts.push('</g>');
    });
    parts.push('</svg>');
    canvas.innerHTML = parts.join('');
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(render, 120);
  }

  form.addEventListener('input', schedule);
  form.addEventListener('change', schedule);
  form.addEventListener('click', function (e) {
    if (e.target && (e.target.closest('[data-bom-remove]') || e.target.closest('[data-routing-remove]') ||
        e.target.id === 'dg-recipe-bom-add' || e.target.id === 'dg-recipe-routing-add')) {
      schedule();
    }
  });

  render();
})();
