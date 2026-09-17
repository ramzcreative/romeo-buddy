// The icon drawing rules from themes/_base/src/CLAUDE.md § Icons, as checks a build can run without a browser:
// rule 2 (longest drawn side exactly 20, centred in the 24 box, half the stroke included) and rule 3 (colour is
// only ever currentColor). Rule 1 (the 24 box) is checked too, since rule 2 is measured against it.
//
// The drawn bounds are computed from the SVG itself — path data, basic shapes and group transforms — by sampling
// every segment. Sampling slightly under-reads a curve's extreme, so measurements carry a small tolerance; they were
// checked against Chrome's getBBox() on all 37 `base` icons when this was written.

const SAMPLES = 48;
export const LIVE = 20;
export const TOLERANCE = 0.15;

const SKIP_CONTENT = new Set(['defs', 'clippath', 'mask', 'symbol', 'title', 'desc', 'metadata', 'style', 'lineargradient', 'radialgradient', 'pattern', 'filter']);
const COLOUR_ATTRS = ['fill', 'stroke', 'stop-color', 'color', 'flood-color', 'lighting-color'];
const ALLOWED_COLOURS = new Set(['currentcolor', 'none', 'inherit']);

// ---------- tiny XML walk (these files are simple: no CDATA, no entities that matter) ----------

function parseAttrs(src) {
	const attrs = {};
	for (const m of src.matchAll(/([\w:-]+)\s*=\s*("([^"]*)"|'([^']*)')/g)) {
		attrs[m[1].toLowerCase()] = m[3] ?? m[4] ?? '';
	}
	return attrs;
}

function walk(svg, visit) {
	const stack = [];
	const body = svg.replace(/<!--[\s\S]*?-->/g, '').replace(/<\?[\s\S]*?\?>/g, '');
	for (const m of body.matchAll(/<(\/?)([a-zA-Z][\w:-]*)([^>]*?)(\/?)>/g)) {
		const [, closing, rawName, attrSrc, selfClosing] = m;
		const name = rawName.toLowerCase();
		if (closing) {
			while (stack.length && stack.pop().name !== name);
			continue;
		}
		const node = { name, attrs: parseAttrs(attrSrc), parent: stack[stack.length - 1] ?? null };
		visit(node);
		if (!selfClosing) stack.push(node);
	}
}

// ---------- geometry ----------

const identity = () => [1, 0, 0, 1, 0, 0];
const multiply = (m, n) => [
	m[0] * n[0] + m[2] * n[1], m[1] * n[0] + m[3] * n[1],
	m[0] * n[2] + m[2] * n[3], m[1] * n[2] + m[3] * n[3],
	m[0] * n[4] + m[2] * n[5] + m[4], m[1] * n[4] + m[3] * n[5] + m[5],
];
const apply = (m, x, y) => [m[0] * x + m[2] * y + m[4], m[1] * x + m[3] * y + m[5]];

function parseTransform(src) {
	let m = identity();
	for (const t of (src ?? '').matchAll(/(matrix|translate|scale|rotate)\s*\(([^)]*)\)/g)) {
		const v = t[2].trim().split(/[\s,]+/).filter(Boolean).map(Number);
		let n = identity();
		if (t[1] === 'matrix') n = v.slice(0, 6);
		if (t[1] === 'translate') n = [1, 0, 0, 1, v[0] ?? 0, v[1] ?? 0];
		if (t[1] === 'scale') n = [v[0], 0, 0, v[1] ?? v[0], 0, 0];
		if (t[1] === 'rotate') {
			const a = ((v[0] ?? 0) * Math.PI) / 180, [cx, cy] = [v[1] ?? 0, v[2] ?? 0];
			n = multiply(multiply([1, 0, 0, 1, cx, cy], [Math.cos(a), Math.sin(a), -Math.sin(a), Math.cos(a), 0, 0]), [1, 0, 0, 1, -cx, -cy]);
		}
		m = multiply(m, n);
	}
	return m;
}

function inherited(node, attr) {
	for (let n = node; n; n = n.parent) {
		if (n.attrs[attr] !== undefined) return n.attrs[attr];
		const style = n.attrs.style?.match(new RegExp(`(?:^|;)\\s*${attr}\\s*:\\s*([^;]+)`));
		if (style) return style[1].trim();
	}
	return undefined;
}

function ctm(node) {
	const chain = [];
	for (let n = node; n; n = n.parent) chain.unshift(n);
	return chain.reduce((m, n) => multiply(m, parseTransform(n.attrs.transform)), identity());
}

