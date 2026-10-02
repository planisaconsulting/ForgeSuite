// Shell behaviour only. Pricing math will live in calculator.js
// so the browser total can never become the number that is saved.
(function () {
    if ("serviceWorker" in navigator) {
        navigator.serviceWorker.register("/sw.js").catch(function () {});
    }

    var showPassword = document.getElementById("show_password");
    var password = document.getElementById("password");
    if (showPassword && password) {
        showPassword.addEventListener("change", function () {
            password.type = showPassword.checked ? "text" : "password";
        });
    }

    var nav = document.getElementById("appNav");
    if (nav) {
        var collapsedKey = "sf-nav-collapsed";
        var collapsed = {};
        try {
            collapsed = JSON.parse(localStorage.getItem(collapsedKey) || "{}") || {};
        } catch (error) {
            collapsed = {};
        }

        nav.querySelectorAll(".sf-nav-group").forEach(function (group) {
            var id = group.getAttribute("data-nav-group");
            var button = group.querySelector("button.sf-nav-label");
            if (!id || !button) {
                return;
            }

            var hasActive = !!group.querySelector(".sf-nav-link.active, .sf-nav[data-has-active]");
            var setCollapsed = function (isCollapsed) {
                group.classList.toggle("is-collapsed", isCollapsed);
                button.setAttribute("aria-expanded", isCollapsed ? "false" : "true");
            };

            setCollapsed(!hasActive && !!collapsed[id]);

            button.addEventListener("click", function () {
                var isCollapsed = !group.classList.contains("is-collapsed");
                setCollapsed(isCollapsed);
                try {
                    var state = JSON.parse(localStorage.getItem(collapsedKey) || "{}") || {};
                    if (isCollapsed) {
                        state[id] = true;
                    } else {
                        delete state[id];
                    }
                    localStorage.setItem(collapsedKey, JSON.stringify(state));
                } catch (error) {}
            });
        });
    }

    if (!nav || typeof bootstrap === "undefined") {
        return;
    }

    document.addEventListener("keydown", function (event) {
        if ((event.ctrlKey || event.metaKey) && (event.key === "k" || event.key === "K")) {
            var search = document.getElementById("global-search");
            if (!search) {
                return;
            }
            event.preventDefault();
            search.focus();
            search.select();
        }
    });

    nav.querySelectorAll(".sf-nav-link").forEach(function (link) {
        link.addEventListener("click", function () {
            if (!window.matchMedia("(max-width: 991.98px)").matches) {
                return;
            }
            var instance = bootstrap.Offcanvas.getInstance(nav);
            if (instance) {
                instance.hide();
            }
        });
    });
})();
