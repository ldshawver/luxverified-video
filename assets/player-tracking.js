(function () {
	if (typeof luxvvPlayer === 'undefined') {
		console.warn('LUXVV: luxvvPlayer not defined');
		return;
	}

	const ajaxUrl   = luxvvPlayer.ajaxUrl;
	const nonce     = luxvvPlayer.nonce;
	const postId    = luxvvPlayer.postId;
	const videoGuid = luxvvPlayer.videoGuid || '';
	const interval  = parseInt(luxvvPlayer.timeUpdateInterval || 15, 10);
	const debug     = !!luxvvPlayer.debug;

	let lastTimeSent = 0;
	let lastEventSentAt = 0;

	function log(...args) {
		if (debug) console.log('[LUXVV]', ...args);
	}

	async function sendEvent(eventType, extra = {}) {
		const data = new FormData();
		data.append('action', 'luxvv_track_event');
		data.append('nonce', nonce);
		data.append('post_id', postId);
		data.append('video_guid', videoGuid);
		data.append('event_type', eventType);

		Object.keys(extra).forEach(k => data.append(k, extra[k]));

		try {
			const res = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data });

			// Some hosts return "0" text if handler not found. Handle both.
			const text = await res.text();
			let json = null;

			try { json = JSON.parse(text); } catch (e) {}

			if (!json || !json.success) {
				console.error('LUXVV event failed:', text);
				return;
			}
			log('event ok', eventType, json.data);
		} catch (err) {
			console.error('LUXVV fetch error:', err);
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		log('LUXVV loaded', luxvvPlayer);
		sendEvent('page_load');
	});

	// Bunny iframe postMessage support (only works if player posts messages)
	window.addEventListener('message', function (event) {
		if (!event.origin || !event.origin.includes('mediadelivery.net')) return;
		if (!event.data || typeof event.data !== 'object') return;

		const bunnyEvent = event.data.event;

		switch (bunnyEvent) {
			case 'play':  sendEvent('play'); break;
			case 'pause': sendEvent('pause'); break;
			case 'ended': sendEvent('ended'); break;
			case 'timeupdate':
				if (typeof event.data.currentTime !== 'undefined') {
					const ct = Math.floor(event.data.currentTime);
					const now = Date.now();
					if (ct >= lastTimeSent + interval && now - lastEventSentAt > 2000) {
						lastTimeSent = ct;
						lastEventSentAt = now;
						sendEvent('time_update', { current_time: ct });
					}
				}
				break;
		}
	});

	// Video.js support fallback (AIOVG / theme players)
	if (window.videojs && typeof videojs.getAllPlayers === 'function') {
		videojs.getAllPlayers().forEach(function (player) {
			player.ready(function () {
				sendEvent('player_ready');

				player.on('play', () => sendEvent('play'));
				player.on('pause', () => sendEvent('pause'));
				player.on('ended', () => sendEvent('ended'));

				player.on('timeupdate', function () {
					const ct = Math.floor(player.currentTime());
					const now = Date.now();
					if (ct >= lastTimeSent + interval && now - lastEventSentAt > 2000) {
						lastTimeSent = ct;
						lastEventSentAt = now;
						sendEvent('time_update', { current_time: ct });
					}
				});
			});
		});
	}
})();
