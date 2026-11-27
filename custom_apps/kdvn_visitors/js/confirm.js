/**
 * Phone page behind the touch screen's QR code: shows what will be booked, one Confirm button.
 * Plain JavaScript, no build step. All text goes in with textContent.
 */
(function () {
	'use strict'

	const T = {
		en: { title: 'Book a meeting room', for: '{n} minutes, starting now', as: 'Booked for', confirm: 'Confirm booking', done: '✓ Confirmed', doneHelp: 'Look at the screen: the room is booked for you.',
			gone: 'This code has expired. Tap Book now on the screen again.', taken: 'This code was already used by someone else.', noEmail: 'Your account has no email address, so the room cannot be booked. Add one in Personal settings.', failed: 'Could not confirm. Try again.' },
		vi: { title: 'Đặt phòng họp', for: '{n} phút, bắt đầu ngay', as: 'Người đặt', confirm: 'Xác nhận đặt phòng', done: '✓ Đã xác nhận', doneHelp: 'Nhìn lên màn hình: phòng đã được đặt cho bạn.',
			gone: 'Mã này đã hết hạn. Hãy bấm Đặt ngay trên màn hình lần nữa.', taken: 'Mã này đã được người khác dùng.', noEmail: 'Tài khoản của bạn chưa có email nên không đặt phòng được. Hãy thêm email trong Cài đặt cá nhân.', failed: 'Không xác nhận được. Hãy thử lại.' },
		ja: { title: '会議室の予約', for: '今から{n}分間', as: '予約者', confirm: '予約を確定', done: '✓ 確定しました', doneHelp: '画面をご確認ください。会議室が予約されました。',
			gone: 'このコードは期限切れです。画面の「今すぐ予約」をもう一度押してください。', taken: 'このコードは他の人が使用済みです。', noEmail: 'メールアドレスが未登録のため予約できません。個人設定で追加してください。', failed: '確定できませんでした。もう一度お試しください。' },
	}

	function h(tag, cls, text) {
		const el = document.createElement(tag)
		if (cls) el.className = cls
		if (text !== undefined) el.textContent = text
		return el
	}

	function start() {
		const root = document.getElementById('kv-confirm')
		if (!root) return
		const t = T[root.dataset.lang] || T.en
		const fill = (text, p) => Object.entries(p).reduce((s, [k, v]) => s.split('{' + k + '}').join(v), text)
		const show = (...els) => root.replaceChildren(h('h2', '', t.title), ...els)

		if (root.dataset.valid !== '1') { show(h('p', 'kv-confirm__error', t.gone)); return }
		if (root.dataset.taken === '1') { show(h('p', 'kv-confirm__error', t.taken)); return }

		const message = h('p', 'kv-confirm__error')
		const button = h('button', 'primary', t.confirm)
		button.type = 'button'
		button.addEventListener('click', async () => {
			button.disabled = true
			message.textContent = ''
			try {
				const res = await fetch(root.dataset.confirmUrl, {
					method: 'POST',
					headers: { Accept: 'application/json', requesttoken: document.head.dataset.requesttoken || '' },
				})
				const body = await res.json()
				if (body.ok) { show(h('p', 'kv-confirm__done', t.done), h('p', '', t.doneHelp)); return }
				message.textContent = body.error === 'no_email' ? t.noEmail : body.error === 'gone' ? t.gone : t.failed
			} catch (e) {
				message.textContent = t.failed
			}
			button.disabled = false
		})
		show(
			h('p', 'kv-confirm__room', root.dataset.room),
			h('p', '', fill(t.for, { n: root.dataset.minutes })),
			h('p', 'kv-confirm__who', t.as + ': ' + root.dataset.name),
			button, message)
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
