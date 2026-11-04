(function () {
  'use strict';

  var storageKey = 'gudang-theme';
  var body = document.body;
  var toggle = document.getElementById('theme-toggle');
  var savedTheme = null;

  try {
    savedTheme = window.localStorage.getItem(storageKey);
  } catch (error) {
    savedTheme = null;
  }

  function applyTheme(theme) {
    var isDark = theme === 'dark';
    body.setAttribute('data-theme', isDark ? 'dark' : 'light');
    if (toggle) {
      toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      toggle.setAttribute('title', isDark ? 'Gunakan tema terang' : 'Gunakan tema gelap');
      toggle.innerHTML = isDark
        ? '<i class="fas fa-sun mr-1" aria-hidden="true"></i>Terang'
        : '<i class="fas fa-moon mr-1" aria-hidden="true"></i>Gelap';
    }
  }

  applyTheme(savedTheme === 'dark' ? 'dark' : 'light');

  if (toggle) {
    toggle.addEventListener('click', function () {
      var nextTheme = body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(nextTheme);
      try {
        window.localStorage.setItem(storageKey, nextTheme);
      } catch (error) {
        // Tema tetap diterapkan untuk sesi saat ini jika penyimpanan browser tidak tersedia.
      }
    });
  }
}());
