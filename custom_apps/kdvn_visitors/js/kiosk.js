/**
 * Reception tablet. Screens:
 *   welcome  -> "I have a visitor pass" -> scan (or type code) -> result
 *            -> "Walk-in guest"         -> form                 -> result
 *            -> "Leaving? Check out"    -> scan (or type code) -> result
 * Goes back to the welcome screen after one minute without a touch.
 * Vietnamese / English / Japanese. Plain JavaScript, no build step.
 */
(function () {
	'use strict'

	const DEFAULT_LANG = 'vi'
	const LOCALES = { vi: 'vi-VN', en: 'en-GB', ja: 'ja-JP' }
	const IDLE_SECONDS = 60
	const RESULT_SECONDS = 9

	const T = {
		vi: {
			morning: 'Chào buổi sáng', afternoon: 'Chào buổi chiều', evening: 'Chào buổi tối',
			welcome: 'Chào mừng quý khách đến {company}',
			havePass: 'Tôi có thẻ khách', havePassSub: 'Đã có lịch hẹn và mã QR',
			walkIn: 'Khách chưa có lịch hẹn', walkInSub: 'Đăng ký tại đây',
			leaving: 'Ra về? Check-out',
			scanIn: 'Đưa mã QR trên thẻ khách vào camera.',
			scanOut: 'Đưa mã QR trên thẻ khách vào camera để check-out.',
			typeCode: 'Nhập mã', codeTitle: 'Nhập mã 6 ký tự trong email của bạn',
			checkIn: 'Check-in', checkOut: 'Check-out', back: 'Quay lại', send: 'Gửi',
			checking: 'Đang kiểm tra…',
			hello: 'Xin chào {guest}!',
			told: 'Chúng tôi đã báo cho {host} biết bạn đã đến. Vui lòng ngồi chờ trong giây lát.',
			already: 'Bạn đã check-in rồi. {host} đã được thông báo.',
			room: 'Phòng họp: {room}',
			bye: 'Cảm ơn {guest} đã ghé thăm. Hẹn gặp lại!',
			noPassOut: 'Không có thẻ khách? Vui lòng báo nhân viên lễ tân khi ra về.',
			name: 'Họ và tên', company: 'Công ty', phone: 'Số điện thoại',
			host: 'Bạn muốn gặp ai?', hostHint: 'Nhập ít nhất {n} chữ cái của tên (có dấu hoặc không dấu)', notSure: 'Tôi không chắc', change: 'Đổi',
			purpose: 'Mục đích', optional: '(không bắt buộc)',
			purposeChoose: 'Chọn mục đích…', purposeOther: 'Khác…', purposeOtherHint: 'Vui lòng ghi rõ mục đích',
			requiredLegend: '* Bắt buộc', fieldRequired: 'Vui lòng điền mục này', consentRequired: 'Vui lòng đánh dấu đồng ý để tiếp tục',
			noHostFound: 'Không tìm thấy. Hãy thử tên khác hoặc chọn "Tôi không chắc".',
			consent: 'Tôi đồng ý để {company} lưu các thông tin này nhằm quản lý khách ra vào. Thông tin sẽ được xóa sau {days} ngày.',
			required: 'Vui lòng điền họ tên, công ty, số điện thoại và đánh dấu đồng ý.',
			walkDone: 'Cảm ơn bạn! Vui lòng ngồi chờ, lễ tân sẽ hỗ trợ bạn ngay.',
			walkTold: 'Chúng tôi cũng đã báo cho {host}.',
			not_found: 'Không tìm thấy thẻ khách này. Vui lòng kiểm tra lại mã hoặc hỏi nhân viên lễ tân.',
			cancelled: 'Lịch hẹn này đã bị hủy. Vui lòng hỏi nhân viên lễ tân.',
			too_early: 'Lịch hẹn của bạn bắt đầu lúc {time}. Có thể check-in trước 3 giờ.',
			expired: 'Thẻ khách này đã hết hiệu lực. Vui lòng hỏi nhân viên lễ tân.',
			not_checked_in: 'Thẻ này chưa được check-in.',
			already_out: 'Bạn đã check-out rồi. Cảm ơn bạn!',
			invalid: 'Thông tin chưa hợp lệ. Vui lòng kiểm tra lại số điện thoại.',
			network: 'Đã xảy ra lỗi. Vui lòng thử lại hoặc hỏi nhân viên lễ tân.',
			not_paired: 'Máy tính bảng này chưa được thiết lập. Vui lòng liên hệ IT.',
			noCamera: 'Không dùng được camera. Vui lòng nhập mã.',
		},
		en: {
			morning: 'Good morning', afternoon: 'Good afternoon', evening: 'Good evening',
			welcome: 'Welcome to {company}',
			havePass: 'I have a visitor pass', havePassSub: 'Invited, with a QR code',
			walkIn: 'Walk-in guest', walkInSub: 'No appointment? Register here',
			leaving: 'Leaving? Check out',
			scanIn: 'Hold the QR code on your visitor pass up to the camera.',
			scanOut: 'Hold your visitor pass QR code up to the camera to check out.',
			typeCode: 'Type my code', codeTitle: 'Type the 6-character code from your email',
			checkIn: 'Check in', checkOut: 'Check out', back: 'Back', send: 'Send',
			checking: 'Checking…',
			hello: 'Welcome, {guest}!',
			told: 'We have let {host} know you are here. Please take a seat.',
			already: 'You are already checked in. {host} knows you are here.',
			room: 'Meeting room: {room}',
			bye: 'Thank you for visiting, {guest}. Goodbye!',
			noPassOut: 'No visitor pass? Please tell reception when you leave.',
			name: 'Your name', company: 'Company', phone: 'Phone',
			host: 'Who are you visiting?', hostHint: 'Type at least {n} letters of their name', notSure: "I'm not sure", change: 'Change',
			purpose: 'Purpose of visit', optional: '(optional)',
			purposeChoose: 'Choose a purpose…', purposeOther: 'Other…', purposeOtherHint: 'Please tell us the purpose',
			requiredLegend: '* Required', fieldRequired: 'Please fill in this field', consentRequired: 'Please tick the box to continue',
			noHostFound: 'No match. Try another spelling or choose "I\'m not sure".',
			consent: 'I agree that {company} stores these details to manage building visitors. They are deleted after {days} days.',
			required: 'Please fill in your name, company and phone, and tick the consent box.',
			walkDone: 'Thank you! Please take a seat. Reception will be with you shortly.',
			walkTold: 'We have also let {host} know.',
			not_found: 'We could not find this pass. Please check the code or ask reception staff.',
			cancelled: 'This visit was cancelled. Please ask reception staff.',
			too_early: 'Your visit starts at {time}. Check-in opens 3 hours before.',
			expired: 'This pass is no longer valid. Please ask reception staff.',
			not_checked_in: 'This pass is not checked in.',
			already_out: 'You have already checked out. Thank you!',
			invalid: 'Some details are not valid. Please check your phone number.',
			network: 'Something went wrong. Please try again or ask reception staff.',
			not_paired: 'This tablet is not set up for check-in. Please ask IT.',
			noCamera: 'The camera is not available. Please type your code.',
		},
		ja: {
			morning: 'おはようございます', afternoon: 'こんにちは', evening: 'こんばんは',
			welcome: '{company}へようこそ',
			havePass: '来訪者パスをお持ちの方', havePassSub: '事前にご招待いただいた方（QRコード）',
			walkIn: 'ご予約のないお客様', walkInSub: 'こちらで受付してください',
			leaving: 'お帰りの方（退館）',
			scanIn: '来訪者パスのQRコードをカメラにかざしてください。',
			scanOut: '退館の際は来訪者パスのQRコードをカメラにかざしてください。',
			typeCode: 'コードを入力する', codeTitle: 'メールに記載された6文字のコードを入力してください',
			checkIn: '受付する', checkOut: '退館する', back: '戻る', send: '送信',
			checking: '確認中…',
			hello: '{guest}様、ようこそ',
			told: '{host}にご到着をお知らせしました。おかけになってお待ちください。',
			already: 'すでに受付済みです。{host}にお知らせ済みです。',
			room: '会議室：{room}',
			bye: '{guest}様、ご来訪ありがとうございました。',
			noPassOut: 'パスをお持ちでない方は、お帰りの際に受付にお声がけください。',
			name: 'お名前', company: '会社名', phone: '電話番号',
			host: '訪問先の担当者', hostHint: 'お名前を{n}文字以上入力してください', notSure: 'わからない', change: '変更',
			purpose: 'ご用件', optional: '（任意）',
			purposeChoose: 'ご用件を選択…', purposeOther: 'その他…', purposeOtherHint: 'ご用件をご記入ください',
			requiredLegend: '* 必須', fieldRequired: 'この項目を入力してください', consentRequired: '続けるには同意にチェックしてください',
			noHostFound: '該当者が見つかりません。別の綴りをお試しいただくか「わからない」を選択してください。',
			consent: '来訪者管理のため、{company}がこの情報を保存することに同意します。情報は{days}日後に削除されます。',
			required: 'お名前、会社名、電話番号を入力し、同意にチェックしてください。',
			walkDone: 'ありがとうございます。おかけになってお待ちください。受付担当がすぐに参ります。',
			walkTold: '{host}にもお知らせしました。',
			not_found: 'パスが見つかりません。コードをご確認いただくか、受付スタッフにお声がけください。',
			cancelled: 'このご来訪はキャンセルされています。受付スタッフにお声がけください。',
			too_early: 'ご来訪は{time}からです。受付は開始3時間前から可能です。',
			expired: 'このパスは有効期限が切れています。受付スタッフにお声がけください。',
			not_checked_in: 'このパスは受付されていません。',
			already_out: 'すでに退館済みです。ありがとうございました。',
			invalid: '入力内容に誤りがあります。電話番号をご確認ください。',
			network: 'エラーが発生しました。もう一度お試しいただくか、受付スタッフにお声がけください。',
			not_paired: 'このタブレットは受付用に設定されていません。IT担当にお問い合わせください。',
			noCamera: 'カメラが使用できません。コードを入力してください。',
		},
	}

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

	function start() {
		const root = document.getElementById('kv-kiosk')
		if (!root) return
		const cfg = root.dataset
		let look = {}
		try { look = JSON.parse(cfg.appearance || '{}') } catch (e) { /* use defaults */ }
		if (look.accent) {
			root.style.setProperty('--kk-accent', look.accent)
			root.style.setProperty('--kk-accent-text', look.accentText || '#fff')
		}
		const loadedAt = Date.now()
		const RELOAD_AFTER_MS = 15 * 60 * 1000 // pick up new settings and app updates

		let purposes = []
		try { purposes = JSON.parse(cfg.purposes || '[]') } catch (e) { /* only "Other" */ }
		const maskHosts = cfg.maskHosts === '1'
		const MIN_HOST_LETTERS = maskHosts ? 3 : 2
		const newWalkin = () => ({
			guestName: '', guestCompany: '', guestPhone: '', purpose: '', purposeOther: false,
			host: null, notSure: false, consent: false, error: '', errors: {},
		})

		const state = {
			lang: DEFAULT_LANG,
			screen: 'welcome', // welcome | scan | code | walkin | busy | result
			purpose: 'in',     // in | out (for scan / code)
			result: null,
			walkin: newWalkin(),
		}
		let idleTimer = null
		let resultTimer = null
		let lastScan = { value: '', at: 0 }

		const t = (key, params) => {
			let text = (T[state.lang] && T[state.lang][key]) || T.en[key] || key
			const all = Object.assign({ company: cfg.company, days: cfg.retentionDays }, params || {})
			for (const [k, v] of Object.entries(all)) text = text.split('{' + k + '}').join(v)
			return text
		}

		function go(screen, extra) {
			if (screen !== 'result') clearTimeout(resultTimer) // don't let an old result timer interrupt the next guest
			if (screen === 'scan' && cameraFailed) screen = 'code'
			Object.assign(state, { screen }, extra || {})
			if (screen === 'welcome') {
				state.lang = DEFAULT_LANG
				state.walkin = newWalkin()
			}
			if (screen === 'scan') startCamera(); else stopCamera()
			render()
			touch()
		}

		// Back to the welcome screen after a minute without a touch.
		function touch() {
			clearTimeout(idleTimer)
			if (state.screen !== 'welcome' && state.screen !== 'result') {
				idleTimer = setTimeout(() => go('welcome'), IDLE_SECONDS * 1000)
			}
		}
		document.addEventListener('pointerdown', touch, true)
		document.addEventListener('keydown', touch, true)

		// --------------------------------------------------------- camera

		const video = h('video', { class: 'kk-video', autoplay: true, muted: true, playsinline: true })
		const canvas = document.createElement('canvas')
		const ctx = canvas.getContext('2d', { willReadFrequently: true })
		let stream = null
		let cameraFailed = false

		async function startCamera() {
			if (stream || cameraFailed) return
			try {
				stream = await navigator.mediaDevices.getUserMedia({
					video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false,
				})
				if (state.screen !== 'scan') { stopCamera(); return }
				video.srcObject = stream
				await video.play()
				scanLoop()
			} catch (e) {
				console.warn('Visitors kiosk: camera not available', e)
				cameraFailed = true
				stream = null
				if (state.screen === 'scan') go('code')
			}
		}

		function stopCamera() {
			if (stream) stream.getTracks().forEach(track => track.stop())
			stream = null
			video.srcObject = null
		}

		function scanLoop() {
			if (!stream || state.screen !== 'scan') return
			if (video.readyState >= 2 && window.jsQR && video.videoWidth > 0) {
				const w = Math.min(640, video.videoWidth)
				const hgt = Math.round(video.videoHeight * (w / video.videoWidth))
				canvas.width = w
				canvas.height = hgt
				ctx.drawImage(video, 0, 0, w, hgt)
				const found = window.jsQR(ctx.getImageData(0, 0, w, hgt).data, w, hgt, { inversionAttempts: 'dontInvert' })
				if (found && found.data) {
					const now = Date.now()
					if (found.data !== lastScan.value || now - lastScan.at > 6000) {
						lastScan = { value: found.data, at: now }
						submitPass(found.data)
						return
					}
				}
			}
			setTimeout(() => requestAnimationFrame(scanLoop), 200)
		}

		// ----------------------------------------------------------- server

		async function post(url, body) {
			try {
				const response = await fetch(url, {
					method: 'POST', credentials: 'omit',
					headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
					body: JSON.stringify(body),
				})
				return await response.json()
			} catch (e) {
				return { ok: false, error: 'network' }
			}
		}

		async function submitPass(value) {
			const out = state.purpose === 'out'
			go('busy')
			const res = await post(out ? cfg.checkOutUrl : cfg.checkInUrl, { value })
			showResult(Object.assign({ kind: out ? 'out' : 'in' }, res))
		}

		/** Which required fields are still empty? Keys match the field names in walkInView. */
		function missingWalkIn(w) {
			const missing = {}
			if (!w.guestName.trim()) missing.guestName = 'fieldRequired'
			if (!w.guestCompany.trim()) missing.guestCompany = 'fieldRequired'
			if (!w.guestPhone.trim()) missing.guestPhone = 'fieldRequired'
			if (w.purposeOther && !w.purpose.trim()) missing.purpose = 'purposeOtherHint'
			if (!w.consent) missing.consent = 'consentRequired'
			return missing
		}

		async function submitWalkIn() {
			const w = state.walkin
			w.errors = missingWalkIn(w)
			if (Object.keys(w.errors).length) {
				w.error = t('required')
				render()
				const first = document.querySelector('#kv-kiosk [aria-invalid="true"]')
				if (first) first.scrollIntoView({ block: 'center', behavior: 'smooth' })
				return
			}
			w.error = ''
			go('busy')
			const res = await post(cfg.walkInUrl, {
				guestName: w.guestName, guestCompany: w.guestCompany, guestPhone: w.guestPhone,
				purpose: w.purpose, hostUid: w.host ? w.host.id : '', consent: true, language: state.lang,
			})
			if (!res.ok && (res.error === 'invalid' || res.error === 'consent')) {
				w.error = t(res.error === 'consent' ? 'required' : 'invalid')
				go('walkin')
				return
			}
			showResult(Object.assign({ kind: 'walkin' }, res))
		}

		function showResult(res) {
			clearTimeout(resultTimer)
			go('result', { result: res })
			resultTimer = setTimeout(() => go('welcome'), (res.ok ? RESULT_SECONDS : RESULT_SECONDS + 4) * 1000)
		}

		// ------------------------------------------------------------ views

		function greeting() {
			const hour = new Date().getHours()
			return t(hour < 11 ? 'morning' : hour < 18 ? 'afternoon' : 'evening')
		}

		function clock() {
			const now = new Date()
			return h('p', { class: 'kk-clock' },
				h('span', { class: 'kk-clock__time' }, now.toLocaleTimeString(LOCALES[state.lang], { hour: '2-digit', minute: '2-digit' })),
				h('span', { class: 'kk-clock__date' }, now.toLocaleDateString(LOCALES[state.lang], { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })),
			)
		}

		function topBar() {
			return h('nav', { class: 'kk-top' },
				state.screen === 'welcome'
					? h('span', { class: 'kk-top__company' }, look.logoUrl ? '' : cfg.company) // the logo already says it
					: h('button', { type: 'button', class: 'kk-lang', onclick: () => go('welcome') }, '‹ ' + t('back')),
				h('span', { class: 'kk-langs' },
					[['vi', 'Tiếng Việt'], ['en', 'English'], ['ja', '日本語']].map(([code, label]) =>
						h('button', {
							type: 'button', lang: code,
							class: 'kk-lang' + (code === state.lang ? ' is-active' : ''),
							'aria-pressed': String(code === state.lang),
							onclick: () => { state.lang = code; render() },
						}, label)),
					document.fullscreenEnabled
						? h('button', { type: 'button', class: 'kk-lang', 'aria-label': 'Full screen', onclick: () => document.documentElement.requestFullscreen().catch(() => {}) }, '⛶')
						: null,
				),
			)
		}

		function custom(field) {
			const text = look.texts && look.texts[state.lang] && look.texts[state.lang][field]
			return text ? text.split('{company}').join(cfg.company) : ''
		}

		function welcomeView() {
			const message = custom('message')
			return h('main', { class: 'kk-main kk-welcome-screen' },
				h('div', { class: 'kk-hero' },
					look.logoUrl ? h('img', { class: 'kk-logo', src: look.logoUrl, alt: '' }) : null,
					h('p', { class: 'kk-greeting' }, greeting()),
					h('h1', { class: 'kk-title' }, custom('title') || t('welcome')),
					clock(),
					message ? h('p', { class: 'kk-message' }, message) : null,
				),
				h('div', { class: 'kk-choices' + (look.walkIn === false ? ' kk-choices--single' : '') },
					h('button', { type: 'button', class: 'kk-choice kk-choice--primary', onclick: () => go('scan', { purpose: 'in' }) },
						h('span', { class: 'kk-choice__icon', 'aria-hidden': 'true' }, '▣'),
						h('span', { class: 'kk-choice__label' }, t('havePass')),
						h('span', { class: 'kk-choice__sub' }, t('havePassSub'))),
					look.walkIn === false ? null : h('button', { type: 'button', class: 'kk-choice', onclick: () => go('walkin') },
						h('span', { class: 'kk-choice__icon', 'aria-hidden': 'true' }, '✎'),
						h('span', { class: 'kk-choice__label' }, t('walkIn')),
						h('span', { class: 'kk-choice__sub' }, t('walkInSub'))),
				),
				look.checkOut === false ? null : h('button', { type: 'button', class: 'kk-leave', onclick: () => go('scan', { purpose: 'out' }) }, t('leaving')),
			)
		}

		function scanView() {
			const out = state.purpose === 'out'
			return h('main', { class: 'kk-main' },
				h('div', { class: 'kk-camera' }, video, h('div', { class: 'kk-frame', 'aria-hidden': 'true' })),
				h('p', { class: 'kk-text' }, t(out ? 'scanOut' : 'scanIn')),
				h('button', { type: 'button', class: 'kk-button kk-button--ghost', onclick: () => go('code') }, t('typeCode')),
				out ? h('p', { class: 'kk-text kk-muted kk-small' }, t('noPassOut')) : null,
			)
		}

		function codeView() {
			const input = h('input', {
				class: 'kk-code-input', type: 'text', maxlength: 6, autocomplete: 'off', autocapitalize: 'characters',
				spellcheck: 'false', 'aria-label': t('codeTitle'),
				oninput: e => { e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '') },
			})
			setTimeout(() => input.focus(), 50)
			return h('main', { class: 'kk-main' },
				cameraFailed ? h('p', { class: 'kk-text kk-muted' }, t('noCamera')) : null,
				h('form', { class: 'kk-code', onsubmit: e => { e.preventDefault(); if (input.value.length === 6) submitPass(input.value) } },
					h('p', { class: 'kk-text' }, t('codeTitle')),
					input,
					h('button', { type: 'submit', class: 'kk-button' }, t(state.purpose === 'out' ? 'checkOut' : 'checkIn')),
				),
			)
		}

		function walkInView() {
			const w = state.walkin

			/** Clears a field's error as soon as the guest starts fixing it, without redrawing the form. */
			const clearError = (key, el) => {
				if (!w.errors[key]) return
				delete w.errors[key]
				const wrap = el.closest('.kk-field, .kk-consent')
				if (wrap) wrap.classList.remove('has-error')
				el.removeAttribute('aria-invalid')
				const message = wrap && wrap.querySelector('.kk-field__error')
				if (message) message.remove()
				if (!Object.keys(w.errors).length) {
					w.error = ''
					const summary = document.querySelector('#kv-kiosk .kk-form > .kk-error')
					if (summary) summary.remove()
				}
			}
			const bind = key => e => { w[key] = e.target.value; clearError(key, e.target) }
			const errorLine = key => w.errors[key] ? h('span', { class: 'kk-field__error', role: 'alert' }, t(w.errors[key])) : null
			const mark = key => w.errors[key] ? 'true' : null

			/** A labelled field. `required` adds the red star; the error shows right under the input. */
			const field = (key, label, input, required) => h('label', { class: 'kk-field' + (w.errors[key] ? ' has-error' : '') },
				h('span', { class: 'kk-field__label' },
					label,
					required ? h('span', { class: 'kk-req', 'aria-hidden': 'true' }, ' *') : h('span', { class: 'kk-opt' }, ' ' + t('optional'))),
				input,
				errorLine(key))
			const text = (key, type, max) => h('input', {
				class: 'kk-input', type, maxlength: max, autocomplete: 'off', value: w[key],
				required: true, 'aria-invalid': mark(key), oninput: bind(key),
			})

			// Who are you visiting? (optional)
			let hostPart
			if (w.host || w.notSure) {
				hostPart = h('div', { class: 'kk-host-chosen' },
					h('span', {}, w.host ? w.host.name : t('notSure')),
					h('button', { type: 'button', class: 'kk-link', onclick: () => { w.host = null; w.notSure = false; render() } }, t('change')))
			} else {
				const results = h('div', { class: 'kk-host-results', role: 'listbox' })
				let timer = null
				let latest = 0
				const search = h('input', {
					class: 'kk-input', type: 'search', autocomplete: 'off', placeholder: t('hostHint', { n: MIN_HOST_LETTERS }), 'aria-label': t('host'),
					oninput: e => {
						clearTimeout(timer)
						const q = e.target.value.trim()
						latest++
						if (q.length < MIN_HOST_LETTERS) { results.replaceChildren(); return }
						const mine = latest
						timer = setTimeout(async () => {
							try {
								const res = await fetch(cfg.hostsUrl + '?q=' + encodeURIComponent(q), { credentials: 'omit', headers: { Accept: 'application/json' } })
								const data = await res.json()
								if (mine !== latest) return // a newer search is already on its way
								const hosts = data.hosts || []
								results.replaceChildren(...(hosts.length
									? hosts.map(host => h('button', { type: 'button', class: 'kk-host', role: 'option', onclick: () => { w.host = host; render() } }, host.name))
									: [h('p', { class: 'kk-text kk-muted' }, t('noHostFound'))]))
							} catch (err) { /* ignore; the guest can pick "I'm not sure" */ }
						}, 300)
					},
				})
				hostPart = h('div', { class: 'kk-host-pick' }, search, results,
					h('button', { type: 'button', class: 'kk-link', onclick: () => { w.notSure = true; render() } }, t('notSure')))
			}

			// Purpose of visit: a drop-down from the settings, plus "Other" with a free text box.
			const OTHER = '__other__'
			const select = h('select', {
				class: 'kk-input kk-select', 'aria-label': t('purpose'),
				onchange: e => {
					const other = e.target.value === OTHER
					w.purposeOther = other
					w.purpose = other ? '' : e.target.value
					delete w.errors.purpose
					render()
					if (other) { const box = document.querySelector('#kv-kiosk .kk-purpose-other'); if (box) box.focus() }
				},
			},
				h('option', { value: '' }, t('purposeChoose')),
				purposes.map(p => h('option', { value: p.value, selected: !w.purposeOther && w.purpose === p.value }, (p.labels && p.labels[state.lang]) || p.value)),
				h('option', { value: OTHER, selected: w.purposeOther }, t('purposeOther')))
			const purposePart = h('div', { class: 'kk-purpose' },
				select,
				w.purposeOther ? h('input', {
					class: 'kk-input kk-purpose-other', type: 'text', maxlength: 1000, autocomplete: 'off', value: w.purpose,
					placeholder: t('purposeOtherHint'), 'aria-label': t('purposeOtherHint'), 'aria-invalid': mark('purpose'), oninput: bind('purpose'),
				}) : null)

			return h('main', { class: 'kk-main kk-main--form' },
				h('h1', { class: 'kk-subtitle' }, t('walkIn')),
				h('p', { class: 'kk-legend' }, h('span', { class: 'kk-req' }, '*'), ' ', t('requiredLegend').replace(/^\*\s*/, '')),
				h('form', { class: 'kk-form', novalidate: true, onsubmit: e => { e.preventDefault(); submitWalkIn() } },
					h('div', { class: 'kk-form__row' },
						field('guestName', t('name'), text('guestName', 'text', 255), true),
						field('guestCompany', t('company'), text('guestCompany', 'text', 255), true),
					),
					h('div', { class: 'kk-form__row' },
						field('guestPhone', t('phone'), text('guestPhone', 'tel', 64), true),
						h('div', { class: 'kk-field' }, h('span', { class: 'kk-field__label' }, t('host'), h('span', { class: 'kk-opt' }, ' ' + t('optional'))), hostPart),
					),
					h('div', { class: 'kk-field' + (w.errors.purpose ? ' has-error' : '') },
						h('span', { class: 'kk-field__label' }, t('purpose'), h('span', { class: 'kk-opt' }, ' ' + t('optional'))),
						purposePart,
						errorLine('purpose')),
					h('label', { class: 'kk-consent' + (w.errors.consent ? ' has-error' : '') },
						h('input', { type: 'checkbox', checked: w.consent, 'aria-invalid': mark('consent'), onchange: e => { w.consent = e.target.checked; clearError('consent', e.target) } }),
						h('span', {}, t('consent'), h('span', { class: 'kk-req', 'aria-hidden': 'true' }, ' *')),
						errorLine('consent')),
					w.error ? h('p', { class: 'kk-error', role: 'alert' }, w.error) : null,
					h('button', { type: 'submit', class: 'kk-button' }, t('send')),
				),
			)
		}

		function resultView() {
			const res = state.result
			let lines
			if (!res.ok) {
				const time = res.startAt
					? new Date(res.startAt * 1000).toLocaleTimeString(LOCALES[state.lang], { hour: '2-digit', minute: '2-digit' })
					: ''
				lines = [h('p', { class: 'kk-text' }, t(T.en[res.error] ? res.error : 'network', { time }))]
			} else if (res.kind === 'out') {
				lines = [h('p', { class: 'kk-big' }, t('bye', { guest: res.guestName }))]
			} else if (res.kind === 'walkin') {
				lines = [
					h('p', { class: 'kk-big' }, t('walkDone')),
					res.hostName ? h('p', { class: 'kk-text kk-muted' }, t('walkTold', { host: res.hostName })) : null,
				]
			} else {
				lines = [
					h('p', { class: 'kk-big' }, t('hello', { guest: res.guestName })),
					h('p', { class: 'kk-text' }, t(res.already ? 'already' : 'told', { host: res.hostName })),
					res.roomName ? h('p', { class: 'kk-text kk-muted' }, t('room', { room: res.roomName })) : null,
				]
			}
			return h('main', { class: 'kk-main' },
				h('div', { class: 'kk-result ' + (res.ok ? 'is-ok' : 'is-error'), role: 'status', 'aria-live': 'assertive' },
					h('div', { class: 'kk-result__icon', 'aria-hidden': 'true' }, res.ok ? '✓' : '!'),
					lines,
					h('button', { type: 'button', class: 'kk-button kk-button--ghost', onclick: () => go('welcome') }, t('back')),
				),
			)
		}

		function render() {
			document.documentElement.lang = state.lang
			const view = {
				welcome: welcomeView,
				scan: scanView,
				code: codeView,
				walkin: walkInView,
				busy: () => h('main', { class: 'kk-main' }, h('p', { class: 'kk-text kk-muted', role: 'status' }, t('checking'))),
				result: resultView,
			}[state.screen]()
			root.replaceChildren(topBar(), view)
			// Browsers pause a <video> when it is taken out of the page; resume it after re-rendering.
			if (stream && video.isConnected && video.paused) video.play().catch(() => {})
		}

		// Keep the clock and greeting fresh on the welcome screen.
		setInterval(() => {
			if (state.screen !== 'welcome') return
			if (Date.now() - loadedAt > RELOAD_AFTER_MS && navigator.onLine !== false) {
				window.location.reload() // only while nobody is using the tablet
				return
			}
			render()
		}, 30 * 1000)

		async function keepScreenOn() {
			try { if ('wakeLock' in navigator) await navigator.wakeLock.request('screen') } catch (e) { /* fine */ }
		}
		document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') keepScreenOn() })
		keepScreenOn()

		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) cameraFailed = true
		go('welcome')
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
