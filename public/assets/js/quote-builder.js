(function () {
    var header = document.getElementById("quote-header");
    var state = document.getElementById("save-state");
    var catalogueNode = document.getElementById("quote-catalogue");
    var version = document.getElementById("version_number");
    if (!header || !catalogueNode) {
        return;
    }
    var catalogue = JSON.parse(catalogueNode.textContent || "[]");
    var productSelect = document.getElementById("product_id");
    var timer = null;
    var dirty = false;

    function product() {
        var id = productSelect ? productSelect.value : "";
        for (var i = 0; i < catalogue.length; i++) {
            if (String(catalogue[i].id) === String(id)) {
                return catalogue[i];
            }
        }
        return null;
    }

    function showFields() {
        var chosen = product();
        var method = chosen ? chosen.pricing_method : "";
        var waste = !!(chosen && ((method === "AREA" && chosen.roll_width_mm) || method === "SHEET"));
        document.querySelectorAll(".sf-line-field").forEach(function (field) {
            var inputs = (field.getAttribute("data-inputs") || "").split(" ");
            var visible = inputs.indexOf(method) !== -1 || (inputs.indexOf("WASTE") !== -1 && waste) || (inputs.indexOf("MANUAL") !== -1 && waste && document.getElementById("waste_mode") && document.getElementById("waste_mode").value === "MANUAL");
            field.hidden = !visible;
        });
    }

    function setState(text) {
        if (!state) {
            return;
        }
        state.textContent = text;
        state.setAttribute("data-state", text === "Saved" ? "saved" : "dirty");
    }

    function save() {
        if (!dirty) {
            return;
        }
        setState("Saving...");
        var body = new FormData(header);
        var token = document.querySelector('meta[name="csrf-token"]');
        fetch(header.getAttribute("data-autosave"), {
            method: "POST",
            headers: {
                "Accept": "application/json",
                "X-Requested-With": "fetch",
                "X-CSRF-Token": token ? token.getAttribute("content") : ""
            },
            body: body
        }).then(function (response) {
            return response.json().then(function (data) {
                if (response.status === 409) {
                    setState("This quotation was modified by another user. Reload before saving.");
                    return;
                }
                if (!data.ok) {
                    setState("Unsaved changes");
                    return;
                }
                dirty = false;
                if (version && data.version_number) {
                    version.value = data.version_number;
                    document.querySelectorAll('input[name="version_number"]').forEach(function (input) {
                        input.value = data.version_number;
                    });
                }
                setState("Saved");
            });
        }).catch(function () {
            setState("Unsaved changes");
        });
    }

    header.addEventListener("input", function () {
        dirty = true;
        setState("Unsaved changes");
        window.clearTimeout(timer);
        timer = window.setTimeout(save, 8000);
    });
    header.addEventListener("submit", function () {
        dirty = false;
    });

    if (productSelect) {
        productSelect.addEventListener("change", showFields);
        showFields();
    }
    var waste = document.getElementById("waste_mode");
    if (waste) {
        waste.addEventListener("change", showFields);
    }

    var lines = document.getElementById("quote-lines");
    var dragged = null;
    if (lines) {
        lines.addEventListener("dragstart", function (event) {
            dragged = event.target.closest(".sf-quote-line");
        });
        lines.addEventListener("dragover", function (event) {
            event.preventDefault();
        });
        lines.addEventListener("drop", function (event) {
            event.preventDefault();
            var target = event.target.closest(".sf-quote-line");
            if (!dragged || !target || dragged === target) {
                return;
            }
            lines.insertBefore(dragged, target);
            var form = document.getElementById("reorder-form");
            var holder = document.getElementById("reorder-ids");
            holder.innerHTML = "";
            lines.querySelectorAll("[data-line-id]").forEach(function (line) {
                var input = document.createElement("input");
                input.type = "hidden";
                input.name = "line_id[]";
                input.value = line.getAttribute("data-line-id");
                holder.appendChild(input);
            });
            form.submit();
        });
    }
})();
