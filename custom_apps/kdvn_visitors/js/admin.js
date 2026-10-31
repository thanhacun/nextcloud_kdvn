/** Administration settings > Visitors */
(function () {
	'use strict'

	function start() {
		const root = document.getElementById('kv-admin')
		if (!root) return
		const form = document.getElementById('kv-settings-form')
		const status = document.getElementById('kv-settings-status')

		async function post(url, body) {
			const response = await fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					Accept: 'application/json',
					requesttoken: document.head.dataset.requesttoken || '',
				},
				body: JSON.stringify(body || {}),
			})
			let json = {}
			try { json = await response.json() } catch (e) { /* empty */ }
			if (!response.ok) throw new Error(json.message || 'Request failed (' + response.status + ').')
			return json
		}

		function say(message, isError) {
			status.textContent = message
			status.classList.toggle('is-error', !!isError)
		}

		form.addEventListener('submit', async event => {
			event.preventDefault()
			const f = form.elements
			try {
				const res = await post(root.dataset.saveUrl, {
					companyName: f.companyName.value,
					officeAddress: f.officeAddress.value,
					talkUser: f.talkUser.value,
					talkPassword: f.talkPassword.value,
					receptionGroup: f.receptionGroup.value,
					staffHiddenGroups: Array.from(f.staffHiddenGroups.selectedOptions).map(o => o.value),
					officeGroups: Object.fromEntries(Array.from(root.querySelectorAll('.kv-admin-workdays tr[data-building]'))
						.map(row => [row.dataset.building, row.querySelector('.kv-office-group').value])),
					officeStaffGroups: Object.fromEntries(Array.from(root.querySelectorAll('.kv-admin-workdays tr[data-building]'))
						.map(row => [row.dataset.building, row.querySelector('.kv-staff-group').value])),
					officeTimezone: f.officeTimezone.value,
					retentionDays: parseInt(f.retentionDays.value, 10) || 90,
					autoCheckoutMinutes: parseInt(f.autoCheckoutMinutes.value, 10),
					autoPassMode: f.autoPassMode.value,
					purposes: f.purposes.value,
					hostNamePrivacy: f.hostNamePrivacy.value,
					// null = leave unchanged (no buildings yet); [] would mean "no office has a reception"
					receptionBuildings: root.querySelector('.kv-admin-workdays tr[data-building]')
						? Array.from(root.querySelectorAll('.kv-admin-workdays tr[data-building]'))
							.filter(row => row.querySelector('.kv-reception').checked)
							.map(row => parseInt(row.dataset.building, 10))
						: null,
					buildingWorkdays: Object.fromEntries(Array.from(root.querySelectorAll('.kv-admin-workdays tr[data-building]')).map(row => [
						row.dataset.building,
						Array.from(row.querySelectorAll('.kv-workday:checked')).map(box => parseInt(box.value, 10)),
					])),
					displayGuestMode: f.displayGuestMode.value,
					displayTitleMode: f.displayTitleMode.value,
					appearance: {
						accent: f.accent.value,
						showLogo: f.showLogo.checked,
						walkIn: f.walkIn.checked,
						checkOut: f.checkOut.checked,
						texts: Object.fromEntries(['vi', 'en', 'ja'].map(lang => [lang, {
							title: f['title_' + lang].value,
							message: f['message_' + lang].value,
						}])),
					},
				})
				f.talkPassword.value = ''
				f.talkPassword.placeholder = res.hasTalkPassword ? 'Saved. Type a new one to replace it.' : 'Not set'
				say('Settings saved.')
			} catch (e) { say(e.message, true) }
		})

		document.getElementById('kv-test-talk').addEventListener('click', async () => {
			say('Sending…')
			try {
				await post(root.dataset.testTalkUrl)
				say('Test message sent. Check Talk: you should have a message from the sender account.')
			} catch (e) { say(e.message + ' (Did you save the settings first?)', true) }
		})

		document.getElementById('kv-test-reception').addEventListener('click', async () => {
			say('Sending…')
			try {
				await post(root.dataset.testReceptionUrl)
				say('Test message posted in the Reception desk conversation of every reception group.')
			} catch (e) { say(e.message + ' (Did you save the reception group and Talk user first?)', true) }
		})

		// Logo upload: sent right away, separately from "Save settings".
		const logoFile = document.getElementById('kv-logo-file')
		const logoPreview = document.getElementById('kv-logo-preview')
		const logoRemove = document.getElementById('kv-logo-remove')
		const logoHint = document.getElementById('kv-logo-hint')
		async function logoRequest(method, body) {
			const response = await fetch(root.dataset.logoApiUrl, {
				method,
				credentials: 'same-origin',
				headers: { Accept: 'application/json', requesttoken: document.head.dataset.requesttoken || '' },
				body,
			})
			let json = {}
			try { json = await response.json() } catch (e) { /* empty */ }
			if (!response.ok) throw new Error(json.message || 'Request failed (' + response.status + ').')
			return json
		}
		logoFile.addEventListener('change', async () => {
			if (!logoFile.files.length) return
			const data = new FormData()
			data.append('logo', logoFile.files[0])
			say('Uploading logo…')
			try {
				const res = await logoRequest('POST', data)
				logoPreview.src = res.logoUrl
				logoRemove.hidden = false
				logoHint.textContent = 'Using the uploaded logo.'
				say('Logo uploaded. Make sure "Show logo" is ticked and saved.')
			} catch (e) { say(e.message, true) }
			logoFile.value = ''
		})
		logoRemove.addEventListener('click', async () => {
			try {
				const res = await logoRequest('DELETE')
				logoPreview.src = res.logoUrl
				logoRemove.hidden = true
				logoHint.textContent = 'Using the Nextcloud Theming logo.'
				say('Uploaded logo removed.')
			} catch (e) { say(e.message, true) }
		})

		document.getElementById('kv-new-display').addEventListener('click', async () => {
			if (!window.confirm('Create new display links? Screens using the old links will stop working until you open the new ones.')) return
			try {
				const res = await post(root.dataset.displayLinkUrl)
				const list = document.getElementById('kv-display-links')
				const layoutSelect = document.getElementById('kv-display-layout')
				const withLayout = url => {
					const layout = layoutSelect.value
					if (!layout) return url
					return url + (url.includes('?') ? '&' : '?') + 'layout=' + layout
				}
				const draw = () => list.replaceChildren(...res.links.map(base => {
					const link = { name: base.name, url: withLayout(base.url) }
					const li = document.createElement('li')
					const label = document.createElement('strong')
					label.textContent = link.name
					const input = document.createElement('input')
					input.type = 'text'
					input.readOnly = true
					input.value = link.url
					const copy = document.createElement('button')
					copy.type = 'button'
					copy.textContent = 'Copy'
					copy.addEventListener('click', async () => {
						try { await navigator.clipboard.writeText(link.url); copy.textContent = 'Copied' } catch (e) { input.select() }
					})
					li.append(label, input, copy)
					return li
				}))
				layoutSelect.onchange = draw
				draw()
				document.getElementById('kv-display-result').hidden = false
			} catch (e) { window.alert(e.message) }
		})

		const result = document.getElementById('kv-kiosk-result')
		const linkInput = document.getElementById('kv-kiosk-link')
		root.querySelectorAll('.kv-new-kiosk').forEach(button => button.addEventListener('click', async () => {
			const name = button.dataset.name
			if (!window.confirm('Create a new tablet link for ' + name + '? A tablet using the old link of this office will stop working until you open the new one on it.')) return
			try {
				const res = await post(root.dataset.kioskUrl, { buildingId: parseInt(button.dataset.building, 10) })
				linkInput.value = res.url
				document.getElementById('kv-kiosk-title').textContent = 'Tablet link for ' + name + ': open it on that office\'s reception tablet and bookmark it.'
				result.hidden = false
				button.textContent = 'New link'
				linkInput.select()
				result.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
			} catch (e) { window.alert(e.message) }
		}))
		document.getElementById('kv-copy-kiosk').addEventListener('click', async () => {
			try { await navigator.clipboard.writeText(linkInput.value) } catch (e) { linkInput.select() }
		})
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