function* pathPoints(d) {
	const tokens = [];
	const re = /([MmLlHhVvCcSsQqTtAaZz])|([-+]?(?:\d*\.\d+|\d+\.?)(?:[eE][-+]?\d+)?)/g;
	let cmd = '', args = 0;
	for (let i = 0; i < d.length;) {
		const c = d[i];
		if (/[\s,]/.test(c)) { i++; continue; }
		if (/[A-Za-z]/.test(c)) { tokens.push(c); cmd = c; args = 0; i++; continue; }
		// Arc flags (the 4th and 5th arc parameters) may be written without separators: "a2 2 0 011 1".
		if ((cmd === 'a' || cmd === 'A') && (args % 7 === 3 || args % 7 === 4) && (c === '0' || c === '1')) {
			tokens.push(Number(c)); args++; i++; continue;
		}
		re.lastIndex = i;
		const m = re.exec(d);
		if (!m || m.index !== i || m[2] === undefined) { i++; continue; }
		tokens.push(Number(m[2])); args++; i = re.lastIndex;
	}

	let x = 0, y = 0, sx = 0, sy = 0, lastCtrl = null, lastCmd = '';
	let i = 0;
	const num = () => tokens[i++];
	while (i < tokens.length) {
		let c = tokens[i];
		if (typeof c === 'string') i++; else c = lastCmd === 'M' ? 'L' : lastCmd === 'm' ? 'l' : lastCmd;
		const rel = c === c.toLowerCase();
		const C = c.toUpperCase();
		if (C === 'Z') { x = sx; y = sy; yield [x, y]; lastCmd = c; lastCtrl = null; continue; }
		if (C === 'M') { x = (rel ? x : 0) + num(); y = (rel ? y : 0) + num(); sx = x; sy = y; yield [x, y]; }
		else if (C === 'L') { x = (rel ? x : 0) + num(); y = (rel ? y : 0) + num(); yield [x, y]; }
		else if (C === 'H') { x = (rel ? x : 0) + num(); yield [x, y]; }
		else if (C === 'V') { y = (rel ? y : 0) + num(); yield [x, y]; }
		else if (C === 'C' || C === 'S' || C === 'Q' || C === 'T') {
			let c1, c2, end;
			const o = rel ? [x, y] : [0, 0];
			if (C === 'C') { c1 = [o[0] + num(), o[1] + num()]; c2 = [o[0] + num(), o[1] + num()]; end = [o[0] + num(), o[1] + num()]; }
			if (C === 'S') { c1 = lastCtrl && /[CcSs]/.test(lastCmd) ? [2 * x - lastCtrl[0], 2 * y - lastCtrl[1]] : [x, y]; c2 = [o[0] + num(), o[1] + num()]; end = [o[0] + num(), o[1] + num()]; }
			if (C === 'Q') { c1 = [o[0] + num(), o[1] + num()]; end = [o[0] + num(), o[1] + num()]; c2 = null; }
			if (C === 'T') { c1 = lastCtrl && /[QqTt]/.test(lastCmd) ? [2 * x - lastCtrl[0], 2 * y - lastCtrl[1]] : [x, y]; end = [o[0] + num(), o[1] + num()]; c2 = null; }
			for (let s = 1; s <= SAMPLES; s++) {
				const t = s / SAMPLES, u = 1 - t;
				yield c2
					? [u ** 3 * x + 3 * u * u * t * c1[0] + 3 * u * t * t * c2[0] + t ** 3 * end[0], u ** 3 * y + 3 * u * u * t * c1[1] + 3 * u * t * t * c2[1] + t ** 3 * end[1]]
					: [u * u * x + 2 * u * t * c1[0] + t * t * end[0], u * u * y + 2 * u * t * c1[1] + t * t * end[1]];
			}
			lastCtrl = c2 ?? c1; x = end[0]; y = end[1]; lastCmd = c; continue;
		}
		else if (C === 'A') {
			let rx = Math.abs(num()), ry = Math.abs(num());
			const phi = (num() * Math.PI) / 180, large = num(), sweep = num();
			const ex = (rel ? x : 0) + num(), ey = (rel ? y : 0) + num();
			yield* arcPoints(x, y, rx, ry, phi, large, sweep, ex, ey);
			x = ex; y = ey;
		}
		lastCmd = c; lastCtrl = null;
	}
}

function* arcPoints(x1, y1, rx, ry, phi, fa, fs, x2, y2) {
	if (rx === 0 || ry === 0) { yield [x2, y2]; return; }
	const cos = Math.cos(phi), sin = Math.sin(phi);
	const dx = (x1 - x2) / 2, dy = (y1 - y2) / 2;
	const x1p = cos * dx + sin * dy, y1p = -sin * dx + cos * dy;
	const lambda = (x1p * x1p) / (rx * rx) + (y1p * y1p) / (ry * ry);
	if (lambda > 1) { rx *= Math.sqrt(lambda); ry *= Math.sqrt(lambda); }
	const sign = fa === fs ? -1 : 1;
	const num = rx * rx * ry * ry - rx * rx * y1p * y1p - ry * ry * x1p * x1p;
	const den = rx * rx * y1p * y1p + ry * ry * x1p * x1p;
	const co = sign * Math.sqrt(Math.max(0, num / den));
	const cxp = (co * rx * y1p) / ry, cyp = (-co * ry * x1p) / rx;
	const cx = cos * cxp - sin * cyp + (x1 + x2) / 2, cy = sin * cxp + cos * cyp + (y1 + y2) / 2;
	const angle = (ux, uy, vx, vy) => Math.atan2(ux * vy - uy * vx, ux * vx + uy * vy);
	const t1 = angle(1, 0, (x1p - cxp) / rx, (y1p - cyp) / ry);
	let dt = angle((x1p - cxp) / rx, (y1p - cyp) / ry, (-x1p - cxp) / rx, (-y1p - cyp) / ry);
	if (!fs && dt > 0) dt -= 2 * Math.PI;
	if (fs && dt < 0) dt += 2 * Math.PI;
	for (let s = 1; s <= SAMPLES; s++) {
		const t = t1 + (dt * s) / SAMPLES;
		yield [cx + rx * Math.cos(t) * cos - ry * Math.sin(t) * sin, cy + rx * Math.cos(t) * sin + ry * Math.sin(t) * cos];
	}
}

