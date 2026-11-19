/** Administration settings > Office sensors */
(function () {
	'use strict'

	const METRICS = [['temperature', 'Temp'], ['humidity', 'Humidity'], ['co2', 'CO2']]
	const POINTS = [['temperature', 'Temp point'], ['humidity', 'Humidity point'], ['co2', 'CO2 point'], ['fan', 'Fan point (optional)']]
	const FIELDS = [['okMin', 'Good from'], ['okMax', 'Good to'], ['warnMin', 'Warn from'], ['warnMax', 'Warn to']]

	function h(tag, attrs, ...children) {
		const el = document.createElement(tag)
		for (const [k, v] of Object.entries(attrs || {})) {
			if (v === null || v === undefined || v === false) continue
			if (k === 'class') el.className = v
			else if (v === true) el.setAttribute(k, '')
			else el.setAttribute(k, v)
		}
		for (const c of children.flat()) if (c !== null && c !== undefined) el.append(c instanceof Node ? c : String(c))
		return el
	}

	function start() {
		const root = document.getElementById('ks-admin')
		if (!root) return
		const form = document.getElementById('ks-form')
		const status = document.getElementById('ks-status')
		const list = document.getElementById('ks-sensors')
		const buildings = JSON.parse(root.dataset.buildings || '[]')
		const defaults = JSON.parse(root.dataset.defaultThresholds || '{}')
		let sensors = JSON.parse(root.dataset.sensors || '[]')

		async function post(url, body) {
			const response = await fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json', requesttoken: document.head.dataset.requesttoken || '' },
				body: JSON.stringify(body || {}),
			})
			let json = {}
			try { json = await response.json() } catch (e) { /* empty */ }
			if (!response.ok) throw new Error(json.message || 'Request failed (' + response.status + ').')
			return json
		}

		function row(sensor) {
			const el = h('div', { class: 'ks-row', 'data-id': sensor.id || '', style: 'border:1px solid var(--color-border);border-radius:8px;padding:10px;margin-bottom:8px' })
			const buildingSelect = h('select', { 'data-f': 'buildingId' },
				h('option', { value: '' }, 'All offices / not assigned'),
				buildings.map(b => h('option', { value: String(b.id) }, b.name + ' (ID ' + b.id + ')')))
			buildingSelect.value = sensor.buildingId === null || sensor.buildingId === undefined ? '' : String(sensor.buildingId)

			const metricBoxes = METRICS.map(([key, label]) => {
				const box = h('input', { type: 'checkbox', 'data-metric': key })
				box.checked = !sensor.metrics || sensor.metrics.includes(key)
				return h('label', { style: 'flex-direction:row;font-weight:normal;gap:4px;align-items:center;display:flex' }, box, label)
			})

			const roomBox = h('input', { type: 'checkbox', 'data-f': 'roomDisplay' })
			roomBox.checked = sensor.roomDisplay !== false

			const thresholdBlock = h('details', { style: 'margin-top:8px' }, h('summary', {}, 'Own thresholds (empty = defaults)'),
				METRICS.map(([metric, label]) => h('div', { style: 'display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin:6px 0' },
					h('strong', { style: 'width:80px' }, label),
					FIELDS.map(([field, fieldLabel]) => {
						const input = h('input', { type: 'number', step: 'any', style: 'width:90px', 'data-t': metric + '.' + field, placeholder: String(defaults[metric] ? defaults[metric][field] : '') })
						const own = sensor.thresholds && sensor.thresholds[metric]
						if (own) input.value = own[field]
						return h('label', { style: 'display:flex;flex-direction:column;font-weight:normal;font-size:90%' }, fieldLabel, input)
					}))))

			const remove = h('button', { type: 'button' }, 'Remove')
			remove.addEventListener('click', () => { if (window.confirm('Remove this sensor?')) el.remove() })

			el.append(
				h('div', { style: 'display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end' },
					h('label', { style: 'display:flex;flex-direction:column;font-weight:600' }, 'Name', h('input', { type: 'text', maxlength: '60', 'data-f': 'name', value: sensor.name || '', placeholder: 'Server room' })),
					h('label', { style: 'display:flex;flex-direction:column;font-weight:600' }, 'Office', buildingSelect),
					remove),
				h('div', { style: 'display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-top:8px' },
					POINTS.map(([key, label]) => h('label', { style: 'display:flex;flex-direction:column;font-weight:600' }, label,
						h('input', { type: 'text', maxlength: '100', 'data-p': key, value: (sensor.points && sensor.points[key]) || '', placeholder: 'R101_' + ({ temperature: 'Temp', humidity: 'RH', co2: 'CO2', fan: 'Fan' })[key] })))),
				h('div', { style: 'display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;align-items:center' }, h('strong', {}, 'Measures:'), metricBoxes,
					h('label', { style: 'flex-direction:row;font-weight:normal;gap:4px;align-items:center;display:flex' }, roomBox, 'Show on the room display')),
				thresholdBlock)
			return el
		}

		function draw() { list.replaceChildren(...sensors.map(row)) }
		draw()

		document.getElementById('ks-add').addEventListener('click', () => {
			list.append(row({ name: '', buildingId: null, metrics: ['temperature', 'humidity', 'co2'], roomDisplay: true }))
		})

		function collectSensors() {
			return Array.from(list.querySelectorAll('.ks-row')).map(el => {
				const get = f => el.querySelector('[data-f="' + f + '"]')
				const thresholds = {}
				for (const [metric] of METRICS) {
					thresholds[metric] = {}
					for (const [field] of FIELDS) thresholds[metric][field] = el.querySelector('[data-t="' + metric + '.' + field + '"]').value
				}
				return {
					id: el.dataset.id,
					name: get('name').value,
					buildingId: get('buildingId').value,
					points: Object.fromEntries(POINTS.map(([key]) => [key, el.querySelector('[data-p="' + key + '"]').value])),
					roomDisplay: get('roomDisplay').checked,
					metrics: METRICS.filter(([key]) => el.querySelector('[data-metric="' + key + '"]').checked).map(([key]) => key),
					thresholds,
				}
			})
		}

		form.addEventListener('submit', async event => {
			event.preventDefault()
			const thresholds = {}
			for (const [metric] of METRICS) {
				thresholds[metric] = {}
				for (const [field] of FIELDS) thresholds[metric][field] = form.elements[metric + '.' + field].value
			}
			try {
				const body = {
					provider: form.elements.provider.value,
					thresholds,
					sensors: collectSensors(),
					supplierUrl: form.elements.supplierUrl.value,
					supplierKey: form.elements.supplierKey.value,
					supplierStaleMinutes: parseInt(form.elements.supplierStaleMinutes.value, 10) || 5,
					clearSupplierKey: !!(form.elements.clearSupplierKey && form.elements.clearSupplierKey.checked),
				}
				await post(root.dataset.saveUrl, body)
				form.elements.supplierKey.value = ''
				status.textContent = 'Saved. A sensor without a name is dropped; thresholds that do not nest (warning band narrower than good) are ignored. Reload to see the saved list.'
			} catch (e) { status.textContent = e.message }
		})

		document.getElementById('ks-new-link').addEventListener('click', async () => {
			if (!window.confirm('Create a new display link? The old link stops working.')) return
			try {
				const res = await post(root.dataset.displayLinkUrl)
				const box = document.getElementById('ks-link-result')
				document.getElementById('ks-link').value = res.url
				box.hidden = false
				box.style.display = 'flex'
				box.style.flexWrap = 'wrap'
			} catch (e) { window.alert(e.message) }
		})

		document.getElementById('ks-copy').addEventListener('click', async () => {
			const input = document.getElementById('ks-link')
			try { await navigator.clipboard.writeText(input.value) } catch (e) { input.select() }
		})
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
