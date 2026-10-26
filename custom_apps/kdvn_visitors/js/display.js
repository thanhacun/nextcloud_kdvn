/**
 * Room display for a screen in front of the meeting rooms.
 * One column per room: free/busy status now, then bookings for the next days.
 * Updates every 60 s without reloading; long lists scroll slowly by themselves.
 * URL options: ?building=ID  &lang=en|vi|ja (default en)  &theme=dark|light  &days=working days (default 5)
 *              &layout=list (default: long lists scroll) | condensed (today in full, later days one line each)
 *                     | pages (lists flip pages with "1 / 3") | timeline (today across all rooms, big screens)
 * Plain JavaScript, no build step. All text is inserted with textContent (no HTML injection).
 */
(function () {
	'use strict'

	const EARLY_WINDOW = 30 * 60 // seconds before the booked start in which an arrived guest marks the room as waiting

	const LOCALES = { vi: 'vi-VN', en: 'en-GB', ja: 'ja-JP' }
	const T = {
		vi: {
			title: 'Lịch phòng họp', comingUp: 'Sắp tới', bookings: '{n} lịch đặt', oneBooking: '1 lịch đặt', noMoreToday: 'Hôm nay không còn cuộc họp nào', nextDays: 'Những ngày tới', guestVisit: 'Tiếp khách', free: 'Trống', freeUntil: 'Trống đến {time}', freeRest: 'Trống đến hết ngày', guestWaiting: 'Khách đã đến, họp lúc {time}',
			busyUntil: 'Đang họp đến {time}', today: 'Hôm nay', tomorrow: 'Ngày mai', none: 'Chưa có lịch đặt phòng',
			guests: 'Khách: {list}', guestCount: '{n} khách', arrived: '{n} đã đến', booked: 'Đã đặt', allDay: 'Cả ngày',
			seats: '{n} chỗ', updated: 'Cập nhật {time}', offline: 'Mất kết nối – đang thử lại', noRooms: 'Chưa có phòng họp nào.',
		},
		en: {
			title: 'Meeting rooms', comingUp: 'Coming up', bookings: '{n} bookings', oneBooking: '1 booking', noMoreToday: 'No more meetings today', nextDays: 'Next days', guestVisit: 'Guest visit', free: 'Free', freeUntil: 'Free until {time}', freeRest: 'Free for today', guestWaiting: 'Guest waiting, meeting at {time}',
			busyUntil: 'In use until {time}', today: 'Today', tomorrow: 'Tomorrow', none: 'No bookings',
			guests: 'Guests: {list}', guestCount: '{n} guests', arrived: '{n} arrived', booked: 'Booked', allDay: 'All day',
			seats: '{n} seats', updated: 'Updated {time}', offline: 'Connection lost, retrying', noRooms: 'No meeting rooms yet.',
		},
		ja: {
			title: '会議室予約状況', comingUp: '今後の予定', bookings: '予約{n}件', oneBooking: '予約1件', noMoreToday: '本日の予定はこれ以上ありません', nextDays: '今後の日程', guestVisit: '来客対応', free: '空き', freeUntil: '{time}まで空き', freeRest: '本日は空き', guestWaiting: '来訪者到着済み、{time}開始',
			busyUntil: '{time}まで使用中', today: '今日', tomorrow: '明日', none: '予約はありません',
			guests: '来訪者：{list}', guestCount: '来訪者{n}名', arrived: '{n}名到着', booked: '予約済み', allDay: '終日',
			seats: '{n}名', updated: '{time}更新', offline: '接続が切れました。再試行中…', noRooms: '会議室がまだありません。',
		},
	}

	function h(tag, attrs, ...children) {
		const el = document.createElement(tag)
		for (const [key, value] of Object.entries(attrs || {})) {
			if (value === null || value === undefined || value === false) continue
			if (key === 'class') el.className = value
			else if (value === true) el.setAttribute(key, '')
			else el.setAttribute(key, value)
		}
		for (const child of children.flat()) {
			if (child === null || child === undefined || child === false || child === '') continue
			el.append(child instanceof Node ? child : String(child))
		}
		return el
	}

	function start() {
		const root = document.getElementById('kv-display')
		if (!root) return
		const params = new URLSearchParams(window.location.search)
		const lang = T[params.get('lang')] ? params.get('lang') : 'en' // no choice on a wall screen: English
		const locale = LOCALES[lang]
		document.documentElement.lang = lang
		document.documentElement.dataset.theme = params.get('theme') === 'light' ? 'light' : 'dark'
		const query = new URLSearchParams()
		if (params.get('building')) query.set('building', params.get('building'))
		if (params.get('days')) query.set('days', params.get('days'))
		const dataUrl = root.dataset.dataUrl + (query.toString() ? '?' + query : '')
		const loadedAt = Date.now()
		const layout = ['list', 'condensed', 'pages', 'timeline'].includes(params.get('layout')) ? params.get('layout') : 'list'
		root.dataset.layout = layout
		const pageIndex = {} // which page each paged list shows; survives re-renders

		let data = null
		let offline = false

		const t = (key, p) => {
			let text = T[lang][key] || T.en[key] || key
			for (const [k, v] of Object.entries(p || {})) text = text.split('{' + k + '}').join(v)
			return text
		}
		const tz = () => (data && data.timezone) || undefined
		const fmtTime = ts => new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: tz() }).format(ts * 1000)
		const dayKey = ts => new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: tz() }).format(ts * 1000)
		const fmtDay = ts => new Intl.DateTimeFormat(locale, { weekday: 'short', day: 'numeric', month: 'numeric', timeZone: tz() }).format(ts * 1000)

		async function load() {
			try {
				const response = await fetch(dataUrl, { credentials: 'omit', headers: { Accept: 'application/json' } })
				if (!response.ok) throw new Error(String(response.status))
				data = await response.json()
				offline = false
			} catch (e) {
				offline = true // keep showing the last data
			}
			render()
		}

		// ----------------------------------------------------------- views

		function header() {
			const now = Date.now() / 1000
			return h('header', { class: 'kd-head' },
				h('div', { class: 'kd-brand' },
					root.dataset.logoUrl ? h('img', { class: 'kd-logo', src: root.dataset.logoUrl, alt: root.dataset.company }) : null,
					h('div', {},
						h('h1', { class: 'kd-title' }, t('title')),
						h('p', { class: 'kd-building' }, data && data.building ? data.building.name : (root.dataset.logoUrl ? '' : root.dataset.company)),
					),
				),
				h('div', { class: 'kd-clock' },
					h('span', { class: 'kd-clock__time' }, fmtTime(now)),
					h('span', { class: 'kd-clock__date' }, new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long', timeZone: tz() }).format(now * 1000)),
					offline ? h('span', { class: 'kd-offline', role: 'status' }, t('offline')) : null,
				),
			)
		}

		function status(room, now) {
			const today = dayKey(now)
			const current = room.bookings.find(b => b.start <= now && b.end > now)
			if (current) {
				return h('div', { class: 'kd-status is-busy' },
					h('span', { class: 'kd-status__label' }, current.allDay ? t('allDay') : t('busyUntil', { time: fmtTime(current.end) })),
					h('span', { class: 'kd-status__what' }, current.kind === 'visit' ? t('guestVisit') : current.private ? t('booked') : (current.title || t('booked'))))
			}
			// Guests checked in before the booked start: not free, but the booked times stay as they are.
			const waiting = room.bookings.find(b => b.start > now && b.start - now <= EARLY_WINDOW && b.arrived && b.arrivedAt && b.arrivedAt <= now)
			if (waiting) {
				return h('div', { class: 'kd-status is-waiting' },
					h('span', { class: 'kd-status__label' }, t('guestWaiting', { time: fmtTime(waiting.start) })))
			}
			const next = room.bookings.find(b => b.start > now && dayKey(b.start) === today)
			return h('div', { class: 'kd-status is-free' },
				h('span', { class: 'kd-status__label' }, next ? t('freeUntil', { time: fmtTime(next.start) }) : t('freeRest')))
		}

		function bookingItem(b, now) {
			const guestText = data.guestMode === 'count' || !b.guests.length
				? (b.guestCount ? t('guestCount', { n: b.guestCount }) : '')
				: t('guests', { list: b.guests.join(', ') })
			return h('li', { class: 'kd-booking' + (b.start <= now && b.end > now ? ' is-now' : '') + (b.kind === 'visit' ? ' is-visit' : '') },
				h('span', { class: 'kd-booking__time' }, b.allDay ? t('allDay') : fmtTime(b.start) + '–' + fmtTime(b.end)),
				h('span', { class: 'kd-booking__title' }, b.kind === 'visit' ? t('guestVisit') : b.private ? t('booked') : (b.title || t('booked'))),
				b.organizer ? h('span', { class: 'kd-booking__meta' }, b.organizer) : null,
				guestText ? h('span', { class: 'kd-booking__guests' }, guestText,
					b.arrived ? h('span', { class: 'kd-arrived' }, ' · ' + t('arrived', { n: b.arrived })) : null) : null,
			)
		}

		function roomCard(room, now) {
			const meta = [room.floor, room.number, room.capacity ? t('seats', { n: room.capacity }) : ''].filter(Boolean).join(', ')
			const upcoming = room.bookings.filter(b => b.end > now)
			const days = []
			for (const b of upcoming) {
				const key = dayKey(Math.max(b.start, now))
				let day = days.find(d => d.key === key)
				if (!day) days.push(day = { key, ts: Math.max(b.start, now), items: [] })
				day.items.push(b)
			}
			const todayKey = dayKey(now)
			const tomorrowKey = dayKey(now + 86400)
			const dayLabel = d => d.key === todayKey ? t('today') : d.key === tomorrowKey ? t('tomorrow') : fmtDay(d.ts)

			const head = h('header', { class: 'kd-room__head' },
				h('h2', { class: 'kd-room__name' }, room.name),
				meta ? h('p', { class: 'kd-room__meta' }, meta) : null,
			)
			const dayBlock = d => h('div', { class: 'kd-day' },
				h('h3', { class: 'kd-day__label' + (d.key === todayKey ? ' is-today' : '') }, dayLabel(d)),
				h('ul', { class: 'kd-day__list' }, d.items.map(b => bookingItem(b, now))),
			)

			if (layout === 'condensed') {
				// Today in full (paged if it still doesn't fit); every later day as one summary line.
				const today = days.find(d => d.key === todayKey)
				const later = days.filter(d => d.key !== todayKey)
				const pageKey = 'room-' + room.id
				return h('section', { class: 'kd-room' }, head, status(room, now),
					h('div', { class: 'kd-room__list', 'data-page': pageKey },
						today ? dayBlock(today) : h('p', { class: 'kd-empty' }, t('noMoreToday'))),
					h('span', { class: 'kd-pager', 'data-pager': pageKey }),
					later.length ? h('div', { class: 'kd-later' },
						h('h3', { class: 'kd-day__label' }, t('comingUp')),
						h('ul', { class: 'kd-later__list' }, later.slice(0, 5).map(d => h('li', { class: 'kd-later__item' },
							h('span', { class: 'kd-later__day' }, dayLabel(d)),
							h('span', {}, summary(d.items)),
						)))) : null,
				)
			}

			const pageKey = 'room-' + room.id
			return h('section', { class: 'kd-room' }, head, status(room, now),
				h('div', layout === 'pages' ? { class: 'kd-room__list', 'data-page': pageKey } : { class: 'kd-room__list', 'data-scroll': '' },
					days.length === 0 ? h('p', { class: 'kd-empty' }, t('none')) : days.map(dayBlock)),
				layout === 'pages' ? h('span', { class: 'kd-pager', 'data-pager': pageKey }) : null,
			)
		}

		/** "3 bookings, 09:00–16:00" or "All day" */
		function summary(items) {
			if (items.some(b => b.allDay)) return (items.length === 1 ? t('oneBooking') : t('bookings', { n: items.length })) + ', ' + t('allDay')
			const first = Math.min(...items.map(b => b.start))
			const last = Math.max(...items.map(b => b.end))
			return (items.length === 1 ? t('oneBooking') : t('bookings', { n: items.length })) + ', ' + fmtTime(first) + '–' + fmtTime(last)
		}

		// ------------------------------------------------------- timeline

		/** Today across all rooms on one time axis, then the next days as a small table. */
		function timelineView(rooms, now) {
			const dayStart = data.from
			const todayKey = dayKey(now)
			const todays = rooms.map(r => r.bookings.filter(b => b.end > dayStart && b.start < dayStart + 86400))
			const hours = todays.flat().filter(b => !b.allDay)
			const startH = Math.max(0, Math.min(8, ...hours.map(b => Math.floor((Math.max(b.start, dayStart) - dayStart) / 3600))))
			const endH = Math.min(24, Math.max(18, ...hours.map(b => Math.ceil((Math.min(b.end, dayStart + 86400) - dayStart) / 3600))))
			const axisStart = dayStart + startH * 3600
			const span = (endH - startH) * 3600
			const pct = ts => Math.max(0, Math.min(100, (ts - axisStart) / span * 100))
			const step = endH - startH > 12 ? 2 : 1
			const ticks = []
			for (let hr = startH; hr <= endH; hr += step) ticks.push(hr)
			const nowPct = now >= axisStart && now <= axisStart + span ? pct(now) : null

			const rows = rooms.map((room, i) => {
				const bars = todays[i].map(b => {
					const left = b.allDay ? 0 : pct(b.start)
					const width = b.allDay ? 100 : Math.max(1.5, pct(b.end) - left)
					const label = b.kind === 'visit' ? t('guestVisit') : b.private ? t('booked') : (b.title || t('booked'))
					return h('div', {
						class: 'kd-bar' + (b.start <= now && b.end > now ? ' is-now' : '') + (b.end <= now ? ' is-past' : '') + (b.kind === 'visit' ? ' is-visit' : ''),
						style: 'left:' + left + '%;width:' + width + '%',
					},
					h('span', { class: 'kd-bar__time' }, b.allDay ? t('allDay') : fmtTime(b.start) + '–' + fmtTime(b.end)),
					h('span', { class: 'kd-bar__title' }, label),
					b.guestCount ? h('span', { class: 'kd-bar__meta' }, data.guestMode === 'count' || !b.guests.length ? t('guestCount', { n: b.guestCount }) : b.guests.join(', ')) : null)
				})
				return h('div', { class: 'kd-tl__row' },
					h('div', { class: 'kd-tl__room' },
						h('h2', { class: 'kd-room__name' }, room.name),
						status(room, now)),
					h('div', { class: 'kd-tl__track' },
						nowPct !== null ? h('div', { class: 'kd-tl__past', style: 'width:' + nowPct + '%' }) : null,
						bars,
						nowPct !== null ? h('div', { class: 'kd-tl__now', style: 'left:' + nowPct + '%' }) : null))
			})

			const nextDays = (data.shownDays || []).filter(d => d !== todayKey).slice(0, 5)
			const table = nextDays.length ? h('div', { class: 'kd-tl__next' },
				h('h3', { class: 'kd-day__label' }, t('nextDays')),
				h('table', {},
					h('thead', {}, h('tr', {}, h('th', {}, ''), nextDays.map(d => h('th', {}, fmtDay(Date.parse(d + 'T12:00:00Z') / 1000))))),
					h('tbody', {}, rooms.map(room => h('tr', {},
						h('th', { scope: 'row' }, room.name),
						nextDays.map(d => {
							const items = room.bookings.filter(b => dayKey(b.start) === d)
							return h('td', { class: items.length ? '' : 'is-empty' }, items.length ? summary(items) : '–')
						})))))) : null

			return h('main', { class: 'kd-tl' },
				h('div', { class: 'kd-tl__axis' }, h('div', {}),
					h('div', { class: 'kd-tl__ticks' }, ticks.map(hr => h('span', { style: 'left:' + pct(dayStart + hr * 3600) + '%' }, String(hr).padStart(2, '0') + ':00')))),
				h('div', { class: 'kd-tl__rows' }, rows),
				table)
		}

		// ---------------------------------------------------------- paging

		/** Page start positions, breaking only between bookings (a day's label stays with its first booking). */
		function pageStarts(el) {
			if (el.scrollHeight - el.clientHeight <= 4) return [0]
			const starts = [0]
			let start = 0
			el.querySelectorAll('.kd-booking').forEach(item => {
				const ul = item.parentElement
				const day = ul.closest('.kd-day')
				const label = day && day.querySelector('.kd-day__label')
				// A page starting mid-day keeps room above for a repeated day label (added in show()).
				const top = item === ul.firstElementChild && day
					? day.offsetTop
					: item.offsetTop - (label ? label.offsetHeight + 8 : 0)
				if (item.offsetTop + item.offsetHeight - start > el.clientHeight && top > start) {
					start = Math.max(0, top - 10) // same breathing space as the first page's top padding
					starts.push(start)
				}
			})
			// Space after the last booking, so even the last page can start exactly at a booking.
			if (starts.length > 1 && !el.querySelector('.kd-spacer')) {
				el.append(h('div', { class: 'kd-spacer', style: 'height:' + el.clientHeight + 'px' }))
			}
			return starts
		}

		function applyPages(animate) {
			root.querySelectorAll('[data-page]').forEach(el => {
				const key = el.dataset.page
				const starts = pageStarts(el)
				let i = pageIndex[key] || 0
				if (i >= starts.length) i = 0
				pageIndex[key] = i
				const show = () => {
					el.scrollTop = starts[i]
					// Only bookings that fit completely on this page are shown; the rest wait for their page.
					const top = el.scrollTop, bottom = top + el.clientHeight
					el.querySelectorAll('.kd-booking').forEach(item => {
						const fits = item.offsetTop >= top - 1 && item.offsetTop + item.offsetHeight <= bottom + 1
						item.style.visibility = fits ? '' : 'hidden'
					})
					el.querySelectorAll('.kd-cont').forEach(c => c.remove())
					el.querySelectorAll('.kd-day').forEach(day => {
						const label = day.querySelector('.kd-day__label')
						const visible = Array.from(day.querySelectorAll('.kd-booking')).filter(item => item.style.visibility !== 'hidden')
						label.style.visibility = visible.length && label.offsetTop >= top - 1 ? '' : 'hidden'
						// The day continues from the previous page: repeat its label at the top.
						if (visible.length && label.offsetTop < top - 1) {
							const cont = label.cloneNode(true)
							cont.classList.add('kd-cont')
							cont.style.visibility = ''
							cont.style.top = top + 'px'
							el.append(cont)
						}
					})
					const pager = root.querySelector('[data-pager="' + key + '"]')
					if (pager) pager.textContent = starts.length > 1 ? (i + 1) + ' / ' + starts.length : ''
				}
				if (!animate || starts.length < 2) { show(); return }
				el.classList.add('is-turning')
				setTimeout(() => { show(); el.classList.remove('is-turning') }, 250)
			})
		}

		// Up to 4 columns on a landscape TV, 2 on a portrait one; more rooms wrap into rows.
		function columns(n) {
			const max = window.innerHeight > window.innerWidth ? 2 : 4
			return Math.max(1, Math.min(n, max))
		}
		window.addEventListener('resize', () => render())

		function render() {
			const now = Date.now() / 1000
			const scroll = Array.from(root.querySelectorAll('[data-scroll]')).map(el => el.scrollTop)
			const rooms = data ? data.rooms : []
			root.replaceChildren(
				header(),
				!data
					? h('p', { class: 'kd-empty kd-empty--big' }, offline ? t('offline') : '…')
					: rooms.length === 0
						? h('p', { class: 'kd-empty kd-empty--big' }, t('noRooms'))
						: layout === 'timeline'
							? timelineView(rooms, now)
							: h('main', { class: 'kd-grid', style: '--kd-cols: ' + columns(rooms.length) }, rooms.map(r => roomCard(r, now))),
				data ? h('footer', { class: 'kd-foot' }, t('updated', { time: fmtTime(data.generatedAt) })) : null,
			)
			root.querySelectorAll('[data-scroll]').forEach((el, i) => { el.scrollTop = scroll[i] || 0 })
			applyPages(false)
		}

		// "pages" and "condensed": turn to the next page every 8 s.
		setInterval(() => {
			root.querySelectorAll('[data-page]').forEach(el => { pageIndex[el.dataset.page] = (pageIndex[el.dataset.page] || 0) + 1 })
			applyPages(true)
		}, 8000)

		// "list": long lists scroll down a page every 8 s, pause at the end, then return to the top.
		setInterval(() => {
			root.querySelectorAll('[data-scroll]').forEach(el => {
				if (el.scrollHeight <= el.clientHeight + 4) return
				const atEnd = el.scrollTop + el.clientHeight >= el.scrollHeight - 4
				el.scrollTo({ top: atEnd ? 0 : el.scrollTop + el.clientHeight * 0.8, behavior: 'smooth' })
			})
		}, 8000)

		setInterval(() => {
			// A full reload every 30 minutes picks up app updates and new settings.
			if (Date.now() - loadedAt > 30 * 60 * 1000 && navigator.onLine !== false) window.location.reload()
			else load()
		}, 60 * 1000)
		setInterval(render, 20 * 1000) // keep the clock and free/busy status current between loads

		load()
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
