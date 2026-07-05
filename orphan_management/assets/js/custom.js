// OrphanCare - Custom JavaScript Utilities
// Additional interactive helpers loaded via footer.php

document.addEventListener('DOMContentLoaded', function() {
  
  // Confirm delete actions
  document.querySelectorAll('[data-confirm]').forEach(function(el) {
    el.addEventListener('click', function(e) {
      if (!confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // Animate small-box counters
  document.querySelectorAll('.small-box .inner h3').forEach(function(el) {
    var target = el.innerText;
    // Only animate pure numbers
    var num = parseFloat(target.replace(/[^0-9.]/g, ''));
    if (!isNaN(num) && num > 0 && num < 100000) {
      var prefix = target.replace(/[0-9.,]/g, '').trim();
      var hasDecimal = target.indexOf('.') !== -1;
      var current = 0;
      var step = Math.max(1, Math.floor(num / 30));
      var interval = setInterval(function() {
        current += step;
        if (current >= num) {
          current = num;
          clearInterval(interval);
        }
        el.innerText = prefix + (hasDecimal ? current.toFixed(2) : current.toLocaleString());
      }, 30);
    }
  });

});
