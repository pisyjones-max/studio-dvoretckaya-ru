/* Защита формы заявки от ботов (дополняет скрытое поле website и метку form_ts в index.html). Подключается одной строкой перед </body>:
   <script src="form-guard.js"></script>
   Существующий код формы в index.html менять не нужно. */
(function () {
  var form = document.getElementById('contact-form');
  if (!form) return;

  var state = { t: '', sig: '' };
  var origFetch = window.fetch;

  // 1. Скрытое поле-ловушка (website) в форме уже есть — его проверяет send.php.

  // 2. Подписанная метка времени от сервера (получить её может только страница сайта).
  function loadToken() {
    origFetch.call(window, 'token.php', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.t) { state.t = d.t; state.sig = d.sig; } })
      .catch(function () {});
  }
  loadToken();

  // 3. Проверка телефона до отправки (те же правила, что на сервере).
  function phoneOk(v) {
    var d = String(v || '').replace(/\D+/g, '');
    if (d.length === 10) d = '7' + d;
    if (d.length === 11 && d.charAt(0) === '8') d = '7' + d.slice(1);
    if (!/^7[3-9]\d{9}$/.test(d)) return false;
    if (/(\d)\1{6,}/.test(d)) return false;
    return true;
  }
  document.addEventListener('submit', function (e) {
    if (e.target !== form) return;
    var phone = form.elements['phone'] ? form.elements['phone'].value : '';
    if (phone && !phoneOk(phone)) {
      e.preventDefault();
      e.stopImmediatePropagation();
      var err = document.getElementById('error-phone');
      if (err) err.classList.remove('hidden');
    }
  }, true);

  // 4. Дописываем служебные поля к запросу на send.php.
  window.fetch = function (input, init) {
    try {
      var url = (typeof input === 'string') ? input : ((input && input.url) || '');
      if (/(^|\/)send\.php(\?|$)/.test(url) && init && typeof init.body === 'string') {
        var body = JSON.parse(init.body);
        body.t = state.t;
        body.sig = state.sig;
        init = Object.assign({}, init, { body: JSON.stringify(body) });
      }
    } catch (err) {}
    return origFetch.call(this, input, init);
  };
})();
