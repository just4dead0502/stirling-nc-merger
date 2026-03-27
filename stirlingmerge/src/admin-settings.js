/* Admin settings page for stirlingmerge — loaded via addScript, not inline */
(function () {
	const token = OC.requestToken
	const base = OC.generateUrl('/apps/stirlingmerge/admin')

	document.getElementById('stirlingmerge-save').addEventListener('click', function () {
		const url = document.getElementById('stirlingmerge-url').value.trim()
		const apiKey = document.getElementById('stirlingmerge-apikey').value
		const msg = document.getElementById('stirlingmerge-save-msg')

		fetch(base + '/save', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				requesttoken: token,
			},
			body: JSON.stringify({ stirling_url: url, stirling_api_key: apiKey }),
		})
			.then(function (r) { return r.json() })
			.then(function (data) {
				msg.style.display = 'block'
				if (data.status === 'ok') {
					msg.style.color = 'green'
					msg.textContent = '✓ Saved'
				} else {
					msg.style.color = 'red'
					msg.textContent = '✗ ' + (data.error || 'Save failed')
				}
				setTimeout(function () { msg.style.display = 'none' }, 3000)
			})
			.catch(function () {
				msg.style.display = 'block'
				msg.style.color = 'red'
				msg.textContent = '✗ Network error'
				setTimeout(function () { msg.style.display = 'none' }, 3000)
			})
	})

	document.getElementById('stirlingmerge-test').addEventListener('click', function () {
		const result = document.getElementById('stirlingmerge-test-result')
		result.textContent = 'Testing…'
		result.style.color = ''

		fetch(base + '/test', {
			method: 'GET',
			headers: { requesttoken: token },
		})
			.then(function (r) { return r.json() })
			.then(function (data) {
				if (data.status === 'ok') {
					result.style.color = 'green'
					result.textContent = '✓ Connected — ' + (data.version || 'Stirling PDF')
				} else {
					result.style.color = 'red'
					result.textContent = '✗ ' + (data.error || 'Connection failed')
				}
			})
			.catch(function () {
				result.style.color = 'red'
				result.textContent = '✗ Network error'
			})
	})
}())
