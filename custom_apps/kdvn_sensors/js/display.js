/**
 * Sensor display: one block per sensor (name, office, tiles for temperature, humidity, CO2) and a clock.
 * Refreshes every 60 s without reloading. URL options: &building=ID (one office)  &lang=en|vi|ja  &theme=dark|light
 * Plain JavaScript, no build step. All text is inserted with textContent.
 */
(function () {
	'use strict'

	const LOCALES = { vi: 'vi-VN', en: 'en-GB', ja: 'ja-JP' }
	const T = {
		en: { temperature: 'Temperature', humidity: 'Humidity', co2: 'CO2', Comfortable: 'Comfortable', Normal: 'Normal', Good: 'Good', Warning: 'Warning', Bad: 'Bad', NA: 'NA', source: 'Indoor sensor', none: 'No data source', offline: 'Connection lost, retrying', measured: 'Measured {time}', noSensors: 'No sensor for this office', fanOn: 'Exhaust fan running', fanOff: 'Exhaust fan off', fanNA: 'Exhaust fan NA' },
		vi: { temperature: 'Nhiệt độ', humidity: 'Độ ẩm', co2: 'CO2', Comfortable: 'Dễ chịu', Normal: 'Bình thường', Good: 'Tốt', Warning: 'Chú ý', Bad: 'Kém', NA: 'NA', source: 'Cảm biến trong nhà', none: 'Chưa có nguồn dữ liệu', offline: 'Mất kết nối – đang thử lại', measured: 'Đo lúc {time}', noSensors: 'Chưa có cảm biến cho văn phòng này', fanOn: 'Quạt hút đang chạy', fanOff: 'Quạt hút tắt', fanNA: 'Quạt hút NA' },
		ja: { temperature: '温度', humidity: '湿度', co2: 'CO2', Comfortable: '快適', Normal: '標準', Good: '良好', Warning: '注意', Bad: '要改善', NA: 'NA', source: '室内センサー', none: 'データなし', offline: '接続が切れました。再試行中…', measured: '{time}測定', noSensors: 'この拠点にはセンサーがありません', fanOn: '排気ファン運転中', fanOff: '排気ファン停止', fanNA: '排気ファン NA' },
	}

	function h(tag, cls, text) {
		const el = document.createElement(tag)
		if (cls) el.className = cls
		if (text !== undefined) el.textContent = text
		return el
	}

	function start() {
		const root = document.getElementById('ks-display')
		if (!root) return
		const params = new URLSearchParams(window.location.search)
		const lang = T[params.get('lang')] ? params.get('lang') : 'en'
		const t = T[lang]
		const locale = LOCALES[lang]
		document.documentElement.lang = lang
		document.documentElement.dataset.theme = params.get('theme') === 'light' ? 'light' : 'dark'

		const query = params.get('building') && /^\d+$/.test(params.get('building')) ? '?building=' + params.get('building') : ''
		const clock = h('div', 'ks-clock')
		const grid = h('div', 'ks-grid')
		const foot = h('div', 'ks-foot')
		root.replaceChildren(clock, grid, foot)

		function tick() {
			clock.textContent = new Date().toLocaleString(locale, { weekday: 'short', day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
		}

		function render(data) {
			if (!data.sensors.length) {
				grid.replaceChildren(h('p', 'ks-empty', t.noSensors))
				foot.textContent = ''
				return
			}
			root.dataset.count = String(Math.min(data.sensors.length, 4))
			grid.replaceChildren(...data.sensors.map(sensor => {
				const block = h('section', 'ks-sensor')
				const title = h('h2', 'ks-title', sensor.name)
				if (sensor.building) title.append(h('span', 'ks-building', ' · ' + sensor.building))
				const tiles = h('div', 'ks-tiles')
				sensor.tiles.forEach(tile => {
					const card = h('div', 'ks-tile ks-' + tile.level)
					card.append(h('div', 'ks-name', t[tile.metric]))
					const value = h('div', 'ks-value', tile.value === null ? t.NA : String(tile.value))
					if (tile.value !== null) value.append(h('span', 'ks-unit', ' ' + tile.unit))
					card.append(value, h('div', 'ks-word', t[tile.word] || tile.word))
					tiles.append(card)
				})
				block.append(title, tiles)
				if (sensor.fan) block.append(h('div', 'ks-fan ks-fan-' + sensor.fan, sensor.fan === 'on' ? t.fanOn : (sensor.fan === 'off' ? t.fanOff : t.fanNA)))
				const times = sensor.measuredAt ? [t.measured.replace('{time}', new Date(sensor.measuredAt * 1000).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' }))] : []
				block.append(h('div', 'ks-meta', (sensor.source === 'none' ? t.none : t.source) + (times.length ? ' · ' + times[0] : '')))
				return block
			}))
			foot.textContent = ''
		}

		async function load() {
			try {
				const response = await fetch(root.dataset.dataUrl + query, { headers: { Accept: 'application/json' } })
				if (!response.ok) throw new Error(String(response.status))
				render(await response.json())
			} catch (e) {
				foot.textContent = t.offline
			}
		}

		tick()
		setInterval(tick, 15000)
		load()
		setInterval(load, 60000)
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
