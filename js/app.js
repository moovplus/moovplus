
document.querySelectorAll('.aba').forEach(function (aba) {
  aba.addEventListener('click', function () {
    document.querySelectorAll('.aba').forEach(function (a) {
      a.classList.remove('ativa');
      a.setAttribute('aria-selected', 'false');
    });
    document.querySelectorAll('.form-busca').forEach(function (f) { f.hidden = true; });

    aba.classList.add('ativa');
    aba.setAttribute('aria-selected', 'true');
    document.getElementById(aba.dataset.alvo).hidden = false;
  });
});


var checkin  = document.getElementById('checkin');
var checkout = document.getElementById('checkout');
if (checkin && checkout) {
  checkin.addEventListener('change', function () {
    checkout.min = checkin.value;
    if (checkout.value && checkout.value <= checkin.value) { checkout.value = ''; }
  });
}

var ida   = document.getElementById('ida');
var volta = document.getElementById('volta');
if (ida && volta) {
  ida.addEventListener('change', function () {
    volta.min = ida.value;
    if (volta.value && volta.value < ida.value) { volta.value = ''; }
  });
}
