/**
 * Meeting Rooms admin page. Plain JavaScript, no build step.
 * Layout: Buildings | Floors of the selected building | Rooms on the selected floor.
 */
(function () {
	'use strict'

	const APP = 'kdvn_rooms'

	const ROOM_TYPES = [
		['meeting-room', 'Meeting room'],
		['lecture-hall', 'Lecture hall'],
		['other', 'Other'],
	]

	const FEATURES = [
		['hasVideoConferencing', 'Video conferencing'],
		['hasProjector', 'Projector'],
		['hasTv', 'TV'],
		['hasWhiteboard', 'Whiteboard'],
		['hasPhone', 'Phone'],
		['isWheelchairAccessible', 'Wheelchair accessible'],
	]

	const EMAIL_PROBLEMS = {
		missing: 'No booking email, so it can\'t be booked in Calendar. Edit the room to add one.',
		invalid: 'The booking email is not valid, so it can\'t be booked in Calendar. Edit the room to fix it.',
		duplicate: 'Another room or resource has the same booking email, so Calendar can\'t tell them apart. Edit the room to change it.',
		user: 'A Nextcloud user has the same email as this room, so Calendar finds the person instead. Edit the room to change it.',
	}

	// ------------------------------------------------------------ helpers

	/** Tiny element builder: h('button', {class: 'primary', onclick: fn}, 'Save') */
	function h(tag, attrs, ...children) {
		const el = document.createElement(tag)
		for (const [key, value] of Object.entries(attrs || {})) {
			if (value === null || value === undefined || value === false) continue
			if (key.startsWith('on')) el.addEventListener(key.slice(2), value)
			else if (key === 'class') el.className = value
			else if (value === true) el.setAttribute(key, '')
			else el.setAttribute(key, value)
		}
		for (const child of children.flat()) {
			if (child === null || child === undefined || child === false) continue
			el.append(child instanceof Node ? child : String(child))
		}
		return el
	}

	/** "Phòng họp Đà Nẵng 2" -> "phong-hop-da-nang-2" */
	function slug(text) {
		return (text || '')
			.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
			.replace(/đ/g, 'd').replace(/Đ/g, 'D')
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '')
	}

	function plural(n, word) {
		return n + ' ' + word + (n === 1 ? '' : 's')
	}

	function requestToken() {
		return document.head.dataset.requesttoken || (window.OC && window.OC.requestToken) || ''
	}

	// --------------------------------------------------------------- app

	function start() {
		const root = document.getElementById('kdvn-rooms-app')
		if (!root) return

		const emailDomain = root.dataset.emailDomain || 'resources.invalid'
		const baseUrl = window.OC.generateUrl('/apps/' + APP + '/api')
		const state = { data: null, buildingId: null, floorId: null }
		const status = h('p', { class: 'kr-status', role: 'status', 'aria-live': 'polite' })
		let statusTimer = null

		async function api(method, path, body) {
			const response = await fetch(baseUrl + path, {
				method,
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					Accept: 'application/json',
					requesttoken: requestToken(),
				},
				body: body === undefined ? undefined : JSON.stringify(body),
			})
			let json = {}
			try { json = await response.json() } catch (e) { /* empty body */ }
			if (!response.ok) {
				if (response.status === 403) throw new Error('You are not allowed to manage rooms. Ask IT to add you to the right group.')
				throw new Error(json.message || 'Request failed (' + response.status + ').')
			}
			return json
		}

		function say(message, isError) {
			clearTimeout(statusTimer)
			status.textContent = message
			status.classList.toggle('is-error', !!isError)
			if (!isError) statusTimer = setTimeout(() => { status.textContent = '' }, 4000)
		}

		async function reload(message) {
			state.data = await api('GET', '/data')
			render()
			if (message) say(message)
		}

		function groupName(id) {
			const group = state.data.groups.find(g => g.id === id)
			return group ? group.name : id
		}

		// ---------------------------------------------------------- render

		function render() {
			const d = state.data
			if (!d.ready) {
				root.replaceChildren(h('p', { class: 'kr-empty' },
					'The Calendar Resource Management app is not enabled. Ask IT to enable it, then reload this page.'))
				return
			}

			if (!d.buildings.some(b => b.id === state.buildingId)) {
				state.buildingId = d.buildings.length ? d.buildings[0].id : null
			}
			const building = d.buildings.find(b => b.id === state.buildingId) || null
			const floors = d.floors.filter(f => f.buildingId === state.buildingId)
			if (!floors.some(f => f.id === state.floorId)) {
				state.floorId = floors.length ? floors[0].id : null
			}
			const floor = floors.find(f => f.id === state.floorId) || null
			const rooms = d.rooms.filter(r => r.floorId === state.floorId)

			root.replaceChildren(
				h('div', { class: 'kr-columns' },
					listColumn({
						title: 'Buildings',
						items: d.buildings,
						selectedId: state.buildingId,
						onSelect: id => { state.buildingId = id; state.floorId = null; render() },
						addLabel: 'Add building',
						onAdd: () => buildingDialog(null),
						onEdit: b => buildingDialog(b),
						onDelete: b => remove('buildings', b, 'building'),
						empty: 'No buildings yet. Start by adding your office building.',
						meta: b => b.address,
					}),
					listColumn({
						title: building ? 'Floors in ' + building.displayName : 'Floors',
						items: floors,
						selectedId: state.floorId,
						onSelect: id => { state.floorId = id; render() },
						addLabel: 'Add floor',
						addDisabled: !building,
						onAdd: () => floorDialog(null),
						onEdit: f => floorDialog(f),
						onDelete: f => remove('floors', f, 'floor'),
						empty: building ? 'No floors yet. Add a floor, then add rooms to it.' : 'Add a building first.',
						meta: f => plural(d.rooms.filter(r => r.floorId === f.id).length, 'room'),
					}),
					roomsColumn(building, floor, rooms),
				),
				status,
			)
		}

		function listColumn(o) {
			return h('section', { class: 'kr-col' },
				h('header', { class: 'kr-col-head' },
					h('h3', {}, o.title),
					h('button', { type: 'button', class: 'primary', disabled: !!o.addDisabled, onclick: o.onAdd }, o.addLabel),
				),
				o.items.length === 0
					? h('p', { class: 'kr-empty' }, o.empty)
					: h('ul', { class: 'kr-list' }, o.items.map(item => {
						const selected = item.id === o.selectedId
						const meta = o.meta ? o.meta(item) : ''
						return h('li', { class: 'kr-item' + (selected ? ' is-selected' : '') },
							h('button', { type: 'button', class: 'kr-pick', 'aria-pressed': String(selected), onclick: () => o.onSelect(item.id) },
								h('span', { class: 'kr-name' }, item.displayName),
								meta ? h('span', { class: 'kr-meta' }, meta) : null,
							),
							rowActions(item, o.onEdit, o.onDelete),
						)
					})),
			)
		}

		function rowActions(item, onEdit, onDelete) {
			return h('span', { class: 'kr-row-actions' },
				h('button', { type: 'button', class: 'kr-link', 'aria-label': 'Edit ' + item.displayName, onclick: () => onEdit(item) }, 'Edit'),
				h('button', { type: 'button', class: 'kr-link kr-danger', 'aria-label': 'Delete ' + item.displayName, onclick: () => onDelete(item) }, 'Delete'),
			)
		}

		function roomsColumn(building, floor, rooms) {
			let body
			if (!floor) {
				body = h('p', { class: 'kr-empty' }, building ? 'Add a floor first.' : 'Add a building and a floor first.')
			} else if (rooms.length === 0) {
				body = h('p', { class: 'kr-empty' }, 'No rooms on this floor yet. Add one and it can be booked in Calendar right away.')
			} else {
				body = h('ul', { class: 'kr-list kr-rooms' }, rooms.map(room => {
					const details = []
					if (room.roomNumber) details.push('Room ' + room.roomNumber)
					if (room.capacity) details.push(plural(room.capacity, 'seat'))
					const features = FEATURES.filter(([key]) => room[key]).map(([, label]) => label)
					const limited = room.groups.length > 0
					const problem = EMAIL_PROBLEMS[room.emailProblem]
					return h('li', { class: 'kr-room' },
						h('div', { class: 'kr-room-main' },
							h('span', { class: 'kr-name' }, room.displayName),
							details.length ? h('span', { class: 'kr-meta' }, details.join(', ')) : null,
							features.length ? h('span', { class: 'kr-chips' }, features.map(f => h('span', { class: 'kr-chip' }, f))) : null,
							problem ? h('span', { class: 'kr-warning', role: 'note' }, '⚠ ' + problem) : null,
							h('span', { class: 'kr-access' + (limited ? ' is-limited' : '') },
								limited ? 'Only ' + room.groups.map(groupName).join(', ') + ' can book' : 'Everyone can book'),
						),
						rowActions(room, roomDialog, r => remove('rooms', r, 'room')),
					)
				}))
			}
			return h('section', { class: 'kr-col kr-col-rooms' },
				h('header', { class: 'kr-col-head' },
					h('h3', {}, floor ? 'Rooms on ' + floor.displayName : 'Rooms'),
					h('button', { type: 'button', class: 'primary', disabled: !floor, onclick: () => roomDialog(null) }, 'Add room'),
				),
				body,
			)
		}

		// --------------------------------------------------------- dialogs

		function openDialog(title, fields, submitLabel, onSubmit) {
			const error = h('p', { class: 'kr-error', role: 'alert', hidden: true })
			const submit = h('button', { type: 'submit', class: 'primary' }, submitLabel)
			const form = h('form', { class: 'kr-form', novalidate: true },
				h('h3', {}, title),
				fields,
				error,
				h('div', { class: 'kr-actions' },
					h('button', { type: 'button', onclick: () => dialog.close() }, 'Cancel'),
					submit,
				),
			)
			const dialog = h('dialog', { class: 'kr-dialog' }, form)
			form.addEventListener('submit', async event => {
				event.preventDefault()
				submit.disabled = true
				error.hidden = true
				try {
					await onSubmit(form.elements)
					dialog.close()
				} catch (e) {
					error.textContent = e.message
					error.hidden = false
					error.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
				} finally {
					submit.disabled = false
				}
			})
			dialog.addEventListener('close', () => dialog.remove())
			document.body.append(dialog)
			dialog.showModal()
			const first = form.querySelector('input:not([readonly]), select, textarea')
			if (first) first.focus()
		}

		function field(label, input, hint) {
			return h('label', { class: 'kr-field' },
				h('span', { class: 'kr-label' }, label),
				input,
				hint ? h('span', { class: 'kr-hint' }, hint) : null,
			)
		}

		function textInput(name, value, attrs) {
			return h('input', Object.assign({ type: 'text', name, value: value || '' }, attrs || {}))
		}

		function checkbox(name, label, checked) {
			return h('label', { class: 'kr-check' }, h('input', { type: 'checkbox', name, checked: !!checked }), ' ', label)
		}

		function buildingDialog(b) {
			openDialog(
				b ? 'Edit building' : 'Add building',
				[
					field('Name', textInput('displayName', b && b.displayName, { maxlength: 255 })),
					field('Address', textInput('address', b && b.address), 'Shown to people choosing a room. Optional.'),
					field('Notes', h('textarea', { name: 'description', rows: 3 }, (b && b.description) || ''), 'Optional.'),
					checkbox('isWheelchairAccessible', 'Wheelchair accessible', b && b.isWheelchairAccessible),
				],
				b ? 'Save building' : 'Add building',
				async f => {
					const body = {
						displayName: f.displayName.value,
						address: f.address.value,
						description: f.description.value,
						isWheelchairAccessible: f.isWheelchairAccessible.checked,
					}
					const res = b ? await api('PUT', '/buildings/' + b.id, body) : await api('POST', '/buildings', body)
					state.buildingId = res.id
					await reload(b ? 'Building saved.' : 'Building added.')
				},
			)
		}

		function floorDialog(fl) {
			openDialog(
				fl ? 'Edit floor' : 'Add floor',
				[field('Name', textInput('displayName', fl && fl.displayName, { maxlength: 255, placeholder: 'e.g. 3rd floor' }))],
				fl ? 'Save floor' : 'Add floor',
				async f => {
					const body = { displayName: f.displayName.value }
					if (!fl) body.buildingId = state.buildingId
					const res = fl ? await api('PUT', '/floors/' + fl.id, body) : await api('POST', '/floors', body)
					state.floorId = res.id
					await reload(fl ? 'Floor saved.' : 'Floor added.')
				},
			)
		}

		function roomDialog(room) {
			const d = state.data
			const building = d.buildings.find(b => b.id === state.buildingId)
			const isNew = !room

			// Name, ID and email: ID and email are filled in from the name until the user edits them.
			const nameInput = textInput('displayName', room && room.displayName, { maxlength: 255, placeholder: 'e.g. Meeting room A' })
			const uidInput = textInput('uid', room && room.uid, { maxlength: 255, readonly: !isNew })
			const fixEmail = !isNew && !!room.emailProblem
			const emailInput = textInput('email', room && room.email, { maxlength: 255, readonly: !isNew && !fixEmail, type: 'email' })
			let uidEdited = !isNew
			let emailEdited = !isNew
			const suggestEmail = uid => (slug(uid) ? 'room-' + slug(uid) + '@' + emailDomain : '')
			nameInput.addEventListener('input', () => {
				if (!uidEdited) uidInput.value = nameInput.value.trim() ? slug((building ? building.displayName : '') + ' ' + nameInput.value) : ''
				if (!emailEdited) emailInput.value = suggestEmail(uidInput.value)
			})
			uidInput.addEventListener('input', () => {
				uidEdited = true
				if (!emailEdited) emailInput.value = suggestEmail(uidInput.value)
			})
			emailInput.addEventListener('input', () => { emailEdited = true })

			// Who can book
			const limited = !!room && room.groups.length > 0
			const groupFilter = h('input', { type: 'search', placeholder: 'Find a group', 'aria-label': 'Find a group' })
			const groupItems = d.groups.map(g => h('label', { class: 'kr-check', 'data-name': (g.name + ' ' + g.id).toLowerCase() },
				h('input', { type: 'checkbox', name: 'group', value: g.id, checked: !!room && room.groups.includes(g.id) }), ' ', g.name))
			groupFilter.addEventListener('input', () => {
				const q = groupFilter.value.trim().toLowerCase()
				groupItems.forEach(item => { item.hidden = q !== '' && !item.dataset.name.includes(q) })
			})
			const groupBox = h('div', { class: 'kr-groups', hidden: !limited }, groupFilter, h('div', { class: 'kr-group-list' }, groupItems))
			const onAccessChange = e => { groupBox.hidden = e.target.value !== 'groups' }

			const typeSelect = h('select', { name: 'roomType' }, ROOM_TYPES.map(([value, label]) =>
				h('option', { value, selected: (room ? room.roomType : 'meeting-room') === value }, label)))
			if (room && !ROOM_TYPES.some(([v]) => v === room.roomType)) {
				typeSelect.append(h('option', { value: room.roomType, selected: true }, room.roomType))
			}

			openDialog(
				isNew ? 'Add room' : 'Edit room',
				[
					field('Name', nameInput, 'This is what people see when they book.'),
					h('div', { class: 'kr-row' },
						field('Room number', textInput('roomNumber', room && room.roomNumber), 'Optional.'),
						field('Seats', h('input', { type: 'number', name: 'capacity', min: 0, step: 1, value: room && room.capacity != null ? room.capacity : '' }), 'Optional.'),
					),
					field('Type', typeSelect),
					h('fieldset', { class: 'kr-fieldset' },
						h('legend', {}, 'Equipment'),
						h('div', { class: 'kr-check-grid' }, FEATURES.map(([key, label]) => checkbox(key, label, room && room[key]))),
					),
					field('Contact person', textInput('contactPersonUserId', room && room.contactPersonUserId, { placeholder: 'Nextcloud user name' }),
						'Who to ask about this room. Optional.'),
					h('fieldset', { class: 'kr-fieldset' },
						h('legend', {}, 'Who can book this room'),
						h('label', { class: 'kr-check' }, h('input', { type: 'radio', name: 'access', value: 'all', checked: !limited, onchange: onAccessChange }), ' Everyone'),
						h('label', { class: 'kr-check' }, h('input', { type: 'radio', name: 'access', value: 'groups', checked: limited, onchange: onAccessChange }), ' Only people in these groups'),
						groupBox,
					),
					h('details', { class: 'kr-details', open: isNew && !fixEmail ? null : true },
						h('summary', {}, 'Calendar ID and email'),
						h('div', { class: 'kr-details-body' },
							field('Room ID', uidInput, isNew ? 'Filled in from the name. Letters, numbers, spaces and _ . @ - only.' : 'Can\'t be changed: existing bookings use it.'),
							field('Booking email', emailInput, isNew
								? 'Must be unique. It doesn\'t need to be a real mailbox.'
								: fixEmail
									? EMAIL_PROBLEMS[room.emailProblem].replace(/ Edit the room to (add one|fix it|change it)\./, '') + ' Enter a unique email, e.g. ' + 'room-' + slug(room.uid) + '@' + emailDomain + '.'
									: 'Can\'t be changed: existing bookings use it.'),
						),
					),
				],
				isNew ? 'Add room' : 'Save room',
				async f => {
					const restricted = f.access.value === 'groups'
					const groups = restricted
						? Array.from(document.querySelectorAll('dialog.kr-dialog input[name="group"]:checked')).map(i => i.value)
						: []
					if (restricted && groups.length === 0) throw new Error('Pick at least one group, or choose Everyone.')

					const body = {
						displayName: f.displayName.value,
						roomNumber: f.roomNumber.value,
						capacity: f.capacity.value === '' ? null : Number(f.capacity.value),
						roomType: f.roomType.value,
						contactPersonUserId: f.contactPersonUserId.value,
						groups,
					}
					FEATURES.forEach(([key]) => { body[key] = f[key].checked })
					if (fixEmail) body.email = f.email.value
					if (isNew) {
						body.floorId = state.floorId
						body.uid = f.uid.value
						body.email = f.email.value
					}
					if (isNew) await api('POST', '/rooms', body)
					else await api('PUT', '/rooms/' + room.id, body)
					await reload(isNew ? 'Room added. It can now be booked in Calendar.' : 'Room saved.')
				},
			)
		}

		async function remove(path, item, noun) {
			if (!window.confirm('Delete ' + noun + ' "' + item.displayName + '"? This can\'t be undone.')) return
			try {
				await api('DELETE', '/' + path + '/' + item.id)
				await reload('Deleted ' + item.displayName + '.')
			} catch (e) {
				say(e.message, true)
			}
		}

		reload().catch(e => {
			root.replaceChildren(h('p', { class: 'kr-error' }, e.message))
		})
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
