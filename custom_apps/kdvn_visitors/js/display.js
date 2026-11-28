/**
 * Room display for a screen in front of the meeting rooms.
 * One column per room: free/busy status now, then bookings for the next days.
 * Updates every 60 s without reloading; long lists scroll slowly by themselves.
 * URL options: ?building=ID  &lang=en|vi|ja (default en)  &theme=dark|light  &days=working days (default 5)
 *              &sensors=1 shows the indoor sensor strip (kdvn_sensors) in the header
 *              &layout=list (default: long lists scroll) | condensed (today in full, later days one line each)
 *                     | pages (lists flip pages with "1 / 3") | timeline (today across all rooms, big screens)
 * Interactive link (/touch/{key}, data-touch="1"): each room gets Details and Book now buttons; booking is for 15/30/60 min from now.
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
			seats: '{n} chỗ', details: 'Chi tiết', bookNow: 'Đặt ngay', close: 'Đóng', bookTitle: 'Đặt {room}', freeUntilLong: 'Trống đến {time}', roomFreeRest: 'Trống đến hết ngày', howLong: 'Họp bao lâu?', minutes: '{n} phút', tryAgain: 'Thử lại', faceFailed: 'Chưa nhận ra bạn', noPhoto: 'Ảnh chỉ được dùng để nhận diện rồi bỏ đi, không được lưu ở đâu cả.', bookingOnly: 'Chỉ lịch đặt phòng được lưu, trong lịch của bạn.', identFace: 'Camera sẽ nhận diện khuôn mặt của bạn', identPhone: 'Bạn sẽ quét mã QR bằng điện thoại và xác nhận', qrTitle: 'Quét bằng điện thoại', qrHelp: 'Mở camera hoặc ứng dụng Nextcloud, quét mã, rồi bấm Xác nhận trên điện thoại ({from}–{to}).', waitingPhone: 'Đang chờ điện thoại…', qrExpired: 'Mã đã hết hạn, hãy thử lại', back: 'Quay lại', cancel: 'Hủy', usePhone: 'Dùng điện thoại', faceHint: 'Hãy nhìn vào camera', faceUnknown: 'Chưa nhận ra, hãy nhìn thẳng vào camera', faceMany: 'Chỉ một người trước camera', faceAmbiguous: 'Có người trông giống nhau: hãy dùng điện thoại', hello: 'Xin chào {name}', confirmBook: 'Đặt phòng {from}–{to}', booking: 'Đang đặt…', bookedOk: 'Đã đặt xong', bookedOkLine: '{room} là của bạn đến {time}', takenNow: 'Vừa có người đặt phòng này', bookFailed: 'Không đặt được', tooShort: 'Phòng sắp có cuộc họp, không đủ thời gian', today2: 'Hôm nay', updated: 'Cập nhật {time}', offline: 'Mất kết nối – đang thử lại', noRooms: 'Chưa có phòng họp nào.',
		},
		en: {
			title: 'Meeting rooms', comingUp: 'Coming up', bookings: '{n} bookings', oneBooking: '1 booking', noMoreToday: 'No more meetings today', nextDays: 'Next days', guestVisit: 'Guest visit', free: 'Free', freeUntil: 'Free until {time}', freeRest: 'Free for today', guestWaiting: 'Guest waiting, meeting at {time}',
			busyUntil: 'In use until {time}', today: 'Today', tomorrow: 'Tomorrow', none: 'No bookings',
			guests: 'Guests: {list}', guestCount: '{n} guests', arrived: '{n} arrived', booked: 'Booked', allDay: 'All day',
			seats: '{n} seats', details: 'Details', bookNow: 'Book now', close: 'Close', bookTitle: 'Book {room}', freeUntilLong: 'Free until {time}', roomFreeRest: 'Free for the rest of the day', howLong: 'How long?', minutes: '{n} min', tryAgain: 'Try again', faceFailed: 'We could not recognise you', noPhoto: 'Your picture is only used to recognise you, then discarded. It is not saved anywhere.', bookingOnly: 'Only the booking is saved, in your calendar.', identFace: 'The camera will recognise your face', identPhone: 'You will scan a QR code with your phone and confirm', qrTitle: 'Scan with your phone', qrHelp: 'Open the camera or the Nextcloud app, scan, then tap Confirm on your phone ({from}–{to}).', waitingPhone: 'Waiting for your phone…', qrExpired: 'The code expired, try again', back: 'Back', cancel: 'Cancel', usePhone: 'Use my phone instead', faceHint: 'Look at the camera', faceUnknown: 'Not recognised yet, look straight at the camera', faceMany: 'One person in front of the camera, please', faceAmbiguous: 'Several people look alike: use your phone', hello: 'Hello {name}', confirmBook: 'Book {from}–{to}', booking: 'Booking…', bookedOk: 'Booked', bookedOkLine: '{room} is yours until {time}', takenNow: 'Someone just booked this room', bookFailed: 'Could not book', tooShort: 'A meeting starts soon, not enough time to book', today2: 'Today', updated: 'Updated {time}', offline: 'Connection lost, retrying', noRooms: 'No meeting rooms yet.',
		},
		ja: {
			title: '会議室予約状況', comingUp: '今後の予定', bookings: '予約{n}件', oneBooking: '予約1件', noMoreToday: '本日の予定はこれ以上ありません', nextDays: '今後の日程', guestVisit: '来客対応', free: '空き', freeUntil: '{time}まで空き', freeRest: '本日は空き', guestWaiting: '来訪者到着済み、{time}開始',
			busyUntil: '{time}まで使用中', today: '今日', tomorrow: '明日', none: '予約はありません',
			guests: '来訪者：{list}', guestCount: '来訪者{n}名', arrived: '{n}名到着', booked: '予約済み', allDay: '終日',
			seats: '{n}名', details: '詳細', bookNow: '今すぐ予約', close: '閉じる', bookTitle: '{room}を予約', freeUntilLong: '{time}まで空き', roomFreeRest: '本日は空き', howLong: '利用時間', minutes: '{n}分', tryAgain: 'もう一度', faceFailed: '認識できませんでした', noPhoto: '写真は本人確認にのみ使用し、すぐに破棄します。どこにも保存されません。', bookingOnly: '保存されるのは予約のみ（あなたのカレンダー）です。', identFace: 'カメラで顔を認識します', identPhone: 'スマートフォンでQRコードを読み取り、確定します', qrTitle: 'スマートフォンで読み取り', qrHelp: 'カメラまたはNextcloudアプリで読み取り、スマートフォンで「確定」を押してください（{from}–{to}）。', waitingPhone: 'スマートフォンを待っています…', qrExpired: 'コードの期限が切れました。もう一度お試しください', back: '戻る', cancel: 'キャンセル', usePhone: 'スマートフォンを使う', faceHint: 'カメラを見てください', faceUnknown: 'まだ認識できません。カメラをまっすぐ見てください', faceMany: 'カメラの前は一人でお願いします', faceAmbiguous: '似た人がいます。スマートフォンを使ってください', hello: 'こんにちは、{name}さん', confirmBook: '{from}–{to}を予約', booking: '予約中…', bookedOk: '予約しました', bookedOkLine: '{room}を{time}まで使用できます', takenNow: 'たった今、予約されました', bookFailed: '予約できませんでした', tooShort: 'まもなく予定があり、予約できません', today2: '今日', updated: '{time}更新', offline: '接続が切れました。再試行中…', noRooms: '会議室がまだありません。',
		},
	}

	function h(tag, attrs, ...children) {
		const el = document.createElement(tag)
		for (const [key, value] of Object.entries(attrs || {})) {
			if (value === null || value === undefined || value === false) continue
			if (key === 'class') el.className = value
			else if (key.startsWith('on') && typeof value === 'function') el.addEventListener(key.slice(2), value)
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
		// Inside the reception tablet's second tab (?embed=1) the header carries a back button; the tablet listens for it.
		const embedded = params.get('embed') === '1' && window.parent !== window
		const BACK = { vi: 'Khách đến', en: 'Visitors', ja: '来訪者' }
		document.documentElement.lang = lang
		document.documentElement.dataset.theme = params.get('theme') === 'light' ? 'light' : 'dark'
		const query = new URLSearchParams()
		if (params.get('building')) query.set('building', params.get('building'))
		if (params.get('days')) query.set('days', params.get('days'))
		if (params.get('sensors') === '1') query.set('sensors', '1')
		const dataUrl = root.dataset.dataUrl + (query.toString() ? '?' + query : '')
		const loadedAt = Date.now()
		const touch = root.dataset.touch === '1' // interactive link: details and quick booking (never set on the read-only link)
		const layout = ['list', 'condensed', 'pages', 'timeline'].includes(params.get('layout')) ? params.get('layout') : (touch ? 'condensed' : 'list')
		root.dataset.layout = layout
		const pageIndex = {} // which page each paged list shows; survives re-renders

		let data = null
		let offline = false
		let sheet = null // touch: the open details/booking sheet; the board is not redrawn meanwhile
		let lastTouch = 0
		const idle = () => !sheet && Date.now() - lastTouch > 30 * 1000 // auto page turns wait while someone is using the screen

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

		const SENSOR_T = {
			en: { temperature: 'Temp', humidity: 'Humidity', co2: 'CO2', Comfortable: 'Comfortable', Normal: 'Normal', Good: 'Good', Warning: 'Warning', Bad: 'Bad', NA: 'NA' },
			vi: { temperature: 'Nhiệt độ', humidity: 'Độ ẩm', co2: 'CO2', Comfortable: 'Dễ chịu', Normal: 'Bình thường', Good: 'Tốt', Warning: 'Chú ý', Bad: 'Kém', NA: 'NA' },
			ja: { temperature: '温度', humidity: '湿度', co2: 'CO2', Comfortable: '快適', Normal: '標準', Good: '良好', Warning: '注意', Bad: '要改善', NA: 'NA' },
		}

		// Indoor sensor strip (only when the link has &sensors=1 and the server sent sensors): one group per sensor.
		function sensorStrip() {
			if (!data || !Array.isArray(data.sensors) || data.sensors.length === 0) return null
			const st = SENSOR_T[lang] || SENSOR_T.en
			return h('div', { class: 'kd-sensors' },
				data.sensors.map(sensor => h('div', { class: 'kd-sensor-group' },
					h('span', { class: 'kd-sensor-group__name' }, sensor.name),
					h('div', { class: 'kd-sensor-group__chips' },
						sensor.tiles.map(tile => h('div', { class: 'kd-sensor kd-sensor--' + tile.level },
							h('span', { class: 'kd-sensor__name' }, st[tile.metric] || tile.metric),
							h('span', { class: 'kd-sensor__value' }, tile.value === null ? st.NA : tile.value + ' ' + tile.unit),
							h('span', { class: 'kd-sensor__word' }, st[tile.word] || tile.word),
						)))
				)))
		}

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
				embedded ? h('button', {
					type: 'button', class: 'kd-back',
					style: 'font:inherit;font-weight:700;font-size:1.1em;min-height:44px;padding:0 1.4em;border-radius:999px;border:1px solid currentColor;background:transparent;color:inherit;cursor:pointer',
					onclick: () => window.parent.postMessage({ kdvnBack: true }, window.location.origin),
				}, '‹ ' + (BACK[lang] || BACK.en)) : null,
				sensorStrip(),
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

			const actions = touch ? h('div', { class: 'kd-actions' },
				h('button', { type: 'button', class: 'kd-btn', onclick: () => openSheet(room, 'details') }, t('details')),
				room.bookable && isFree(room, now) ? h('button', { type: 'button', class: 'kd-btn kd-btn--primary', onclick: () => openSheet(room, 'book') }, t('bookNow')) : null) : null
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
				return h('section', { class: 'kd-room' }, head, status(room, now), actions,
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
			return h('section', { class: 'kd-room' }, head, status(room, now), actions,
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

		// ------------------------------------------------- touch: details and quick booking
		//
		// Booking a room needs a verified person, never a typed name. The identify step ends in a signed ticket that
		// the server's book endpoint redeems: (1) face, when the page has data-face-url (the camera asks the server
		// who it is), or (2) a QR code the person scans with their phone and confirms while logged in to Nextcloud.

		const DURATIONS = [15, 30, 60]
		const faceUrl = root.dataset.faceUrl || ''
		const isFree = (room, now) => !room.bookings.some(b => b.start <= now && b.end > now)
			&& !room.bookings.some(b => b.start > now && b.start - now <= EARLY_WINDOW && b.arrived && b.arrivedAt && b.arrivedAt <= now)
		/** Seconds the room stays free from now (null = for the rest of the shown period). */
		function freeSeconds(room, now) {
			const next = room.bookings.find(b => b.start > now)
			return next ? next.start - now : null
		}
		const post = (url, fields) => fetch(url, { method: 'POST', credentials: 'omit', headers: { Accept: 'application/json' }, body: fields instanceof FormData ? fields : new URLSearchParams(fields) })

		function stopIdentify() {
			if (sheet && sheet.cancel) { sheet.cancel(); sheet.cancel = null }
		}

		function closeSheet() {
			stopIdentify()
			sheet = null
			const el = document.getElementById('kd-sheet')
			if (el) el.remove()
			clearTimeout(closeSheet.timer)
			render()
			load()
		}

		function openSheet(room, mode) {
			lastTouch = Date.now()
			sheet = { roomId: room.id, mode, step: 'choose', minutes: 30, error: '', done: null, busy: false, refs: {}, cancel: null }
			drawSheet()
		}

		function backToChoose(error) {
			stopIdentify()
			if (!sheet) return
			sheet.step = 'choose'
			sheet.error = error || ''
			drawSheet()
		}

		function drawSheet() {
			if (!sheet) return
			let el = document.getElementById('kd-sheet')
			if (!el) {
				el = h('div', { id: 'kd-sheet', class: 'kd-sheet', role: 'dialog', 'aria-modal': 'true' })
				root.append(el)
			}
			const now = Date.now() / 1000
			const room = data.rooms.find(r => r.id === sheet.roomId)
			if (!room) { closeSheet(); return }
			clearTimeout(closeSheet.timer)
			closeSheet.timer = setTimeout(closeSheet, sheet.done ? 10 * 1000 : sheet.step === 'choose' || sheet.step === 'facefail' ? 60 * 1000 : 150 * 1000) // back to the board when nobody touches it

			const free = freeSeconds(room, now)
			const fits = m => free === null || m * 60 <= free
			if (!fits(sheet.minutes)) sheet.minutes = DURATIONS.filter(fits).pop() || sheet.minutes
			const end = now + sheet.minutes * 60
			const body = []
			const foot = []
			sheet.refs = {}

			if (sheet.done) {
				body.push(h('div', { class: 'kd-done' },
					h('p', { class: 'kd-done__big' }, '✓ ' + t('bookedOk')),
					h('p', {}, t('bookedOkLine', { room: room.name, time: fmtTime(sheet.done.end) })),
					sheet.done.name ? h('p', { class: 'kd-done__who' }, sheet.done.name) : null))
			} else if (sheet.mode === 'details') {
				const upcoming = room.bookings.filter(b => b.end > now)
				const byDay = []
				for (const b of upcoming) {
					const key = dayKey(Math.max(b.start, now))
					let day = byDay.find(d => d.key === key)
					if (!day) byDay.push(day = { key, ts: Math.max(b.start, now), items: [] })
					day.items.push(b)
				}
				const todayKey = dayKey(now)
				body.push(status(room, now))
				body.push(h('div', { class: 'kd-sheet__list' }, byDay.length === 0 ? h('p', { class: 'kd-empty' }, t('none'))
					: byDay.map(d => h('div', { class: 'kd-day' },
						h('h3', { class: 'kd-day__label' + (d.key === todayKey ? ' is-today' : '') }, d.key === todayKey ? t('today') : d.key === dayKey(now + 86400) ? t('tomorrow') : fmtDay(d.ts)),
						h('ul', { class: 'kd-day__list' }, d.items.map(b => bookingItem(b, now)))))))
				if (room.bookable && isFree(room, now)) {
					foot.push(h('button', { type: 'button', class: 'kd-btn kd-btn--primary kd-btn--big', onclick: () => { sheet.mode = 'book'; drawSheet() } }, t('bookNow')))
				}
			} else if (sheet.step === 'choose') {
				const bookable = isFree(room, now) && fits(DURATIONS[0])
				body.push(h('p', { class: 'kd-sheet__sub' }, free === null ? t('roomFreeRest') : t('freeUntilLong', { time: fmtTime(now + free) })))
				if (!bookable) {
					body.push(h('p', { class: 'kd-sheet__error' }, isFree(room, now) ? t('tooShort') : t('takenNow')))
				} else {
					body.push(h('h3', { class: 'kd-sheet__q' }, t('howLong')),
						h('div', { class: 'kd-chips' }, DURATIONS.map(m => h('button', {
							type: 'button', class: 'kd-chip' + (sheet.minutes === m ? ' is-on' : ''), disabled: !fits(m),
							onclick: () => { sheet.minutes = m; drawSheet() },
						}, t('minutes', { n: m })))),
						h('p', { class: 'kd-sheet__hint' }, faceUrl ? t('identFace') : t('identPhone')),
						h('p', { class: 'kd-sheet__hint kd-privacy' }, faceUrl ? t('noPhoto') : t('bookingOnly')))
					foot.push(h('button', { type: 'button', class: 'kd-btn kd-btn--primary kd-btn--big', onclick: startIdentify },
						t('confirmBook', { from: fmtTime(now), to: fmtTime(end) })))
				}
			} else if (sheet.step === 'facefail') {
				body.push(h('p', { class: 'kd-sheet__sub kd-failmsg' }, sheet.failMsg || t('faceFailed')))
				foot.push(h('div', { class: 'kd-split' },
					h('button', { type: 'button', class: 'kd-btn kd-btn--primary kd-btn--big', onclick: startFace }, t('tryAgain')),
					h('button', { type: 'button', class: 'kd-btn kd-btn--big', onclick: startQr }, t('usePhone'))))
			} else if (sheet.step === 'qr') {
				sheet.refs.qr = h('div', { class: 'kd-qr' })
				sheet.refs.wait = h('p', { class: 'kd-sheet__sub' }, t('waitingPhone'))
				body.push(h('div', { class: 'kd-qrwrap' }, sheet.refs.qr,
					h('div', { class: 'kd-qrtext' },
						h('h3', { class: 'kd-sheet__q' }, t('qrTitle')),
						h('p', {}, t('qrHelp', { from: fmtTime(now), to: fmtTime(end) })),
						sheet.refs.wait,
						h('p', { class: 'kd-sheet__hint kd-privacy' }, t('bookingOnly')))))
				foot.push(h('button', { type: 'button', class: 'kd-btn kd-btn--big', onclick: () => backToChoose() }, t('back')))
			} else if (sheet.step === 'face') {
				sheet.refs.video = h('video', { class: 'kd-cam__video', autoplay: true, muted: true, playsinline: true })
				sheet.refs.hint = h('p', { class: 'kd-sheet__sub kd-cam__hint' }, t('faceHint'))
				body.push(h('div', { class: 'kd-cam' }, h('div', { class: 'kd-cam__oval' }, sheet.refs.video), sheet.refs.hint,
					h('p', { class: 'kd-sheet__hint kd-privacy' }, t('noPhoto'))))
				foot.push(h('div', { class: 'kd-split' },
					h('button', { type: 'button', class: 'kd-btn kd-btn--big', onclick: () => backToChoose() }, t('cancel')),
					h('button', { type: 'button', class: 'kd-btn kd-btn--big', onclick: startQr }, t('usePhone'))))
			}
			if (sheet.error) body.push(h('p', { class: 'kd-sheet__error' }, sheet.error))

			el.replaceChildren(h('div', { class: 'kd-sheet__card' },
				h('header', { class: 'kd-sheet__head' },
					h('h2', {}, sheet.mode === 'book' ? t('bookTitle', { room: room.name }) : room.name),
					h('button', { type: 'button', class: 'kd-btn', onclick: closeSheet }, t('close'))),
				h('div', { class: 'kd-sheet__body' }, body),
				foot.length ? h('footer', { class: 'kd-sheet__foot' }, foot) : null))
		}

		function startIdentify() {
			lastTouch = Date.now()
			if (faceUrl) startFace(); else startQr()
		}

		// ---- QR code + phone

		async function startQr() {
			stopIdentify()
			sheet.step = 'qr'
			sheet.error = ''
			drawSheet()
			const mine = sheet
			let timers = []
			let stopped = false
			sheet.cancel = () => { stopped = true; timers.forEach(clearInterval) }
			try {
				const form = { roomId: String(sheet.roomId), minutes: String(sheet.minutes) }
				if (params.get('building')) form.building = params.get('building')
				const res = await post(root.dataset.claimUrl, form)
				const claim = await res.json()
				if (stopped || sheet !== mine) return
				if (!claim.ok) { backToChoose(t('bookFailed')); return }
				if (typeof window.qrcode === 'function') {
					const qr = window.qrcode(0, 'M')
					qr.addData(claim.url)
					qr.make()
					mine.refs.qr.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 4, scalable: true }) // the bundled generator's own SVG, no user text
				}
				const until = Date.now() + (claim.seconds || 120) * 1000
				const statusUrl = root.dataset.claimStatusUrl.replace('NONCE', encodeURIComponent(claim.nonce))
				timers.push(setInterval(async () => {
					if (stopped || sheet !== mine) return
					const left = Math.max(0, Math.round((until - Date.now()) / 1000))
					mine.refs.wait.textContent = t('waitingPhone') + ' ' + left + ' s'
					try {
						const r = await fetch(statusUrl, { credentials: 'omit', headers: { Accept: 'application/json' } })
						const s = await r.json()
						if (stopped || sheet !== mine) return
						if (s.state === 'claimed') { stopped = true; timers.forEach(clearInterval); submitBooking(s.ticket, s.name) }
						else if (s.state === 'gone' || left === 0) backToChoose(t('qrExpired'))
					} catch (e) { /* offline: keep trying until the code expires */ }
				}, 2000))
			} catch (e) {
				if (!stopped && sheet === mine) backToChoose(t('bookFailed'))
			}
		}

		/** Not recognised: say so and let the person try again or use the phone (no automatic jump to the QR). */
		function faceFailed(message) {
			stopIdentify()
			if (!sheet) return
			sheet.step = 'facefail'
			sheet.failMsg = message
			sheet.error = ''
			drawSheet()
		}

		// ---- face (needs data-face-url: the server compares the picture with the enrolled faces; nothing is kept here)

		async function startFace() {
			stopIdentify()
			sheet.step = 'face'
			sheet.error = ''
			drawSheet()
			const mine = sheet
			let stopped = false
			let stream = null
			let timer = null
			sheet.cancel = () => { stopped = true; clearTimeout(timer); if (stream) stream.getTracks().forEach(track => track.stop()) }
			try {
				stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false })
				if (stopped || sheet !== mine) { stream.getTracks().forEach(track => track.stop()); return }
				mine.refs.video.srcObject = stream
				await mine.refs.video.play().catch(() => {})
			} catch (e) {
				if (!stopped && sheet === mine) startQr() // no camera or no permission: the phone still works
				return
			}
			const canvas = document.createElement('canvas')
			const giveUpAt = Date.now() + 15 * 1000 // Odoo's advice: about one frame a second, stop after 10-15 s
			const tick = async () => {
				if (stopped || sheet !== mine) return
				if (Date.now() > giveUpAt) { faceFailed(t('faceFailed')); return }
				try {
					const video = mine.refs.video
					canvas.width = 640 // Odoo wants about 640x480 and under 300 KB
					canvas.height = Math.round(640 * (video.videoHeight || 3) / (video.videoWidth || 4))
					canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height)
					const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.8))
					const form = new FormData()
					form.set('image', blob, 'scan.jpg')
					form.set('roomId', String(mine.roomId))
					form.set('minutes', String(mine.minutes))
					if (params.get('building')) form.set('building', params.get('building'))
					const res = await post(faceUrl, form)
					const answer = await res.json()
					if (stopped || sheet !== mine) return
					if (answer.state === 'ok') { hello(answer); return }
					if (answer.state === 'error' || answer.state === 'off') { startQr(); return } // face service unavailable: the phone still works
					if (answer.state === 'ambiguous') { faceFailed(t('faceAmbiguous')); return }
					if (answer.state !== 'wait') mine.refs.hint.textContent = answer.state === 'many' ? t('faceMany') : answer.state === 'none' ? t('faceHint') : t('faceUnknown')
				} catch (e) { /* try again */ }
				timer = setTimeout(tick, 1000)
			}
			// "Hello X (3, 2, 1)": a moment to cancel before the room is booked, like the attendance kiosk.
			const hello = answer => {
				let n = 3
				const count = () => {
					if (stopped || sheet !== mine) return
					if (n === 0) { stopped = true; if (stream) stream.getTracks().forEach(track => track.stop()); submitBooking(answer.ticket, answer.name); return }
					mine.refs.hint.textContent = t('hello', { name: answer.name }) + ' (' + n + ')'
					n--
					timer = setTimeout(count, 1000)
				}
				count()
			}
			tick()
		}

		// ---- the booking itself

		async function submitBooking(ticket, name) {
			if (!sheet || sheet.busy) return
			const mine = sheet
			stopIdentify()
			sheet.busy = true
			sheet.error = ''
			sheet.step = 'choose'
			drawSheet()
			const form = { ticket }
			try {
				const res = await post(root.dataset.bookUrl, form)
				const body = await res.json().catch(() => ({}))
				if (sheet !== mine) return
				sheet.busy = false
				if (body.ok) {
					sheet.done = { end: body.end, name }
					await load() // data updates while the sheet shows "Booked"
				} else {
					sheet.error = body.error === 'taken' ? t('takenNow') : body.error === 'ticket' ? t('qrExpired') : (body.message || t('bookFailed'))
					if (body.error === 'taken') await load()
				}
			} catch (e) {
				if (sheet !== mine) return
				sheet.busy = false
				sheet.error = t('bookFailed')
			}
			drawSheet()
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
			if (sheet) return // never redraw under a finger
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
		if (touch) root.addEventListener('pointerdown', () => { lastTouch = Date.now() })
		setInterval(() => {
			if (!idle()) return
			root.querySelectorAll('[data-page]').forEach(el => { pageIndex[el.dataset.page] = (pageIndex[el.dataset.page] || 0) + 1 })
			applyPages(true)
		}, 8000)

		// "list": long lists scroll down a page every 8 s, pause at the end, then return to the top.
		setInterval(() => {
			if (!idle()) return
			root.querySelectorAll('[data-scroll]').forEach(el => {
				if (el.scrollHeight <= el.clientHeight + 4) return
				const atEnd = el.scrollTop + el.clientHeight >= el.scrollHeight - 4
				el.scrollTo({ top: atEnd ? 0 : el.scrollTop + el.clientHeight * 0.8, behavior: 'smooth' })
			})
		}, 8000)

		setInterval(() => {
			// A full reload every 30 minutes picks up app updates and new settings.
			if (sheet) return
			if (Date.now() - loadedAt > 30 * 60 * 1000 && navigator.onLine !== false) window.location.reload()
			else load()
		}, 60 * 1000)
		setInterval(render, 20 * 1000) // keep the clock and free/busy status current between loads

		load()
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
