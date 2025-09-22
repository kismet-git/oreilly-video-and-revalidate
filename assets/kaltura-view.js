(() => {
	function mountPlayer(container) {
		if (container.dataset.mounted) return;
		container.dataset.mounted = '1';

		const partnerId = container.dataset.partnerid;
		const entryId = container.dataset.entryid;
		const autoplay = container.dataset.autoplay === '1';
		const poster = container.dataset.poster || '';

		const src = `https://cdnapisec.kaltura.com/p/${encodeURIComponent(partnerId)}/sp/${encodeURIComponent(partnerId)}00/embedIframeJs/uiconf_id/23448213/partner_id/${encodeURIComponent(partnerId)}?entry_id=${encodeURIComponent(entryId)}&autoPlay=${autoplay ? 'true' : 'false'}`;

		const iframe = document.createElement('iframe');
		iframe.setAttribute('allow', 'autoplay; fullscreen; encrypted-media; picture-in-picture');
		iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
		iframe.setAttribute('loading', 'lazy');
		iframe.src = src;
		iframe.style.position = 'absolute';
		iframe.style.inset = '0';
		iframe.style.width = '100%';
		iframe.style.height = '100%';
		iframe.style.border = '0';

		container.innerHTML = '';
		const wrap = document.createElement('div');
		wrap.style.position = 'relative';
		wrap.style.aspectRatio = '16/9';
		if (poster) {
			wrap.style.backgroundImage = `url("${poster}")`;
			wrap.style.backgroundSize = 'cover';
			wrap.style.backgroundPosition = 'center';
		}
		wrap.appendChild(iframe);
		container.appendChild(wrap);
	}

	function setup(el) {
		const needConsent = el.dataset.consent === '1';
		const io = new IntersectionObserver(entries => {
			entries.forEach(entry => {
				if (!entry.isIntersecting) return;
				if (needConsent && !el.dataset.consentGiven) return;
				mountPlayer(el);
				io.disconnect();
			});
		}, { rootMargin: '200px' });
		io.observe(el);

		if (needConsent) {
			const btn = el.querySelector('[data-consent-button]');
			if (btn) {
				btn.addEventListener('click', () => {
					el.dataset.consentGiven = '1';
					mountPlayer(el);
				});
			}
		}
	}

	document.addEventListener('DOMContentLoaded', () => {
		document.querySelectorAll('.oreilly-kaltura-container[data-video="kaltura"]').forEach(setup);
	});
})();
