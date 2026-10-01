(function () {
    var form = document.getElementById("calculator-form");
    var result = document.getElementById("calc-result");
    var productSelect = document.getElementById("calc-product");
    var categorySelect = document.getElementById("calc-category");
    var catalogueNode = document.getElementById("calc-catalogue");
    var categoryNode = document.getElementById("calc-categories");
    if (!form || !result || !productSelect || !catalogueNode) {
        return;
    }

    var products = JSON.parse(catalogueNode.textContent || "[]");
    var categories = JSON.parse(categoryNode ? categoryNode.textContent || "[]" : "[]");
    var timer = null;
    var wasteLabels = {
        ACTUAL: "Charge actual material",
        CONSUMED_WIDTH: "Charge consumed roll width",
        FULL_SHEET: "Charge full sheet",
        MANUAL: "Manual measure"
    };

    function categoryIds(selected) {
        if (!selected) {
            return null;
        }
        var ids = [String(selected)];
        var guard = 0;
        var grew = true;
        while (grew && guard < 8) {
            grew = false;
            guard += 1;
            categories.forEach(function (category) {
                if (category.parent_id && ids.indexOf(String(category.parent_id)) !== -1 && ids.indexOf(String(category.id)) === -1) {
                    ids.push(String(category.id));
                    grew = true;
                }
            });
        }
        return ids;
    }

    function selectedProduct() {
        var id = productSelect.value;
        for (var i = 0; i < products.length; i += 1) {
            if (String(products[i].id) === String(id)) {
                return products[i];
            }
        }
        return null;
    }

    function fillProducts() {
        var ids = categoryIds(categorySelect ? categorySelect.value : "");
        var current = productSelect.value;
        productSelect.innerHTML = '<option value="">Choose a product</option>';
        products.forEach(function (product) {
            if (ids && ids.indexOf(String(product.category_id)) === -1) {
                return;
            }
            var option = document.createElement("option");
            option.value = product.id;
            option.textContent = product.sku + " — " + product.name;
            productSelect.appendChild(option);
        });
        if (current) {
            productSelect.value = current;
        }
    }

    function showInputs() {
        var product = selectedProduct();
        var method = product ? product.pricing_method : "";
        document.querySelectorAll(".sf-calc-field").forEach(function (field) {
            var inputs = (field.getAttribute("data-inputs") || "").split(" ");
            var visible = method && inputs.indexOf(method) !== -1;
            if (field.id === "calc-waste") {
                visible = !!product && ((method === "AREA" && product.roll_width_mm) || method === "SHEET");
            }
            if (inputs.indexOf("MANUAL") !== -1) {
                var mode = form.querySelector('input[name="waste_mode"]:checked');
                visible = mode && mode.value === "MANUAL";
            }
            field.hidden = !visible;
        });
        var hint = document.getElementById("calc-hint");
        if (hint) {
            hint.hidden = !!product;
        }
    }

    function wasteChoices(product) {
        if (!product) {
            return [];
        }
        if (product.pricing_method === "SHEET") {
            return ["ACTUAL", "FULL_SHEET", "MANUAL"];
        }
        if (product.pricing_method === "AREA" && product.roll_width_mm) {
            return ["ACTUAL", "CONSUMED_WIDTH", "MANUAL"];
        }
        return [];
    }

    function renderWaste() {
        var box = document.getElementById("calc-waste-options");
        var product = selectedProduct();
        if (!box) {
            return;
        }
        var choices = wasteChoices(product);
        var current = form.querySelector('input[name="waste_mode"]:checked');
        var selected = current ? current.value : (product ? product.default_waste_policy : "ACTUAL");
        if (choices.indexOf(selected) === -1) {
            selected = choices[0] || "ACTUAL";
        }
        box.innerHTML = "";
        choices.forEach(function (choice) {
            var id = "waste-" + choice;
            var wrap = document.createElement("div");
            wrap.className = "form-check";
            wrap.innerHTML = '<input class="form-check-input" type="radio" name="waste_mode" id="' + id + '" value="' + choice + '">' +
                '<label class="form-check-label" for="' + id + '">' + (wasteLabels[choice] || choice) + "</label>";
            box.appendChild(wrap);
            if (choice === selected) {
                wrap.querySelector("input").checked = true;
            }
        });
        box.querySelectorAll('input[name="waste_mode"]').forEach(function (input) {
            input.addEventListener("change", function () {
                showInputs();
                schedule();
            });
        });
    }

    function moneyRow(label, value) {
        return "<div><dt>" + label + "</dt><dd>" + value + "</dd></div>";
    }

    function render(data) {
        if (!data.ok) {
            result.innerHTML = '<div class="p-3 alert alert-warning sf-alert">' +
                (data.errors || ["The price could not be calculated."]).join("<br>") + "</div>";
            return;
        }
        var html = '<div class="sf-panel-head"><h2>' + escapeHtml(data.product.name) + "</h2></div><dl class=\"sf-dl\">";
        html += moneyRow("Actual quantity", escapeHtml(data.actual_quantity + " " + (data.method === "SHEET" ? "m²" : data.quantity_unit)));
        html += moneyRow("Manufacturing waste", escapeHtml(data.standard_waste_percent + "%"));
        html += moneyRow("Billable quantity", escapeHtml(data.billable_quantity + " " + data.quantity_unit));
        html += moneyRow("Costed quantity", escapeHtml(data.costed_quantity + " " + data.quantity_unit));
        html += moneyRow("Raw material cost", escapeHtml(data.raw_cost_display));
        html += moneyRow("Total cost", escapeHtml(data.total_cost_display));
        html += "</dl>";
        if (data.roll) {
            html += '<div class="px-3 pb-2"><p class="sf-kicker">Roll</p><p class="mb-1">Roll width ' + escapeHtml(data.roll.roll_width_mm) +
                " mm · print " + escapeHtml(data.roll.print_width_mm) + " × " + escapeHtml(data.roll.print_length_mm) + " mm</p>";
            html += "<p class=\"mb-1\">Width utilisation " + escapeHtml(data.roll.width_utilisation_percent) +
                "% · unused " + escapeHtml(data.roll.unused_width_mm) + " mm</p>";
            html += "<p class=\"mb-0\">Actual " + escapeHtml(data.roll.actual_area_m2) + " m² · consumed " +
                escapeHtml(data.roll.consumed_area_m2) + " m² · potential waste " + escapeHtml(data.roll.potential_waste_m2) + " m²</p></div>";
        }
        if (data.sheet) {
            html += '<div class="px-3 pb-2"><p class="sf-kicker">Sheet</p><p class="mb-0">Sheets ' + escapeHtml(data.sheet.sheets) +
                " · actual " + escapeHtml(data.sheet.actual_area_m2) + " m² · offcut " + escapeHtml(data.sheet.offcut_area_m2) +
                " m² · utilisation " + escapeHtml(data.sheet.utilisation_percent) + "%</p></div>";
        }
        if (data.manual) {
            html += '<div class="px-3"><div class="alert alert-warning sf-alert">Manual pricing is in use.</div></div>';
        }
        (data.warnings || []).forEach(function (warning) {
            html += '<div class="px-3"><div class="alert alert-warning sf-alert">' + escapeHtml(warning) + "</div></div>";
        });
        html += '<div class="sf-levels">';
        (data.levels || []).forEach(function (level) {
            html += '<article><p class="sf-kicker">' + escapeHtml(level.code) + "</p>";
            html += "<p class=\"sf-level-price\">" + escapeHtml(level.selling_price_display) + "</p>";
            html += "<p class=\"mb-0\">Markup " + escapeHtml(level.markup_percent) + "%<br>GP " +
                escapeHtml(level.gross_profit_display) + "<br>Margin " +
                escapeHtml(level.gross_margin_percent === null ? "—" : level.gross_margin_percent + "%") + "</p></article>";
        });
        html += "</div>";
        result.innerHTML = html;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[char];
        });
    }

    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(price, 200);
    }

    function price() {
        var product = selectedProduct();
        if (!product) {
            result.innerHTML = '<div class="sf-empty"><p>Choose a product.</p></div>';
            return;
        }
        var body = new FormData(form);
        var token = document.querySelector('meta[name="csrf-token"]');
        fetch(form.getAttribute("data-url"), {
            method: "POST",
            headers: {
                "Accept": "application/json",
                "X-Requested-With": "fetch",
                "X-CSRF-Token": token ? token.getAttribute("content") : ""
            },
            body: body
        }).then(function (response) {
            return response.json().then(function (data) {
                render(data);
            });
        }).catch(function () {
            result.innerHTML = '<div class="p-3 alert alert-danger sf-alert">The server could not price that. Check the connection and try again.</div>';
        });
    }

    fillProducts();
    renderWaste();
    showInputs();
    if (categorySelect) {
        categorySelect.addEventListener("change", function () {
            fillProducts();
            renderWaste();
            showInputs();
            schedule();
        });
    }
    productSelect.addEventListener("change", function () {
        renderWaste();
        showInputs();
        schedule();
    });
    form.addEventListener("input", function () {
        showInputs();
        schedule();
    });
})();
