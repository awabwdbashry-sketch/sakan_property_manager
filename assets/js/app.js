(function () {
  'use strict';
  var body = document.body;
  function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) {} return null; }

  // القائمة الجانبية: طي على سطح المكتب، منزلقة على الموبايل
  if (ls('sakan.sb') === '1') body.classList.add('sb-collapsed');
  var tg = document.getElementById('sbToggle');
  if (tg) tg.addEventListener('click', function () {
    if (window.matchMedia('(max-width: 900px)').matches) { body.classList.toggle('sb-open'); }
    else { body.classList.toggle('sb-collapsed'); ls('sakan.sb', body.classList.contains('sb-collapsed') ? '1' : '0'); }
  });
  var scrim = document.getElementById('scrim');
  if (scrim) scrim.addEventListener('click', function () { body.classList.remove('sb-open'); });

  // قائمة المستخدم
  var ub = document.getElementById('userBtn'), up = document.getElementById('userPop');
  if (ub && up) {
    ub.addEventListener('click', function (e) { e.stopPropagation(); up.hidden = !up.hidden; });
    document.addEventListener('click', function (e) { if (!up.contains(e.target)) up.hidden = true; });
  }

  // إغلاق التنبيهات
  document.addEventListener('click', function (e) {
    if (e.target.classList.contains('alert-x')) e.target.parentNode.remove();
  });

  // نافذة التأكيد للنماذج ذات data-confirm
  var modal = document.getElementById('confirmModal'), pending = null;
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.dataset && f.dataset.confirm && !f.dataset.confirmed) {
      e.preventDefault();
      pending = f;
      document.getElementById('cmText').textContent = f.dataset.confirm;
      modal.hidden = false;
      document.getElementById('cmYes').focus();
    }
  });
  if (modal) {
    document.getElementById('cmNo').addEventListener('click', function () { modal.hidden = true; pending = null; });
    document.getElementById('cmYes').addEventListener('click', function () {
      if (pending) { pending.dataset.confirmed = '1'; modal.hidden = true; pending.submit(); }
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) { modal.hidden = true; pending = null; } });
  }

  // منع الإرسال المزدوج
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || f.dataset.noLock) return;
    if (f.dataset.submitting) { e.preventDefault(); return; }
    f.dataset.submitting = '1';
    var btns = f.querySelectorAll('button[type=submit],button:not([type])');
    setTimeout(function () { btns.forEach(function (b) { b.disabled = true; }); }, 0);
    setTimeout(function () { delete f.dataset.submitting; btns.forEach(function (b) { b.disabled = false; }); }, 8000);
  });

  // عارض الصور: لا يُفتح إلا بالضغط على الصورة
  var lb = document.getElementById('lightbox');
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (t.matches && t.matches('img[data-lightbox]')) {
      lb.querySelector('img').src = t.src; lb.hidden = false;
    } else if (lb && !lb.hidden) { lb.hidden = true; lb.querySelector('img').removeAttribute('src'); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { if (lb) lb.hidden = true; if (modal) { modal.hidden = true; pending = null; } }
  });

  // تحميل تلقائي للفلاتر عند تغيير القوائم
  document.querySelectorAll('form.filters select[data-auto]').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
  });

  // تعبئة الوحدات حسب العقار (قوائم تابعة)
  document.querySelectorAll('select[data-units-of]').forEach(function (us) {
    var ps = document.querySelector(us.dataset.unitsOf);
    if (!ps) return;
    function apply() {
      var pid = ps.value, any = false;
      Array.prototype.forEach.call(us.options, function (o) {
        if (!o.value) return;
        var show = !pid || o.dataset.property === pid;
        o.hidden = !show; o.disabled = !show;
        if (!show && o.selected) us.value = '';
      });
    }
    ps.addEventListener('change', apply); apply();
  });

  // حساب تلقائي لمبلغ كامل المتبقي
  document.querySelectorAll('[data-fill]').forEach(function (b) {
    b.addEventListener('click', function () { var t = document.querySelector(b.dataset.fill); if (t) { t.value = b.dataset.value; t.focus(); } });
  });
})();

/* ===== إضافات: قائمة الجرس، إخفاء التنبيهات الناجحة، حالة التحميل ===== */
(function () {
  var bell = document.getElementById('bellBtn'), pop = document.getElementById('bellPop');
  if (bell && pop) {
    bell.addEventListener('click', function (e) { e.preventDefault(); pop.hidden = !pop.hidden; bell.setAttribute('aria-expanded', pop.hidden ? 'false' : 'true'); });
    document.addEventListener('click', function (e) { if (!pop.hidden && !pop.contains(e.target) && !bell.contains(e.target)) pop.hidden = true; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') pop.hidden = true; });
  }
  document.querySelectorAll('.alert-success').forEach(function (a) {
    setTimeout(function () { a.classList.add('is-leaving'); setTimeout(function () { if (a.parentNode) a.parentNode.removeChild(a); }, 400); }, 7000);
  });
  document.addEventListener('submit', function (e) {
    var f = e.target, b = e.submitter || f.querySelector('button[type=submit]');
    if (b && !e.defaultPrevented) b.classList.add('is-loading');
  }, true);
  window.addEventListener('pageshow', function () { document.querySelectorAll('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); }); });
})();
