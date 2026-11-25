(function () {
	'use strict';

	function init(wrap) {
		var form = wrap.querySelector('.dot-form');
		if (!form) return;
		var result = wrap.querySelector('.dot-result');
		var err = wrap.querySelector('.dot-error');
		var btn = form.querySelector('.dot-btn');

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			err.hidden = true;
			result.innerHTML = '';

			var q = form.q.value.trim();
			if (!q) {
				err.textContent = 'لطفاً شماره سفارش یا شماره موبایل را وارد کنید.';
				err.hidden = false;
				return;
			}

			btn.disabled = true;
			var label = btn.textContent;
			btn.textContent = 'در حال جست‌وجو...';

			fetch(DOT.ajax, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res && res.success) {
						result.innerHTML = res.data.html;
						result.scrollIntoView({ behavior: 'smooth', block: 'start' });
					} else {
						err.textContent = (res && res.data && res.data.message) || 'خطایی رخ داد. دوباره تلاش کنید.';
						err.hidden = false;
					}
				})
				.catch(function () {
					err.textContent = 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.';
					err.hidden = false;
				})
				.finally(function () {
					btn.disabled = false;
					btn.textContent = label;
				});
		});
	}

	document.querySelectorAll('.dot-wrap').forEach(init);

	// دکمه کپی کد رهگیری (برای نتیجه‌های AJAX و صفحه حساب کاربری)
	document.addEventListener('click', function (e) {
		var b = e.target.closest('.dot-copy');
		if (!b) return;
		var code = b.getAttribute('data-code');
		var done = function () {
			var old = b.textContent;
			b.textContent = 'کپی شد ✓';
			setTimeout(function () { b.textContent = old; }, 1500);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(code).then(done);
		} else {
			var t = document.createElement('textarea');
			t.value = code;
			document.body.appendChild(t);
			t.select();
			try { document.execCommand('copy'); done(); } catch (x) {}
			document.body.removeChild(t);
		}
	});
})();
