/**
 * NZ SEO Audit — admin helpers.
 * The score itself is computed server-side on save; this script only
 * highlights the keyword field when it is empty, so editors notice
 * that two checks cannot run without a target keyword.
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  ready(function () {
    var input = document.getElementById('nzsa-keyword');
    if (!input) {
      return;
    }
    function hint() {
      input.style.borderColor = input.value.trim() === '' ? '#d97706' : '';
      input.title = input.value.trim() === ''
        ? 'Set a target keyword to enable the keyword checks.'
        : '';
    }
    input.addEventListener('input', hint);
    hint();
  });
})();
