/** Administration settings > Directory */
(function () {
	'use strict'
	function start() {
		const root = document.getElementById('kd-admin')
		if (!root) return
		const status = document.getElementById('kd-status')
		document.getElementById('kd-save').addEventListener('click', async () => {
			const rows = Array.from(root.querySelectorAll('tr[data-group]'))
			const body = {
				listedGroups: rows.filter(r => r.querySelector('.kd-listed').checked)
					.map(r => ({ id: r.dataset.group, label: r.querySelector('.kd-label').value })),
				deniedGroups: rows.filter(r => r.querySelector('.kd-denied').checked).map(r => r.dataset.group),
			}
			try {
				const response = await fetch(root.dataset.saveUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', Accept: 'application/json', requesttoken: document.head.dataset.requesttoken || '' },
					body: JSON.stringify(body),
				})
				let json = {}
				try { json = await response.json() } catch (e) { /* empty */ }
				if (!response.ok) throw new Error(json.message || 'Request failed (' + response.status + ').')
				status.textContent = 'Saved.'
				status.classList.remove('is-error')
			} catch (e) {
				status.textContent = e.message
				status.classList.add('is-error')
			}
		})
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
