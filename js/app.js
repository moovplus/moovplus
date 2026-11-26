
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

var emailConsulta = document.getElementById('email');
var listaEmailsConsulta = document.getElementById('emails-salvos');

if (emailConsulta && listaEmailsConsulta && emailConsulta.form) {
  var chaveEmailsConsulta = 'moovplus.consultarViagem.emails';

  function lerEmailsConsulta() {
    try {
      var emails = JSON.parse(window.localStorage.getItem(chaveEmailsConsulta) || '[]');
      if (Array.isArray(emails)) {
        return emails.filter(function (email) {
          return typeof email === 'string' && email.length <= 254 && email.indexOf('@') > 0;
        });
      }
      console.error('A lista de e-mails salvos para consulta de viagem está inválida.');
    } catch (erro) {
      console.error('Não foi possível ler os e-mails salvos neste navegador.', erro);
    }
    return [];
  }

  lerEmailsConsulta().forEach(function (email) {
    var opcao = document.createElement('option');
    opcao.value = email;
    listaEmailsConsulta.appendChild(opcao);
  });

  emailConsulta.form.addEventListener('submit', function () {
    if (!emailConsulta.checkValidity()) {
      return;
    }

    var email = emailConsulta.value.trim().toLowerCase();
    var emails = lerEmailsConsulta().filter(function (salvo) {
      return salvo.toLowerCase() !== email;
    });
    emails.unshift(email);

    try {
      window.localStorage.setItem(chaveEmailsConsulta, JSON.stringify(emails.slice(0, 5)));
    } catch (erro) {
      console.error('Não foi possível salvar o e-mail neste navegador.', erro);
    }
  });
}
