document.addEventListener("DOMContentLoaded", function () {
    const tipo = document.getElementById("tipo_viagem");
    const campoVolta = document.getElementById("campo-volta");

    if (!tipo || !campoVolta) return;

    const inputVolta = campoVolta.querySelector("input");

    function atualizar() {
        const idaVolta = tipo.value === "ida_volta";

        campoVolta.hidden = !idaVolta;
        inputVolta.required = idaVolta;
        inputVolta.disabled = !idaVolta;

        if (!idaVolta) {
            inputVolta.value = "";
        }
    }

    tipo.addEventListener("change", atualizar);
    atualizar();
});