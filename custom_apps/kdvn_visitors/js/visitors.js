/**
 * Visitors page for staff.
 *  - "My visitors": invite guests, see your visits, let walk-ins in.
 *  - "Reception" (reception group + admins): all guests for a day, walk-ins waiting,
 *    manual check-in/out, invite on behalf of others.
 * Plain JavaScript, no build step.
 */
(function () {
	'use strict'

	const LANGUAGES = [['vi', 'Tiếng Việt'], ['en', 'English'], ['ja', '日本語']]

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

	/** Latest first: later start time on top; same time -> most recently created on top. */
	const latestFirst = (a, b) => (b.startAt - a.startAt) || (b.id - a.id)

	const pad = n => String(n).padStart(2, '0')
	const timeText = ts => new Date(ts * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
	const dateText = ts => new Date(ts * 1000).toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' })
	const dayKey = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())

	async function copyText(text) {
		try {
			await navigator.clipboard.writeText(text)
			return true
		} catch (e) {
			window.prompt('Copy this link:', text)
			return false
		}
	}

	function start() {
		const root = document.getElementById('kv-app')
		if (!root) return
		const cfg = root.dataset
		const defaultLanguage = cfg.defaultLanguage || 'en'

		const state = {
			tab: 'mine',          // mine | reception
			visits: [],
			rooms: [],
			isReception: false,
			desk: { date: dayKey(new Date()), visits: [], filter: '' },
		}
		const status = h('p', { class: 'kv-status', role: 'status', 'aria-live': 'polite' })
		let statusTimer = null
		let refreshTimer = null

		async function request(method, url, body) {
			const response = await fetch(url, {
				method,
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					Accept: 'application/json',
					requesttoken: document.head.dataset.requesttoken || '',
				},
				body: body === undefined ? undefined : JSON.stringify(body),
			})
			let json = {}
			try { json = await response.json() } catch (e) { /* empty */ }
			if (response.status === 429) throw new Error('Too many requests. Please wait a few minutes.')
			if (!response.ok) throw new Error(json.message || 'Request failed (' + response.status + ').')
			return json
		}
		const api = (method, path, body) => request(method, cfg.apiUrl + path, body)

		function say(message, isError) {
			clearTimeout(statusTimer)
			status.textContent = message
			status.classList.toggle('is-error', !!isError)
			if (!isError) statusTimer = setTimeout(() => { status.textContent = '' }, 5000)
		}

		let lastSignature = ''

		/** Fetch fresh data. Only re-draws when something changed, so typing and scrolling aren't disturbed. */
		async function reload(force = true) {
			const data = await api('GET', '')
			state.visits = data.visits
			state.rooms = data.rooms
			state.isReception = data.isReception
			if (state.tab === 'reception' && state.isReception) await loadDesk()
			const signature = JSON.stringify([state.visits, state.tab === 'reception' ? state.desk.visits : null])
			if (force || signature !== lastSignature) render()
			lastSignature = signature
		}

		async function loadDesk() {
			const data = await request('GET', cfg.receptionUrl + '?date=' + encodeURIComponent(state.desk.date))
			state.desk.visits = data.visits
			state.desk.date = data.date
		}

		// Live updates: check every 10 s while the page is visible, and right away when you come back
		// to the tab. Paused while a dialog is open so a form isn't redrawn under your fingers.
		function scheduleRefresh() {
			const refresh = () => {
				if (document.hidden || document.querySelector('dialog.kv-dialog')) return
				reload(false).catch(() => {})
			}
			clearInterval(refreshTimer)
			refreshTimer = setInterval(refresh, 10000)
			document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh() })
			window.addEventListener('focus', refresh)
		}

		// ----------------------------------------------------------- render

		function render() {
			// Remember whether the Reception search box had the cursor, so a live update doesn't steal it.
			const active = document.activeElement
			const keepSearch = active && active.classList && active.classList.contains('kv-desk-search')
				? { start: active.selectionStart, end: active.selectionEnd }
				: null
			const scroll = window.scrollY

			const tabs = state.isReception
				? h('nav', { class: 'kv-tabs', role: 'tablist' },
					tabButton('mine', 'My visitors'),
					tabButton('reception', 'Reception'))
				: null

			root.replaceChildren(...[
				h('header', { class: 'kv-head' },
					h('div', {},
						h('h2', {}, 'Visitors'),
						h('p', { class: 'kv-sub' }, state.tab === 'reception'
							? 'All guests for the day. Walk-ins waiting need someone to let them in.'
							: 'Invite a guest and they get a QR pass by email. You get a Talk message when they check in at reception.'),
					),
					h('button', { type: 'button', class: 'primary', onclick: () => inviteDialog() }, 'Invite a guest'),
				),
				tabs,
				status,
				...(state.tab === 'reception' ? deskView() : mineView()),
			].filter(Boolean))

			if (keepSearch) {
				const input = root.querySelector('.kv-desk-search')
				if (input) {
					input.focus()
					input.setSelectionRange(keepSearch.start, keepSearch.end)
				}
			}
			window.scrollTo(0, scroll)
		}

		function tabButton(tab, label) {
			const active = state.tab === tab
			return h('button', {
				type: 'button', role: 'tab', class: 'kv-tab' + (active ? ' is-active' : ''), 'aria-selected': String(active),
				onclick: async () => {
					state.tab = tab
					try { if (tab === 'reception') await loadDesk() } catch (e) { say(e.message, true) }
					render()
				},
			}, label)
		}

		function mineView() {
			const today = dayKey(new Date())
			const now = Date.now() / 1000
			const groups = { waiting: [], today: [], upcoming: [], earlier: [] }
			for (const v of [...state.visits].sort(latestFirst)) {
				if (v.status === 'waiting') groups.waiting.push(v)
				else if (dayKey(new Date(v.startAt * 1000)) === today) groups.today.push(v)
				else if (v.startAt > now) groups.upcoming.push(v)
				else groups.earlier.push(v)
			}
			return [
				groups.waiting.length ? section('Waiting for you at reception', groups.waiting, '', 'kv-section--attention') : null,
				section('Today', groups.today, 'No guests today.'),
				section('Upcoming', groups.upcoming, 'No upcoming guests. Use "Invite a guest" to add one.'),
				groups.earlier.length ? section('Last 30 days', groups.earlier, '') : null,
			]
		}

		function deskView() {
			const q = state.desk.filter.trim().toLowerCase()
			const visits = [...state.desk.visits].sort(latestFirst).filter(v => !q
				|| [v.guestName, v.guestCompany, v.hostName, v.roomName, v.code].join(' ').toLowerCase().includes(q))
			const by = s => visits.filter(v => s.includes(v.status))
			const waiting = by(['waiting'])
			const expected = by(['invited'])
			const inside = by(['checked_in'])
			const done = by(['checked_out', 'declined', 'cancelled'])

			const shift = days => {
				const d = new Date(state.desk.date + 'T12:00:00')
				d.setDate(d.getDate() + days)
				changeDate(dayKey(d))
			}
			const isToday = state.desk.date === dayKey(new Date())

			return [
				h('div', { class: 'kv-desk-bar' },
					h('div', { class: 'kv-desk-date' },
						h('button', { type: 'button', 'aria-label': 'Previous day', onclick: () => shift(-1) }, '‹'),
						h('input', { type: 'date', value: state.desk.date, 'aria-label': 'Day', onchange: e => changeDate(e.target.value) }),
						h('button', { type: 'button', 'aria-label': 'Next day', onclick: () => shift(1) }, '›'),
						isToday ? null : h('button', { type: 'button', onclick: () => changeDate(dayKey(new Date())) }, 'Today'),
					),
					h('input', {
						type: 'search', class: 'kv-desk-search', placeholder: 'Find guest, company, host or code', value: state.desk.filter,
						'aria-label': 'Find guest',
						oninput: e => {
							state.desk.filter = e.target.value
							render()
						},
					}),
				),
				h('div', { class: 'kv-counts' },
					count(waiting.length, 'waiting', 'is-attention'),
					count(expected.length, 'expected'),
					count(inside.length, 'in the building'),
					count(done.length, 'left or cancelled'),
				),
				waiting.length ? section('Waiting at reception', waiting, '', 'kv-section--attention', true) : null,
				section('Expected', expected, 'Nobody else expected.', '', true),
				section('In the building', inside, 'Nobody in the building.', '', true),
				done.length ? section('Left, declined or cancelled', done, '', 'kv-section--quiet', true) : null,
			]
		}

		function count(n, label, extra) {
			return h('span', { class: 'kv-count ' + (n && extra ? extra : '') }, h('strong', {}, String(n)), ' ' + label)
		}

		async function changeDate(date) {
			if (!date) return
			state.desk.date = date
			try { await loadDesk() } catch (e) { say(e.message, true) }
			render()
		}

		function section(title, visits, empty, extraClass, desk) {
			return h('section', { class: 'kv-section ' + (extraClass || '') },
				h('h3', {}, title),
				visits.length === 0
					? h('p', { class: 'kv-empty' }, empty)
					: h('ul', { class: 'kv-list' }, visits.map(v => visitRow(v, desk))),
			)
		}

		function statusLabel(v) {
			switch (v.status) {
			case 'invited': return 'Invited'
			case 'waiting': return 'Waiting at reception'
			case 'checked_in': return 'Arrived ' + (v.checkedInAt ? timeText(v.checkedInAt) : '')
			case 'checked_out': return 'Left ' + (v.checkedOutAt ? timeText(v.checkedOutAt) : '')
			case 'declined': return v.decidedBy ? 'Declined by ' + v.decidedBy : 'Declined'
			case 'cancelled': return 'Cancelled'
			default: return v.status
			}
		}

		function visitRow(v, desk) {
			const walkIn = v.kind === 'walk_in'
			const time = walkIn
				? 'Walk-in ' + timeText(v.startAt)
				: timeText(v.startAt) + (v.endAt ? '–' + timeText(v.endAt) : '')

			const actions = []
			const btn = (label, onclick, cls) => h('button', { type: 'button', class: cls || 'kv-link', onclick }, label)
			if (v.status === 'waiting') {
				actions.push(btn('Let in', () => act(v, 'approve', v.guestName + ' can come in.'), 'primary kv-small'))
				actions.push(btn('Decline', () => act(v, 'decline', v.guestName + ' was declined.', 'Decline ' + v.guestName + '? Please also tell them at reception.'), 'kv-small'))
			}
			if (v.status === 'invited' && desk) actions.push(btn('Check in', () => act(v, 'check-in', v.guestName + ' checked in. The host was told.')))
			if (v.status === 'checked_in') actions.push(btn('Check out', () => act(v, 'check-out', v.guestName + ' checked out.')))
			if (!walkIn && (v.status === 'invited' || v.status === 'checked_in')) actions.push(btn('Copy pass link', () => copyLink(v)))
			if (!walkIn && v.status === 'invited') {
				actions.push(btn('Resend email', () => resend(v)))
				actions.push(btn('Cancel', () => act(v, 'cancel', 'Visit cancelled. Let ' + v.guestName + ' know.',
					'Cancel the visit of ' + v.guestName + '? Their pass will stop working. They are not notified automatically.'), 'kv-link kv-danger'))
			}

			const meta = [v.guestCompany, v.roomName].filter(Boolean).join(', ')
			return h('li', { class: 'kv-visit kv-visit--' + v.status },
				h('div', { class: 'kv-visit__when' },
					h('span', { class: 'kv-visit__time' }, time),
					h('span', { class: 'kv-visit__date' }, dateText(v.startAt)),
				),
				h('div', { class: 'kv-visit__who' },
					h('span', { class: 'kv-visit__name' }, v.guestName),
					meta ? h('span', { class: 'kv-visit__meta' }, meta) : null,
					desk ? h('span', { class: 'kv-visit__meta' }, 'Host: ' + (v.hostName || 'not chosen')) : null,
					walkIn ? h('span', { class: 'kv-visit__meta' }, 'Phone: ' + v.guestPhone) : null,
					v.purpose ? h('span', { class: 'kv-visit__meta' }, v.purpose) : null,
				),
				h('div', { class: 'kv-visit__side' },
					h('span', { class: 'kv-badge kv-badge--' + v.status }, statusLabel(v)),
					v.source === 'calendar'
						? h('span', { class: 'kv-visit__source', title: 'Created automatically from a Calendar invitation' }, 'From Calendar')
						: v.roomBooked ? h('span', { class: 'kv-visit__source', title: 'The room is booked in Calendar' }, 'Room booked') : null,
					walkIn ? null : h('span', { class: 'kv-visit__code', title: 'Code the guest can type at the tablet' }, v.code),
					h('div', { class: 'kv-visit__actions' }, actions),
				),
			)
		}

		async function act(v, action, done, confirmText) {
			if (confirmText && !window.confirm(confirmText)) return
			try {
				const res = await api('POST', '/' + v.id + '/' + action)
				await reload()
				if (res.visit && res.visit.calendarNote) say(done + ' ' + res.visit.calendarNote, true)
				else say(done)
			} catch (e) { say(e.message, true) }
		}

		async function copyLink(v) {
			try {
				const res = await api('GET', '/' + v.id + '/link')
				if (await copyText(res.passUrl)) say('Pass link for ' + v.guestName + ' copied. You can paste it in Zalo or another chat.')
			} catch (e) { say(e.message, true) }
		}

		async function resend(v) {
			try {
				const res = await api('POST', '/' + v.id + '/resend')
				if (res.emailError) say(res.emailError, true)
				else say('Invitation sent again to ' + v.guestEmail + '.')
			} catch (e) { say(e.message, true) }
		}

		// --------------------------------------------------------- invite

		function field(label, input, hint) {
			return h('label', { class: 'kv-field' },
				h('span', { class: 'kv-label' }, label),
				input,
				hint ? h('span', { class: 'kv-hint' }, hint) : null,
			)
		}

		/** Reception only: pick the host. Returns {element, value()} */
		function hostPicker() {
			let chosen = null // null = me
			const wrap = h('div', { class: 'kv-host' })
			const draw = () => {
				if (chosen) {
					wrap.replaceChildren(
						h('span', { class: 'kv-host__chosen' }, chosen.name),
						h('button', { type: 'button', class: 'kv-link', onclick: () => { chosen = null; draw() } }, 'Change'))
					return
				}
				const results = h('div', { class: 'kv-host__results' })
				let timer = null
				const input = h('input', {
					type: 'search', placeholder: 'Me. Or type 2+ letters of another host\'s name', autocomplete: 'off', 'aria-label': 'Host',
					oninput: e => {
						clearTimeout(timer)
						const q = e.target.value.trim()
						if (q.length < 2) { results.replaceChildren(); return }
						timer = setTimeout(async () => {
							try {
								const data = await request('GET', cfg.usersUrl + '?q=' + encodeURIComponent(q))
								results.replaceChildren(...data.users.map(u =>
									h('button', { type: 'button', class: 'kv-chip', onclick: () => { chosen = u; draw() } }, u.name)))
							} catch (err) { /* ignore */ }
						}, 250)
					},
				})
				wrap.replaceChildren(input, results)
			}
			draw()
			return { element: wrap, value: () => (chosen ? chosen.id : '') }
		}

		function inviteDialog() {
			const next = new Date()
			next.setMinutes(0, 0, 0)
			next.setHours(next.getHours() + 1)
			const picker = state.isReception ? hostPicker() : null

			const error = h('p', { class: 'kv-error', role: 'alert', hidden: true })
			const submit = h('button', { type: 'submit', class: 'primary' }, 'Send invitation')
			const bookBox = h('input', { type: 'checkbox', name: 'bookRoom', checked: true })
			const bookRow = h('label', { class: 'kv-check', hidden: true }, bookBox, ' Book the room in Calendar (in the host\'s calendar, like a normal meeting)')
			const roomSelect = h('select', {
				name: 'roomName',
				onchange: e => {
					const room = state.rooms.find(r => r.label === e.target.value)
					bookRow.hidden = !room
					bookBox.disabled = !!room && !room.bookable
					bookBox.checked = !!room && room.bookable
					bookRow.lastChild.textContent = room && !room.bookable
						? ' This room can\'t be booked (no valid booking email). Ask IT to fix it in Meeting rooms.'
						: ' Book the room in Calendar (in the host\'s calendar, like a normal meeting)'
				},
			},
				h('option', { value: '' }, 'No room / not decided'),
				state.rooms.map(r => h('option', { value: r.label }, r.label)))

			const form = h('form', { class: 'kv-form', novalidate: true },
				h('h3', {}, 'Invite a guest'),
				picker ? field('Host', picker.element, 'Reception can invite on behalf of someone else. They get the Talk message.') : null,
				h('div', { class: 'kv-row' },
					field('Guest name', h('input', { type: 'text', name: 'guestName', maxlength: 255, autocomplete: 'off' })),
					field('Company', h('input', { type: 'text', name: 'guestCompany', maxlength: 255, autocomplete: 'off' })),
				),
				h('div', { class: 'kv-row' },
					field('Email', h('input', { type: 'email', name: 'guestEmail', maxlength: 255, autocomplete: 'off' }), 'The QR pass is sent here.'),
					field('Phone', h('input', { type: 'tel', name: 'guestPhone', maxlength: 64, autocomplete: 'off' })),
				),
				h('div', { class: 'kv-row kv-row--3' },
					field('Date', h('input', { type: 'date', name: 'date', value: dayKey(next) })),
					field('From', h('input', { type: 'time', name: 'from', value: pad(next.getHours()) + ':00' })),
					field('To', h('input', { type: 'time', name: 'to' }), 'Optional.'),
				),
				field('Meeting room', roomSelect, 'Shown to the guest, reception and the room display.'),
				bookRow,
				field('Purpose', h('textarea', { name: 'purpose', rows: 2, maxlength: 1000 }), 'Optional. Not shown to the guest.'),
				field('Email language', h('select', { name: 'language' },
					LANGUAGES.map(([code, label]) => h('option', { value: code, selected: code === defaultLanguage }, label)))),
				h('p', { class: 'kv-hint' }, 'Guest details are stored for reception and deleted automatically after the retention period.'),
				error,
				h('div', { class: 'kv-actions' },
					h('button', { type: 'button', onclick: () => dialog.close() }, 'Cancel'),
					submit,
				),
			)
			const dialog = h('dialog', { class: 'kv-dialog' }, form)

			form.addEventListener('submit', async event => {
				event.preventDefault()
				const f = form.elements
				error.hidden = true
				const startAt = toTimestamp(f.date.value, f.from.value)
				const endAt = f.to.value ? toTimestamp(f.date.value, f.to.value) : null
				if (startAt === null) {
					showError('Pick a date and start time.')
					return
				}
				submit.disabled = true
				try {
					const res = await api('POST', '', {
						hostUid: picker ? picker.value() : '',
						guestName: f.guestName.value,
						guestCompany: f.guestCompany.value,
						guestEmail: f.guestEmail.value,
						guestPhone: f.guestPhone.value,
						startAt,
						endAt,
						roomName: f.roomName.value,
						bookRoom: !bookRow.hidden && bookBox.checked && !bookBox.disabled,
						purpose: f.purpose.value,
						language: f.language.value,
					})
					form.replaceWith(doneView(res, dialog))
					reload().catch(() => {})
				} catch (e) {
					showError(e.message)
				} finally {
					submit.disabled = false
				}
			})

			function showError(message) {
				error.textContent = message
				error.hidden = false
				error.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
			}

			dialog.addEventListener('close', () => dialog.remove())
			document.body.append(dialog)
			dialog.showModal()
			form.querySelector('input[name="guestName"]').focus()
		}

		function toTimestamp(date, time) {
			if (!date || !time) return null
			const d = new Date(date + 'T' + time) // browser's local time zone
			return isNaN(d.getTime()) ? null : Math.floor(d.getTime() / 1000)
		}

		function doneView(res, dialog) {
			const v = res.visit
			const linkInput = h('input', { type: 'text', readonly: true, value: res.passUrl, 'aria-label': 'Pass link' })
			const copied = h('span', { class: 'kv-hint', role: 'status' })
			return h('div', { class: 'kv-form' },
				h('h3', {}, res.emailError ? 'Visit saved, email not sent' : 'Invitation sent'),
				res.emailError
					? h('p', { class: 'kv-error' }, res.emailError)
					: h('p', {}, v.guestName + ' will get the QR pass at ' + v.guestEmail + '.'),
				res.roomBooked ? h('p', {}, v.roomName + ' is booked in ' + (v.hostName || 'your') + '\'s calendar.') : null,
				field('Pass link (for Zalo or other chats)', linkInput),
				h('div', { class: 'kv-actions kv-actions--start' },
					h('button', {
						type: 'button',
						onclick: async () => { if (await copyText(res.passUrl)) copied.textContent = 'Copied.' },
					}, 'Copy link'),
					copied,
				),
				h('p', {}, 'Backup code for the tablet: ', h('strong', { class: 'kv-code' }, v.code)),
				h('div', { class: 'kv-actions' }, h('button', { type: 'button', class: 'primary', onclick: () => dialog.close() }, 'Done')),
			)
		}

		reload()
			.then(scheduleRefresh)
			.catch(e => root.replaceChildren(h('p', { class: 'kv-error' }, e.message)))
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
