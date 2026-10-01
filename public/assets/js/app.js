// Shell behaviour only. Pricing math will live in calculator.js
// so the browser total can never become the number that is saved.
(function () {
    var nav = document.getElementById("appNav");
    if (!nav || typeof bootstrap === "undefined") {
        return;
    }

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
