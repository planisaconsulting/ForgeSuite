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
