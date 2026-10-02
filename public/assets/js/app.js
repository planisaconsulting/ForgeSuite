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
        var orderKey = "sf-nav-order";
        var collapsed = {};
        try {
            collapsed = JSON.parse(localStorage.getItem(collapsedKey) || "{}") || {};
        } catch (error) {
            collapsed = {};
        }

        var navLists = function () {
            return Array.prototype.slice.call(nav.querySelectorAll(".sf-nav"));
        };

        var itemNodes = function () {
            return Array.prototype.slice.call(nav.querySelectorAll(".sf-nav-link[data-nav-id]"));
        };

        var groupIdFor = function (list) {
            var fromAttr = list.getAttribute("data-nav-group");
            if (fromAttr) {
                return fromAttr;
            }
            if (list.id) {
                return list.id;
            }
            var parent = list.closest(".sf-nav-group");
            return parent ? parent.getAttribute("data-nav-group") : null;
        };

        var readOrder = function () {
            try {
                return JSON.parse(localStorage.getItem(orderKey) || "null");
            } catch (error) {
                return null;
            }
        };

        var writeOrder = function () {
            var items = {};
            navLists().forEach(function (list) {
                var id = groupIdFor(list);
                if (!id) {
                    return;
                }
                items[id] = Array.prototype.slice.call(list.querySelectorAll(".sf-nav-link[data-nav-id]")).map(function (el) {
                    return el.getAttribute("data-nav-id");
                });
            });
            try {
                localStorage.setItem(orderKey, JSON.stringify({ items: items }));
            } catch (error) {}
        };

        var applyOrder = function () {
            var order = readOrder();
            if (!order || !order.items) {
                return;
            }
            var byId = {};
            itemNodes().forEach(function (el) {
                byId[el.getAttribute("data-nav-id")] = el;
            });
            Object.keys(order.items).forEach(function (groupId) {
                var list = nav.querySelector('.sf-nav[data-nav-group="' + groupId + '"]');
                if (!list) {
                    list = nav.querySelector("#" + groupId);
                }
                if (!list) {
                    var group = nav.querySelector('.sf-nav-group[data-nav-group="' + groupId + '"]');
                    list = group ? group.querySelector(".sf-nav") : null;
                }
                if (!list || !Array.isArray(order.items[groupId])) {
                    return;
                }
                order.items[groupId].forEach(function (id) {
                    var el = byId[id];
                    if (el) {
                        list.appendChild(el);
                        delete byId[id];
                    }
                });
            });
        };

        applyOrder();

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
                if (nav.classList.contains("is-arranging")) {
                    return;
                }
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

        var arrangeBtn = document.getElementById("sfNavArrange");
        var dragEl = null;

        var clearDropMarks = function () {
            nav.querySelectorAll(".is-drop-target, .is-drop-before, .is-drop-after, .is-dragging").forEach(function (el) {
                el.classList.remove("is-drop-target", "is-drop-before", "is-drop-after", "is-dragging");
            });
        };

        var setArranging = function (on) {
            nav.classList.toggle("is-arranging", on);
            if (arrangeBtn) {
                arrangeBtn.setAttribute("aria-pressed", on ? "true" : "false");
                arrangeBtn.setAttribute("aria-label", on ? "Done arranging menu" : "Arrange menu");
                arrangeBtn.title = on ? "Done" : "Arrange menu";
                var icon = arrangeBtn.querySelector("i");
                if (icon) {
                    icon.className = on ? "fa-solid fa-check" : "fa-solid fa-up-down-left-right";
                }
            }
            itemNodes().forEach(function (el) {
                el.setAttribute("draggable", on ? "true" : "false");
            });
            if (on) {
                nav.querySelectorAll(".sf-nav-group.is-collapsed").forEach(function (group) {
                    group.classList.remove("is-collapsed");
                    var button = group.querySelector("button.sf-nav-label");
                    if (button) {
                        button.setAttribute("aria-expanded", "true");
                    }
                });
            } else {
                clearDropMarks();
                writeOrder();
            }
        };

        if (arrangeBtn) {
            arrangeBtn.addEventListener("click", function () {
                setArranging(!nav.classList.contains("is-arranging"));
            });
        }

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && nav.classList.contains("is-arranging")) {
                setArranging(false);
            }
        });

        nav.addEventListener("dragstart", function (event) {
            if (!nav.classList.contains("is-arranging")) {
                return;
            }
            var item = event.target.closest(".sf-nav-link[data-nav-id]");
            if (!item) {
                return;
            }
            dragEl = item;
            item.classList.add("is-dragging");
            event.dataTransfer.effectAllowed = "move";
            try {
                event.dataTransfer.setData("text/plain", item.getAttribute("data-nav-id") || "");
            } catch (error) {}
        });

        nav.addEventListener("dragend", function () {
            clearDropMarks();
            dragEl = null;
            if (nav.classList.contains("is-arranging")) {
                writeOrder();
            }
        });

        nav.addEventListener("dragover", function (event) {
            if (!nav.classList.contains("is-arranging") || !dragEl) {
                return;
            }
            var list = event.target.closest(".sf-nav");
            var overItem = event.target.closest(".sf-nav-link[data-nav-id]");
            if (!list && !overItem) {
                return;
            }
            event.preventDefault();
            event.dataTransfer.dropEffect = "move";
            clearDropMarks();
            dragEl.classList.add("is-dragging");
            if (overItem && overItem !== dragEl) {
                var rect = overItem.getBoundingClientRect();
                var before = event.clientY < rect.top + rect.height / 2;
                overItem.classList.add(before ? "is-drop-before" : "is-drop-after");
                overItem.parentNode.insertBefore(dragEl, before ? overItem : overItem.nextSibling);
            } else if (list) {
                list.classList.add("is-drop-target");
                list.appendChild(dragEl);
            }
        });

        nav.addEventListener("drop", function (event) {
            if (!nav.classList.contains("is-arranging")) {
                return;
            }
            event.preventDefault();
            clearDropMarks();
            writeOrder();
        });

        nav.addEventListener("click", function (event) {
            if (!nav.classList.contains("is-arranging")) {
                return;
            }
            var link = event.target.closest("a.sf-nav-link");
            if (link) {
                event.preventDefault();
            }
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
            if (nav.classList.contains("is-arranging")) {
                return;
            }
            var instance = bootstrap.Offcanvas.getInstance(nav);
            if (instance) {
                instance.hide();
            }
        });
    });
})();
