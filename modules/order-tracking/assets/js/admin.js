(function () {
	'use strict';
	var btn = document.getElementById('dot-sync-start');
	if (!btn) return;

	var box = document.getElementById('dot-progress');
	var fill = document.getElementById('dot-progress-fill');
	var text = document.getElementById('dot-progress-text');
	var sum = { created: 0, linked: 0, existing: 0, skipped: 0 };

	function step(offset) {
		var body = new URLSearchParams({ action: 'dot_sync_batch', nonce: DOT_ADMIN.nonce, offset: offset });
		fetch(DOT_ADMIN.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res.success) throw new Error((res.data && res.data.message) || 'خطا');
				var d = res.data;
				Object.keys(sum).forEach(function (k) { sum[k] += d.stats[k] || 0; });
				var pct = d.total ? Math.min(100, Math.round((d.next / d.total) * 100)) : 100;
				fill.style.width = pct + '%';
				text.textContent = 'پردازش ' + d.next + ' از ' + d.total + ' سفارش (' + pct + '٪)';
				if (d.done) {
					text.textContent = 'پایان. کاربر جدید: ' + sum.created + ' | اتصال به کاربر موجود: ' + sum.linked +
						' | قبلاً متصل: ' + sum.existing + ' | ردشده (بدون موبایل و ایمیل): ' + sum.skipped;
					btn.disabled = false;
				} else {
					step(d.next);
				}
			})
			.catch(function (e) {
				text.textContent = 'خطا: ' + e.message + ' — می‌توانید دوباره اجرا کنید؛ موارد انجام‌شده تکراری نمی‌شوند.';
				btn.disabled = false;
			});
	}

	btn.addEventListener('click', function () {
		if (!window.confirm('همه‌ی سفارش‌های قبلی بررسی و مشتریان جدید به کاربران سایت اضافه شوند؟')) return;
		btn.disabled = true;
		box.hidden = false;
		fill.style.width = '0';
		sum = { created: 0, linked: 0, existing: 0, skipped: 0 };
		text.textContent = 'در حال شروع...';
		step(0);
	});
})();
