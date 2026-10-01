(function () {
    var method = document.getElementById("pricing_method");
    if (!method) {
        return;
    }

    function sync() {
        document.querySelectorAll(".sf-dim").forEach(function (block) {
            var allowed = (block.getAttribute("data-methods") || "").split(" ");
            var show = allowed.indexOf(method.value) !== -1;
            block.hidden = !show;
            block.querySelectorAll("input, select, textarea").forEach(function (input) {
                input.disabled = !show;
            });
        });
    }

    method.addEventListener("change", sync);
    sync();
})();