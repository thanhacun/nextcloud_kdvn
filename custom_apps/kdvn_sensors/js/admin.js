/** Administration settings > Office sensors */
(function () {
	'use strict'

	function start() {
		const root = document.getElementById('ks-admin')
		if (!root) return
		const form = document.getElementById('ks-form')
		const status = document.getElementById('ks-status')

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

		form.addEventListener('submit', async event => {
			event.preventDefault()
			const thresholds = {}
			for (const metric of ['temperature', 'humidity', 'co2']) {
				thresholds[metric] = {}
				for (const field of ['okMin', 'okMax', 'warnMin', 'warnMax']) {
					thresholds[metric][field] = form.elements[metric + '.' + field].value
				}
			}
			try {
				await post(root.dataset.saveUrl, { provider: form.elements.provider.value, location: form.elements.location.value, thresholds })
				status.textContent = 'Saved. (A threshold row that does not nest, warning wider than good, falls back to its default.)'
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
