/** Directory page: groups on the left, members of the chosen group on the right, search across all. */
(function () {
	'use strict'
	function start() {
		const root = document.getElementById('kd-app')
		if (!root) return
		let data = null
		let current = null
		let query = ''

		const el = (tag, cls, text) => {
			const e = document.createElement(tag)
			if (cls) e.className = cls
			if (text !== undefined) e.textContent = text
			return e
		}
		// accent-free, lower-case text: "nguyen van a" finds "Nguyễn Văn A"
		const fold = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').toLowerCase()

		function personCard(p) {
			const card = el('a', 'kd-person')
			card.href = data.profileBase + encodeURIComponent(p.id)
			const img = el('img', 'kd-avatar')
			img.loading = 'lazy'
			img.alt = ''
			img.width = 44
			img.height = 44
			img.src = data.avatarBase + encodeURIComponent(p.id) + '/64'
			card.appendChild(img)
			const text = el('div', 'kd-person-text')
			text.appendChild(el('div', 'kd-name', p.name))
			if (p.email) text.appendChild(el('div', 'kd-email', p.email))
			card.appendChild(text)
			return card
		}

		function render() {
			root.textContent = ''
			const wrap = el('div', 'kd-wrap')
			wrap.appendChild(el('h2', null, 'Directory'))
			if (!data.groups.length) {
				wrap.appendChild(el('p', 'kd-empty', 'No groups are listed yet. An administrator can choose them in Administration settings > Directory.'))
				root.appendChild(wrap)
				return
			}
			const search = el('input', 'kd-search')
			search.type = 'search'
			search.placeholder = 'Search by name or email'
			search.value = query
			wrap.appendChild(search)

			const layout = el('div', 'kd-layout')
			const nav = el('div', 'kd-groups')
			const list = el('div', 'kd-members')
			layout.appendChild(nav)
			layout.appendChild(list)
			wrap.appendChild(layout)
			root.appendChild(wrap)

			function draw() {
				nav.textContent = ''
				list.textContent = ''
				const q = fold(query.trim())
				data.groups.forEach(g => {
					const b = el('button', 'kd-group' + (!q && g.id === current ? ' is-active' : ''))
					b.type = 'button'
					b.appendChild(el('span', 'kd-group-name', g.name + (g.mine ? ' ★' : '')))
					b.appendChild(el('span', 'kd-count', String(g.members.length)))
					b.addEventListener('click', () => { query = ''; search.value = ''; current = g.id; draw() })
					nav.appendChild(b)
				})
				if (q) {
					// search: every matching person once, with the groups they are in
					const found = new Map()
					data.groups.forEach(g => g.members.forEach(m => {
						if (fold(m.name + ' ' + m.email).includes(q)) {
							if (!found.has(m.id)) found.set(m.id, { m, groups: [] })
							found.get(m.id).groups.push(g.name)
						}
					}))
					list.appendChild(el('h3', null, found.size + ' found'))
					const grid = el('div', 'kd-grid')
					Array.from(found.values()).sort((a, b) => a.m.name.localeCompare(b.m.name)).forEach(({ m, groups }) => {
						const card = personCard(m)
						card.querySelector('.kd-person-text').appendChild(el('div', 'kd-in', groups.join(', ')))
						grid.appendChild(card)
					})
					list.appendChild(grid)
					return
				}
				const g = data.groups.find(x => x.id === current) || data.groups[0]
				list.appendChild(el('h3', null, g.name + ' (' + g.members.length + ')'))
				const grid = el('div', 'kd-grid')
				g.members.forEach(m => grid.appendChild(personCard(m)))
				list.appendChild(grid)
			}
			search.addEventListener('input', () => { query = search.value; draw() })
			draw()
		}

		fetch(root.dataset.apiUrl, { credentials: 'same-origin', headers: { Accept: 'application/json', requesttoken: document.head.dataset.requesttoken || '' } })
			.then(r => { if (!r.ok) throw new Error('Could not load the directory (' + r.status + ').'); return r.json() })
			.then(json => {
				data = json
				const mine = json.groups.find(g => g.mine)
				current = (mine || json.groups[0] || {}).id
				render()
			})
			.catch(e => { root.textContent = ''; root.appendChild(el('p', 'kd-empty', e.message)) })
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
	else start()
})()
