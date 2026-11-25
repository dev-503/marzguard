(function () {
	'use strict';
	var form = document.getElementById('dot-bale-form');
	if (!form) return;
	var $ = function (id) { return document.getElementById(id); };

	function payload(action) {
		var fd = new FormData(form);
		var k = DOT_BALE.key, out = new URLSearchParams();
		out.set('action', action);
		out.set('nonce', DOT_BALE.nonce);
		['bale_bot_token', 'bale_chat_ids', 'message_template'].forEach(function (f) {
			out.set(f, (fd.get(k + '[' + f + ']') || '').toString());
		});
		['include_sku', 'include_customer_note', 'include_coupon', 'include_shipping'].forEach(function (f) {
			if (fd.get(k + '[' + f + ']')) out.set(f, '1');
		});
		return out;
	}
	function post(action) {
		return fetch(DOT_BALE.ajax, { method: 'POST', body: payload(action), credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}

	$('dot-bale-test').addEventListener('click', function () {
		var b = this, out = $('dot-bale-test-result');
		b.disabled = true; out.hidden = false; out.className = 'dot-bale__result'; out.textContent = 'در حال ارسال...';
		post('dot_bale_test').then(function (r) {
			out.textContent = (r.data && r.data.message) || '';
			out.className = 'dot-bale__result ' + (r.success ? 'ok' : 'err');
		}).catch(function () {
			out.textContent = 'ارتباط با سرور برقرار نشد.'; out.className = 'dot-bale__result err';
		}).finally(function () { b.disabled = false; });
	});

	$('dot-bale-preview').addEventListener('click', function () {
		var out = $('dot-bale-preview-out');
		out.hidden = false; out.textContent = '...';
		post('dot_bale_preview').then(function (r) {
			out.textContent = r.success ? r.data.plain : ((r.data && r.data.message) || 'خطا');
		});
	});

	var so = $('dot-bale-send-order'), no = $('dot-bale-order-no');
	function sendOrder() {
		var out = $('dot-bale-send-result'), n = no.value.trim();
		out.hidden = false;
		if (!n) { out.className = 'dot-bale__result err'; out.textContent = 'شماره سفارش را وارد کنید.'; return; }
		so.disabled = true; out.className = 'dot-bale__result'; out.textContent = 'در حال ارسال...';
		var body = new URLSearchParams({ action: 'dot_bale_send_order', nonce: DOT_BALE.nonce, number: n });
		fetch(DOT_BALE.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (r) {
				out.textContent = (r.data && r.data.message) || '';
				out.className = 'dot-bale__result ' + (r.success ? 'ok' : 'err');
				if (r.success) no.value = '';
			})
			.catch(function () { out.textContent = 'ارتباط با سرور برقرار نشد.'; out.className = 'dot-bale__result err'; })
			.finally(function () { so.disabled = false; });
	}
	if (so && no) {
		so.addEventListener('click', sendOrder);
		no.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); sendOrder(); } });
	}

	var wh = $('dot-bale-webhook');
	if (wh) wh.addEventListener('click', function () {
		var out = $('dot-bale-webhook-result');
		wh.disabled = true; out.hidden = false; out.className = 'dot-bale__result'; out.textContent = 'در حال ثبت...';
		post('dot_bale_webhook').then(function (r) {
			out.textContent = (r.data && r.data.message) || '';
			out.className = 'dot-bale__result ' + (r.success ? 'ok' : 'err');
		}).catch(function () {
			out.textContent = 'ارتباط با سرور برقرار نشد.'; out.className = 'dot-bale__result err';
		}).finally(function () { wh.disabled = false; });
	});

	$('dot-bale-reset').addEventListener('click', function () { $('dot-bale-template').value = ''; });

	form.querySelectorAll('.dot-bale__tag').forEach(function (t) {
		t.addEventListener('click', function () {
			var ta = $('dot-bale-template'), tag = t.getAttribute('data-tag');
			var s = ta.selectionStart || 0, e = ta.selectionEnd || 0;
			ta.value = ta.value.slice(0, s) + tag + ta.value.slice(e);
			ta.focus(); ta.selectionStart = ta.selectionEnd = s + tag.length;
		});
	});

	var clear = $('dot-bale-clear');
	if (clear) clear.addEventListener('click', function () {
		post('dot_bale_clear_logs').then(function () { location.reload(); });
	});
})();
