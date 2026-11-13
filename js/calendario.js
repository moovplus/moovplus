
document.addEventListener("DOMContentLoaded", function () {

    const abas = document.querySelectorAll(".aba");
    const formularios = document.querySelectorAll(
        "#form-voos, #form-hoteis, #form-pacotes"
    );

    abas.forEach(function (aba) {
        aba.addEventListener("click", function () {
            const alvo = aba.dataset.alvo;

            abas.forEach(function (item) {
                item.classList.remove("ativa");
                item.setAttribute("aria-selected", "false");
            });

            aba.classList.add("ativa");
            aba.setAttribute("aria-selected", "true");

            formularios.forEach(function (formulario) {
                formulario.hidden = formulario.id !== alvo;
            });
        });
    });

    // CALENDÁRIOS
    const calendarios = document.querySelectorAll("[data-calendario]");

    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);

    const nomesMeses = [
        "Janeiro", "Fevereiro", "Março", "Abril",
        "Maio", "Junho", "Julho", "Agosto",
        "Setembro", "Outubro", "Novembro", "Dezembro"
    ];

    const diasSemana = ["DOM", "SEG", "TER", "QUA", "QUI", "SEX", "SÁB"];

    function dataParaString(data) {
        const ano = data.getFullYear();
        const mes = String(data.getMonth() + 1).padStart(2, "0");
        const dia = String(data.getDate()).padStart(2, "0");

        return `${ano}-${mes}-${dia}`;
    }

    function formatarData(valor) {
        if (!valor) return "";

        const [ano, mes, dia] = valor.split("-");
        return `${dia}/${mes}/${ano}`;
    }

    calendarios.forEach(function (area) {

        const formulario = area.closest("form");
        const modo = area.dataset.modo;

        const campoDatas = area.querySelector("[data-display]");
        const dataInicio = area.querySelector("[data-inicio]");
        const dataFim = area.querySelector("[data-fim]");
        const popup = area.querySelector("[data-popup]");
        const mesesContainer = area.querySelector("[data-meses]");
        const resumo = area.querySelector("[data-resumo]");

        const botaoAnterior = area.querySelector("[data-anterior]");
        const botaoProximo = area.querySelector("[data-proximo]");
        const botaoApagar = area.querySelector("[data-apagar]");
        const botaoAplicar = area.querySelector("[data-aplicar]");

        if (
            !formulario ||
            !campoDatas ||
            !dataInicio ||
            !dataFim ||
            !popup ||
            !mesesContainer ||
            !resumo ||
            !botaoAnterior ||
            !botaoProximo ||
            !botaoApagar ||
            !botaoAplicar
        ) {
            console.error("Estrutura incompleta no calendário:", area);
            return;
        }

        const tipoViagem = formulario.querySelector("#tipo_viagem");

        let mesAtual = new Date(
            hoje.getFullYear(),
            hoje.getMonth(),
            1
        );

        let inicioSelecionado = dataInicio.value || "";
        let fimSelecionado = dataFim.value || "";

        function somenteIda() {
            return (
                modo === "viagem" &&
                tipoViagem &&
                tipoViagem.value === "ida"
            );
        }

        function atualizarResumo() {

            if (!inicioSelecionado) {
                resumo.textContent = "Selecione as datas";
                return;
            }

            if (somenteIda()) {
                resumo.textContent =
                    `Ida: ${formatarData(inicioSelecionado)}`;
                return;
            }

            if (!fimSelecionado) {
                resumo.textContent =
                    `Ida: ${formatarData(inicioSelecionado)} · Selecione a volta`;
                return;
            }

            const inicio = new Date(inicioSelecionado + "T00:00:00");
            const fim = new Date(fimSelecionado + "T00:00:00");

            const dias = Math.round(
                (fim - inicio) / 86400000
            );

            resumo.textContent =
                `Viagem de ${dias} ${dias === 1 ? "dia" : "dias"}`;
        }

        function selecionarData(valor) {

            if (
                somenteIda() ||
                !inicioSelecionado ||
                fimSelecionado
            ) {
                inicioSelecionado = valor;
                fimSelecionado = "";
            } else if (valor < inicioSelecionado) {
                fimSelecionado = inicioSelecionado;
                inicioSelecionado = valor;
            } else if (valor === inicioSelecionado) {
                fimSelecionado = "";
            } else {
                fimSelecionado = valor;
            }

            renderizarCalendario();
        }

        function criarMes(data) {

            const ano = data.getFullYear();
            const mes = data.getMonth();

            const primeiroDia = new Date(ano, mes, 1);
            const ultimoDia = new Date(ano, mes + 1, 0);

            const container = document.createElement("div");

            const titulo = document.createElement("div");
            titulo.className = "calendario-mes-titulo";
            titulo.textContent = `${nomesMeses[mes]} ${ano}`;

            container.appendChild(titulo);

            const semana = document.createElement("div");
            semana.className = "calendario-semana";

            diasSemana.forEach(function (dia) {
                const elemento = document.createElement("span");
                elemento.textContent = dia;
                semana.appendChild(elemento);
            });

            container.appendChild(semana);

            const dias = document.createElement("div");
            dias.className = "calendario-dias";

            for (let i = 0; i < primeiroDia.getDay(); i++) {
                dias.appendChild(document.createElement("span"));
            }

            for (let numero = 1; numero <= ultimoDia.getDate(); numero++) {

                const dataDia = new Date(ano, mes, numero);
                const valor = dataParaString(dataDia);

                const botao = document.createElement("button");
                botao.type = "button";
                botao.className = "calendario-dia";
                botao.textContent = numero;

                if (dataDia < hoje) {
                    botao.disabled = true;
                }

                if (
                    valor === inicioSelecionado ||
                    valor === fimSelecionado
                ) {
                    botao.classList.add("selecionado");

                } else if (
                    inicioSelecionado &&
                    fimSelecionado &&
                    valor > inicioSelecionado &&
                    valor < fimSelecionado
                ) {
                    botao.classList.add("no-intervalo");
                }

                botao.addEventListener("click", function () {
                    selecionarData(valor);
                });

                dias.appendChild(botao);
            }

            container.appendChild(dias);

            return container;
        }

        function renderizarCalendario() {

            mesesContainer.innerHTML = "";

            mesesContainer.appendChild(criarMes(mesAtual));

            const proximoMes = new Date(
                mesAtual.getFullYear(),
                mesAtual.getMonth() + 1,
                1
            );

            mesesContainer.appendChild(criarMes(proximoMes));

            atualizarResumo();
        }

        function abrirCalendario() {
            popup.hidden = false;
            renderizarCalendario();
        }

        function fecharCalendario() {
            popup.hidden = true;
        }

        campoDatas.addEventListener("click", function (event) {
            event.stopPropagation();
            abrirCalendario();
        });

        popup.addEventListener("click", function (event) {
            event.stopPropagation();
        });

        botaoAnterior.addEventListener("click", function () {

            const anterior = new Date(
                mesAtual.getFullYear(),
                mesAtual.getMonth() - 1,
                1
            );

            if (
                anterior < new Date(
                    hoje.getFullYear(),
                    hoje.getMonth(),
                    1
                )
            ) {
                return;
            }

            mesAtual = anterior;
            renderizarCalendario();
        });

        botaoProximo.addEventListener("click", function () {

            mesAtual = new Date(
                mesAtual.getFullYear(),
                mesAtual.getMonth() + 1,
                1
            );

            renderizarCalendario();
        });

        botaoApagar.addEventListener("click", function () {

            inicioSelecionado = "";
            fimSelecionado = "";

            dataInicio.value = "";
            dataFim.value = "";
            campoDatas.value = "";

            renderizarCalendario();
        });

        botaoAplicar.addEventListener("click", function () {

            if (!inicioSelecionado) {
                alert("Selecione a data de ida ou check-in.");
                return;
            }

            if (!somenteIda() && !fimSelecionado) {
                alert("Selecione também a data de volta ou check-out.");
                return;
            }

            dataInicio.value = inicioSelecionado;
            dataFim.value = somenteIda() ? "" : fimSelecionado;

            if (somenteIda()) {
                campoDatas.value = formatarData(inicioSelecionado);
            } else {
                campoDatas.value =
                    `${formatarData(inicioSelecionado)} - ${formatarData(fimSelecionado)}`;
            }

            fecharCalendario();
        });

        if (tipoViagem) {

            tipoViagem.addEventListener("change", function () {

                if (somenteIda()) {
                    fimSelecionado = "";
                    dataFim.value = "";
                }

                campoDatas.placeholder = somenteIda()
                    ? "Selecione a data de ida"
                    : "Selecione ida e volta";

                if (!popup.hidden) {
                    renderizarCalendario();
                }
            });
        }

        document.addEventListener("click", function (event) {
            if (!area.contains(event.target)) {
                fecharCalendario();
            }
        });

        campoDatas.placeholder = somenteIda()
            ? "Selecione a data de ida"
            : "Selecione ida e volta";

    });

});
