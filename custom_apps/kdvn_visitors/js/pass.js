/** Draws the QR code on the guest's visitor pass (qrcode-generator, bundled). */
(function () {
	'use strict'
	function start() {
		const box = document.getElementById('kv-qr')
		if (!box || typeof window.qrcode !== 'function') return
		const qr = window.qrcode(0, 'M') // 0 = pick the size automatically, M = medium error correction
		qr.addData(box.dataset.value)
		qr.make()
		box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 16, scalable: true })
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