function* shapePoints(node) {
	const a = (k) => Number(node.attrs[k] ?? 0);
	switch (node.name) {
		case 'path': yield* pathPoints(node.attrs.d ?? ''); break;
		case 'line': yield [a('x1'), a('y1')]; yield [a('x2'), a('y2')]; break;
		case 'rect': { const [x, y, w, h] = [a('x'), a('y'), a('width'), a('height')]; yield [x, y]; yield [x + w, y + h]; break; }
		case 'circle': case 'ellipse': {
			const rx = node.name === 'circle' ? a('r') : a('rx'), ry = node.name === 'circle' ? a('r') : a('ry');
			for (let s = 0; s < SAMPLES * 2; s++) { const t = (2 * Math.PI * s) / (SAMPLES * 2); yield [a('cx') + rx * Math.cos(t), a('cy') + ry * Math.sin(t)]; }
			break;
		}
		case 'polyline': case 'polygon': {
			const v = (node.attrs.points ?? '').trim().split(/[\s,]+/).map(Number);
			for (let k = 0; k + 1 < v.length; k += 2) yield [v[k], v[k + 1]];
			break;
		}
	}
}

// ---------- the checks ----------

export function checkIcon(svg) {
	const problems = [];
	let root = null;
	const box = [Infinity, Infinity, -Infinity, -Infinity];

	walk(svg, (node) => {
		if (node.name === 'svg' && !node.parent) root = node;

		for (const attr of COLOUR_ATTRS) {
			const value = node.attrs[attr];
			if (value !== undefined && !ALLOWED_COLOURS.has(value.trim().toLowerCase())) problems.push(`${attr}="${value}" on <${node.name}> (rule 3: currentColor or none only)`);
		}
		for (const m of (node.attrs.style ?? '').matchAll(/(fill|stroke|color|stop-color)\s*:\s*([^;]+)/gi)) {
			if (!ALLOWED_COLOURS.has(m[2].trim().toLowerCase())) problems.push(`style ${m[1]}: ${m[2].trim()} on <${node.name}> (rule 3)`);
		}

		for (let n = node; n; n = n.parent) if (SKIP_CONTENT.has(n.name)) return;
		if (!['path', 'line', 'rect', 'circle', 'ellipse', 'polyline', 'polygon'].includes(node.name)) return;

		const m = ctm(node);
		const stroke = inherited(node, 'stroke');
		const stroked = stroke !== undefined && stroke.toLowerCase() !== 'none';
		const half = stroked ? (Number(inherited(node, 'stroke-width') ?? 1) * Math.sqrt(Math.abs(m[0] * m[3] - m[1] * m[2]))) / 2 : 0;
		for (const [px, py] of shapePoints(node)) {
			const [x, y] = apply(m, px, py);
			box[0] = Math.min(box[0], x - half); box[1] = Math.min(box[1], y - half);
			box[2] = Math.max(box[2], x + half); box[3] = Math.max(box[3], y + half);
		}
	});

	if (!root) return { problems: ['no <svg> root'], box: null };

	const vb = (root.attrs.viewbox ?? '').trim().split(/[\s,]+/).map(Number);
	if (vb.length !== 4 || vb[0] !== 0 || vb[1] !== 0 || vb[2] !== 24 || vb[3] !== 24) problems.push(`viewBox is "${root.attrs.viewbox ?? '(missing)'}" (rule 1: 0 0 24 24)`);
	if (!Number.isFinite(box[0])) return { problems: [...problems, 'draws nothing measurable'], box: null };

	const [x1, y1, x2, y2] = box;
	const w = x2 - x1, h = y2 - y1, longest = Math.max(w, h);
	const cx = (x1 + x2) / 2, cy = (y1 + y2) / 2;
	const r = (v) => Math.round(v * 100) / 100;
	if (Math.abs(longest - LIVE) > TOLERANCE) problems.push(`longest drawn side is ${r(longest)} (rule 2: ${LIVE})`);
	if (Math.abs(cx - 12) > TOLERANCE || Math.abs(cy - 12) > TOLERANCE) problems.push(`drawing is centred on ${r(cx)},${r(cy)} (rule 2: 12,12)`);

	return { problems, box: box.map(r) };
}
