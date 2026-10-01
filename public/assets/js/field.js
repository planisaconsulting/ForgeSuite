/* Field shell. Drafts live in IndexedDB. This file never stores a password. */
(function () {
    var DB_NAME = "signforge-field";
    var STATUSES = ["LOCAL_DRAFT", "QUEUED", "SYNCING", "SYNCED", "CONFLICT", "FAILED"];
    var dismissed = "sf-install-dismissed";
    var deferredPrompt = null;

    function csrf() {
        var tag = document.querySelector('meta[name="csrf-token"]');
        return tag ? tag.getAttribute("content") : "";
    }

    function uuid() {
        if (window.crypto && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            return (c === "x" ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function deviceId() {
        var key = "sf-device-uuid";
        var current = localStorage.getItem(key);
        if (!current) {
            current = uuid();
            localStorage.setItem(key, current);
        }
        return current;
    }

    function openDb() {
        return new Promise(function (resolve, reject) {
            var request = indexedDB.open(DB_NAME, 1);
            request.onupgradeneeded = function () {
                var db = request.result;
                if (!db.objectStoreNames.contains("drafts")) {
                    db.createObjectStore("drafts", {keyPath: "local_uuid"});
                }
                if (!db.objectStoreNames.contains("packs")) {
                    db.createObjectStore("packs", {keyPath: "pack_uuid"});
                }
                if (!db.objectStoreNames.contains("meta")) {
                    db.createObjectStore("meta");
                }
            };
            request.onsuccess = function () { resolve(request.result); };
            request.onerror = function () { reject(request.error); };
        });
    }

    function txDone(tx) {
        return new Promise(function (resolve, reject) {
            tx.oncomplete = function () { resolve(); };
            tx.onerror = function () { reject(tx.error); };
        });
    }

    function putDraft(draft) {
        return openDb().then(function (db) {
            var tx = db.transaction("drafts", "readwrite");
            tx.objectStore("drafts").put(draft);
            return txDone(tx);
        });
    }

    function allDrafts() {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction("drafts", "readonly");
                var request = tx.objectStore("drafts").getAll();
                request.onsuccess = function () { resolve(request.result || []); };
                request.onerror = function () { reject(request.error); };
            });
        });
    }

    function pendingDrafts() {
        return allDrafts().then(function (rows) {
            return rows.filter(function (row) {
                return row.status === "QUEUED" || row.status === "FAILED" || row.status === "LOCAL_DRAFT" || row.status === "SYNCING";
            });
        });
    }

    function entity() {
        var node = document.getElementById("sf-entity");
        if (!node) {
            return null;
        }
        try {
            return JSON.parse(node.textContent || "{}");
        } catch (e) {
            return null;
        }
    }

    function text(node, value) {
        if (node) {
            node.textContent = value;
        }
    }

    function banner() {
        var bar = document.getElementById("sf-offline");
        if (!bar) {
            return;
        }
        bar.hidden = navigator.onLine;
    }

    function showCount() {
        pendingDrafts().then(function (rows) {
            var node = document.getElementById("sf-sync-count");
            if (!node) {
                return;
            }
            if (rows.length === 0) {
                node.hidden = true;
                return;
            }
            node.hidden = false;
            node.textContent = rows.length + (rows.length === 1 ? " item waiting to sync" : " items waiting to sync");
        }).catch(function () {});
    }

    function queueDraft(operationType, payload) {
        var info = entity();
        if (!info) {
            return Promise.reject(new Error("missing entity"));
        }
        var now = new Date().toISOString();
        var draft = {
            local_uuid: uuid(),
            entity_type: info.entity_type,
            server_entity_id: info.entity_id,
            server_version: null,
            operation_type: operationType,
            payload: payload,
            status: navigator.onLine ? "QUEUED" : "LOCAL_DRAFT",
            created_at_local: now,
            updated_at_local: now,
            last_sync_attempt: null,
            error: null,
            operation_uuid: uuid()
        };
        if (operationType === "INSTALL_SIGNATURE" || operationType === "POD_SIGNATURE") {
            text(document.getElementById("sf-sign-state"), "SIGNATURE CAPTURED — WAITING TO SYNC");
        }
        return putDraft(draft).then(function () {
            showCount();
            if (navigator.onLine) {
                return syncNow();
            }
            return null;
        });
    }

    function postJson(url, body) {
        return fetch(url, {
            method: "POST",
            headers: {"Content-Type": "application/json", "X-CSRF-Token": csrf()},
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().then(function (data) {
                data.http = response.status;
                return data;
            });
        });
    }

    function registerDevice() {
        return postJson("/api/v1/devices", {
            device_uuid: deviceId(),
            device_name: "This phone",
            platform: navigator.platform || "",
            app_version: document.body.getAttribute("data-app-version") || "",
            sw_version: "signforge-shell-v3"
        });
    }

    function syncNow() {
        if (!navigator.onLine) {
            text(document.getElementById("sf-sync-result"), "OFFLINE. Changes will sync when connection returns.");
            return Promise.resolve();
        }
        return registerDevice().then(function (device) {
            if (device && device.error_code === "DEVICE_REVOKED") {
                return indexedDB.deleteDatabase(DB_NAME);
            }
            return pendingDrafts().then(function (rows) {
                var queue = rows.slice(0, 5);
                if (queue.length === 0) {
                    text(document.getElementById("sf-sync-result"), "Sync finished. Nothing was waiting.");
                    showCount();
                    return null;
                }
                var photoQueue = queue.filter(function (row) { return String(row.operation_type).indexOf("PHOTO") !== -1; });
                if (photoQueue.length) {
                    text(document.getElementById("sf-sync-result"), "Photo 1 of " + photoQueue.length);
                }
                queue.forEach(function (row) { row.status = "SYNCING"; });
                return Promise.all(queue.map(putDraft)).then(function () {
                    return postJson("/api/v1/sync", {
                        device_uuid: deviceId(),
                        app_version: document.body.getAttribute("data-app-version") || "",
                        sw_version: "signforge-shell-v3",
                        pending_count: rows.length,
                        operations: queue.map(function (row) {
                            return {
                                operation_uuid: row.operation_uuid,
                                local_uuid: row.local_uuid,
                                operation_type: row.operation_type,
                                entity_type: row.entity_type,
                                entity_id: row.server_entity_id,
                                payload: row.payload
                            };
                        })
                    });
                }).then(function (result) {
                    if (!result) {
                        return null;
                    }
                    if (result.error_code === "DEVICE_REVOKED") {
                        indexedDB.deleteDatabase(DB_NAME);
                        text(document.getElementById("sf-sync-result"), result.message || "This device was revoked.");
                        return null;
                    }
                    var byId = {};
                    (result.results || []).forEach(function (item) { byId[item.operation_uuid] = item; });
                    return Promise.all(queue.map(function (row) {
                        var item = byId[row.operation_uuid];
                        row.last_sync_attempt = new Date().toISOString();
                        row.updated_at_local = row.last_sync_attempt;
                        if (!item) {
                            row.status = "FAILED";
                            row.error = "That update could not be saved.";
                        } else if (item.status === "SYNCED") {
                            row.status = "SYNCED";
                            row.error = null;
                        } else if (item.status === "CONFLICT") {
                            row.status = "CONFLICT";
                            row.error = item.message;
                        } else {
                            row.status = "FAILED";
                            row.error = item.message;
                        }
                        return putDraft(row);
                    })).then(function () {
                        var failed = queue.filter(function (row) { return row.status === "FAILED" || row.status === "CONFLICT"; });
                        text(document.getElementById("sf-sync-result"), failed.length ? (failed[0].error || "Sync needs attention.") : "Sync finished. The office has these updates.");
                        showCount();
                        if (rows.length > 5) {
                            return syncNow();
                        }
                        return null;
                    });
                });
            });
        }).catch(function () {
            text(document.getElementById("sf-sync-result"), "Sync did not finish. Try again.");
        });
    }

    function compress(file) {
        return new Promise(function (resolve) {
            var reader = new FileReader();
            reader.onload = function () {
                var image = new Image();
                image.onload = function () {
                    var canvas = document.createElement("canvas");
                    var scale = Math.min(1, 1600 / Math.max(image.width, image.height));
                    canvas.width = Math.max(1, Math.round(image.width * scale));
                    canvas.height = Math.max(1, Math.round(image.height * scale));
                    canvas.getContext("2d").drawImage(image, 0, 0, canvas.width, canvas.height);
                    var quality = Number(document.body.getAttribute("data-image-quality") || "70") / 100;
                    if (!(quality > 0 && quality <= 1)) {
                        quality = 0.7;
                    }
                    resolve(canvas.toDataURL("image/jpeg", quality));
                };
                image.onerror = function () { resolve(reader.result); };
                image.src = reader.result;
            };
            reader.readAsDataURL(file);
        });
    }

    function storageWarning() {
        var node = document.getElementById("sf-local-storage");
        if (!node || !navigator.storage || !navigator.storage.estimate) {
            return;
        }
        navigator.storage.estimate().then(function (estimate) {
            var used = estimate.usage || 0;
            node.textContent = "Offline photos and drafts on this device: " + Math.round(used / 1048576) + " MB.";
        }).catch(function () {});
    }

    function bindMeasure() {
        var form = document.getElementById("sf-measure-form");
        if (!form) {
            return;
        }
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            var sanity = Number(document.body.getAttribute("data-sanity-mm") || "20000");
            var unit = document.getElementById("sf-unit").value;
            var factor = unit === "m" ? 1000 : (unit === "cm" ? 10 : 1);
            var warnings = [];
            ["Width", "Height"].forEach(function (label, index) {
                var raw = index === 0 ? document.getElementById("sf-w").value : document.getElementById("sf-h").value;
                if (raw === "") { return; }
                var mm = Number(raw) * factor;
                if (mm === 0) { warnings.push(label + " is 0. The value was kept."); }
                else if (mm > sanity) { warnings.push(label + " is unusually large. The value was kept."); }
            });
            text(document.getElementById("sf-measure-warn"), warnings.join(" "));
            queueDraft("SURVEY_MEASUREMENT", {
                reference: document.getElementById("sf-ref").value,
                width: document.getElementById("sf-w").value,
                height: document.getElementById("sf-h").value,
                unit: document.getElementById("sf-unit").value,
                quantity: document.getElementById("sf-qty").value,
                note: document.getElementById("sf-note").value
            }).then(function () {
                form.reset();
            }).catch(function () {});
        });
    }

    function photoType() {
        var info = entity();
        if (!info) { return "SURVEY_PHOTO"; }
        if (info.mode === "install") { return "INSTALL_PHOTO"; }
        if (info.mode === "delivery") { return "DELIVERY_PHOTO"; }
        return "SURVEY_PHOTO";
    }

    function bindPhoto() {
        var button = document.getElementById("sf-photo-add");
        var input = document.getElementById("sf-photo-file");
        var canvas = document.getElementById("sf-annotate");
        var tools = document.getElementById("sf-annotate-tools");
        if (!button || !input) {
            return;
        }
        var original = "";
        var annotated = false;
        var tool = "freehand";
        var drawing = false;
        var start = null;
        if (tools) {
            tools.addEventListener("click", function (event) {
                var next = event.target.getAttribute("data-tool");
                if (next) { tool = next; }
            });
        }
        function paintBase(dataUrl) {
            if (!canvas) { return; }
            var image = new Image();
            image.onload = function () {
                canvas.hidden = false;
                if (tools) { tools.hidden = false; }
                canvas.getContext("2d").drawImage(image, 0, 0, canvas.width, canvas.height);
                annotated = false;
            };
            image.src = dataUrl;
        }
        if (canvas) {
            var ctx = canvas.getContext("2d");
            function point(event) {
                var rect = canvas.getBoundingClientRect();
                var src = event.touches ? event.touches[0] : event;
                return {
                    x: (src.clientX - rect.left) * (canvas.width / rect.width),
                    y: (src.clientY - rect.top) * (canvas.height / rect.height)
                };
            }
            canvas.addEventListener("pointerdown", function (event) {
                if (!original) { return; }
                drawing = true;
                start = point(event);
                if (tool === "text") {
                    ctx.fillStyle = "#ff7a1a";
                    ctx.font = "24px sans-serif";
                    ctx.fillText((document.getElementById("sf-annotate-label") || {}).value || "Note", start.x, start.y);
                    annotated = true;
                    drawing = false;
                } else {
                    ctx.strokeStyle = "#ff7a1a";
                    ctx.lineWidth = 3;
                    ctx.beginPath();
                    ctx.moveTo(start.x, start.y);
                }
                event.preventDefault();
            });
            canvas.addEventListener("pointermove", function (event) {
                if (!drawing || tool !== "freehand") { return; }
                var p = point(event);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                annotated = true;
            });
            window.addEventListener("pointerup", function (event) {
                if (!drawing || !start) { drawing = false; return; }
                var p = point(event);
                ctx.strokeStyle = "#ff7a1a";
                ctx.lineWidth = 3;
                if (tool === "arrow") {
                    ctx.beginPath();
                    ctx.moveTo(start.x, start.y);
                    ctx.lineTo(p.x, p.y);
                    ctx.stroke();
                } else if (tool === "circle") {
                    ctx.beginPath();
                    ctx.strokeRect(Math.min(start.x, p.x), Math.min(start.y, p.y), Math.abs(p.x - start.x), Math.abs(p.y - start.y));
                }
                if (tool === "arrow" || tool === "circle") { annotated = true; }
                drawing = false;
            });
        }
        input.addEventListener("change", function () {
            if (!input.files || !input.files[0]) { return; }
            compress(input.files[0]).then(function (dataUrl) {
                original = dataUrl;
                paintBase(dataUrl);
            });
        });
        button.addEventListener("click", function () {
            if (!original && (!input.files || !input.files[0])) { return; }
            var send = function (dataUrl) {
                var payload = {
                    image_base64: dataUrl,
                    category: (document.getElementById("sf-photo-cat") || {}).value || "OTHER",
                    caption: (document.getElementById("sf-photo-caption") || {}).value || "",
                    captured_at: new Date().toISOString()
                };
                if (annotated && canvas) {
                    payload.annotation_base64 = canvas.toDataURL("image/png");
                }
                return captureGps(false).then(function (gps) {
                    payload.latitude = gps.latitude || "";
                    payload.longitude = gps.longitude || "";
                    return queueDraft(photoType(), payload);
                });
            };
            var ready = original ? Promise.resolve(original) : compress(input.files[0]);
            ready.then(send).then(function () {
                original = "";
                annotated = false;
                input.value = "";
            }).catch(function () {});
        });
    }

    function captureGps(ask) {
        return new Promise(function (resolve) {
            if (!ask || !navigator.geolocation) {
                resolve({});
                return;
            }
            navigator.geolocation.getCurrentPosition(function (pos) {
                resolve({latitude: String(pos.coords.latitude), longitude: String(pos.coords.longitude)});
            }, function () { resolve({}); }, {enableHighAccuracy: false, timeout: 8000, maximumAge: 120000});
        });
    }

    function fieldValue(id) {
        var node = document.getElementById(id);
        return node ? node.value : "";
    }

    function actionPayload(button) {
        var op = button.getAttribute("data-sf-op");
        var payload = {
            occurred_at: new Date().toISOString(),
            timezone: (Intl.DateTimeFormat().resolvedOptions().timeZone || "")
        };
        if (op === "INSTALL_TRAVEL" || op === "MILEAGE") {
            payload.start_odometer = fieldValue("sf-odo-start");
            payload.end_odometer = fieldValue("sf-odo-end");
            payload.manual_km = fieldValue("sf-manual-km");
            payload.vehicle_resource_id = fieldValue("sf-vehicle");
        }
        if (op === "INSTALL_START") {
            var box = document.getElementById("sf-safety");
            payload.safety_ack = box && box.checked ? "1" : "0";
        }
        if (op === "INSTALL_CHECKLIST") {
            payload.checklist_item_id = button.getAttribute("data-item");
            payload.answer = button.getAttribute("data-answer");
            payload.note = fieldValue("sf-check-note");
        }
        if (op === "INSTALL_SNAG") {
            payload.description = fieldValue("sf-snag");
            payload.priority = fieldValue("sf-snag-priority");
        }
        if (op === "INSTALL_NOTE") {
            payload.text = fieldValue("sf-install-note");
            payload.base_text = button.getAttribute("data-base") || "";
        }
        if (op === "DELIVERY_EXCEPTION") {
            payload.exception = fieldValue("sf-exception");
            payload.note = fieldValue("sf-exception-note");
        }
        return payload;
    }

    function bindSignature() {
        var canvas = document.getElementById("sf-sign");
        var button = document.getElementById("sf-sign-save");
        if (!canvas || !button) {
            return;
        }
        var ctx = canvas.getContext("2d");
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = "#111";
        ctx.lineWidth = 2;
        var drawing = false;
        function point(event) {
            var rect = canvas.getBoundingClientRect();
            var src = event.touches ? event.touches[0] : event;
            return {
                x: (src.clientX - rect.left) * (canvas.width / rect.width),
                y: (src.clientY - rect.top) * (canvas.height / rect.height)
            };
        }
        function start(event) {
            drawing = true;
            var p = point(event);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            event.preventDefault();
        }
        canvas.addEventListener("pointerdown", start);
        canvas.addEventListener("pointermove", function (event) {
            if (!drawing) { return; }
            var p = point(event);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
        });
        window.addEventListener("pointerup", function () { drawing = false; });
        button.addEventListener("click", function () {
            var info = entity();
            captureGps(button.getAttribute("data-gps") === "1").then(function (gps) {
                return queueDraft(info && info.mode === "delivery" ? "POD_SIGNATURE" : "INSTALL_SIGNATURE", {
                    signer_name: (document.getElementById("sf-signer") || {}).value || "",
                    signature_png: canvas.toDataURL("image/png"),
                    client_signed_at: new Date().toISOString(),
                    latitude: gps.latitude || "",
                    longitude: gps.longitude || ""
                });
            }).catch(function () {});
        });
    }

    function bindPack() {
        var button = document.getElementById("sf-download-pack");
        if (!button) {
            return;
        }
        button.addEventListener("click", function () {
            storageWarning();
            var limit = Number(document.body.getAttribute("data-storage-mb") || "300") * 1048576;
            var estimate = navigator.storage && navigator.storage.estimate ? navigator.storage.estimate() : Promise.resolve({usage: 0});
            estimate.then(function (info) {
                var used = info.usage || 0;
                if (limit > 0 && used >= limit) {
                    text(document.getElementById("sf-pack-state"), "Offline storage is already " + Math.round(used / 1048576) + " MB. Existing unsynced work is kept. This field pack was not downloaded.");
                    return null;
                }
                return registerDevice().then(function () {
                    return postJson("/api/v1/field-packs", {
                        device_uuid: deviceId(),
                        pack_type: button.getAttribute("data-pack-type"),
                        entity_id: Number(button.getAttribute("data-entity-id")),
                        used_bytes: used
                    });
                });
            }).then(function (result) {
                if (!result) {
                    return;
                }
                if (!result.ok) {
                    text(document.getElementById("sf-pack-state"), result.message || "The field pack was not downloaded.");
                    return;
                }
                return openDb().then(function (db) {
                    var tx = db.transaction("packs", "readwrite");
                    tx.objectStore("packs").put(result.pack);
                    return txDone(tx);
                }).then(function () {
                    text(document.getElementById("sf-pack-state"), "Available offline.");
                });
            }).catch(function () {
                text(document.getElementById("sf-pack-state"), "The field pack was not downloaded.");
            });
        });
    }

    function bindScan() {
        var button = document.getElementById("sf-scan-start");
        if (!button) {
            return;
        }
        button.addEventListener("click", function () {
            var box = document.getElementById("sf-scan-camera");
            var note = document.getElementById("sf-scan-result");
            if (!navigator.mediaDevices || !window.BarcodeDetector) {
                text(note, "Camera scanning is not available. Type the code or use a USB scanner.");
                return;
            }
            navigator.mediaDevices.getUserMedia({video: {facingMode: "environment"}}).then(function (stream) {
                var video = document.createElement("video");
                video.setAttribute("playsinline", "");
                video.autoplay = true;
                video.srcObject = stream;
                video.style.width = "100%";
                box.innerHTML = "";
                box.appendChild(video);
                var stop = document.getElementById("sf-scan-stop");
                if (stop) { stop.hidden = false; }
                var detector = new BarcodeDetector({formats: ["qr_code", "code_128"]});
                var last = "";
                var timer = setInterval(function () {
                    detector.detect(video).then(function (codes) {
                        if (!codes.length) { return; }
                        var value = codes[0].rawValue || "";
                        if (value === "" || value === last) { return; }
                        last = value;
                        var field = document.getElementById("code") || document.getElementById("sf-delivery-code");
                        if (field) { field.value = value; }
                        text(note, value + " read. Confirm it. The camera stays open for the next item.");
                        if (navigator.vibrate) { navigator.vibrate(40); }
                        setTimeout(function () { if (last === value) { last = ""; } }, 1500);
                    }).catch(function () {});
                }, 400);
                if (stop) {
                    stop.onclick = function () {
                        clearInterval(timer);
                        stream.getTracks().forEach(function (track) { track.stop(); });
                        stop.hidden = true;
                        text(note, "Camera stopped.");
                    };
                }
            }).catch(function () {
                text(note, "Camera permission was not granted. Type the code or choose a photo.");
            });
        });
    }

    function bindSync() {
        var button = document.getElementById("sf-sync-now");
        if (button) {
            button.addEventListener("click", function () { syncNow(); });
        }
    }

    function bindInstall() {
        var button = document.getElementById("sf-install");
        window.addEventListener("beforeinstallprompt", function (event) {
            if (localStorage.getItem(dismissed) === "1") {
                return;
            }
            event.preventDefault();
            deferredPrompt = event;
            if (button) { button.hidden = false; }
        });
        if (!button) { return; }
        button.addEventListener("click", function () {
            if (!deferredPrompt) {
                text(button, "Use the browser menu to install Sign-Forge.");
                return;
            }
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function (choice) {
                if (choice.outcome === "dismissed") {
                    localStorage.setItem(dismissed, "1");
                }
                deferredPrompt = null;
            });
        });
    }

    function bindUpdate() {
        if (!navigator.serviceWorker) { return; }
        navigator.serviceWorker.addEventListener("message", function (event) {
            if (!event.data || event.data.type !== "update") { return; }
            pendingDrafts().then(function (rows) {
                var node = document.getElementById("sf-update");
                if (!node) { return; }
                node.hidden = false;
                text(node, rows.length ? "Update available. Sync or finish the open field item before refreshing." : "Update available.");
            }).catch(function () {});
        });
        var go = document.getElementById("sf-update-go");
        if (go) {
            go.addEventListener("click", function () {
                pendingDrafts().then(function (rows) {
                    if (rows.length) {
                        text(document.getElementById("sf-update"), "Sync the waiting items before refreshing.");
                        return;
                    }
                    window.location.reload();
                }).catch(function () { window.location.reload(); });
            });
        }
    }

    function noteButton() {
        var button = document.getElementById("sf-note-add");
        if (!button) { return; }
        button.addEventListener("click", function () {
            var body = document.getElementById("sf-note-body");
            if (!body || body.value.trim() === "") { return; }
            var kind = fieldValue("sf-note-kind");
            var note = body.value;
            if (kind && kind !== "NOTE") { note = kind + ": " + note; }
            queueDraft("SURVEY_NOTE", {body: note}).then(function () { body.value = ""; }).catch(function () {});
        });
    }

    function bindActions() {
        document.querySelectorAll("[data-sf-op]").forEach(function (button) {
            button.addEventListener("click", function () {
                var op = button.getAttribute("data-sf-op");
                if (op === "INSTALL_SNAG" && fieldValue("sf-snag").trim() === "") { return; }
                if (op === "DELIVERY_EXCEPTION" && fieldValue("sf-exception-note").trim() === "") { return; }
                captureGps(button.getAttribute("data-gps") === "1").then(function (gps) {
                    var payload = actionPayload(button);
                    if (gps.latitude) {
                        payload.latitude = gps.latitude;
                        payload.longitude = gps.longitude;
                    }
                    return queueDraft(op, payload);
                }).catch(function () {});
            });
        });
    }

    function metaPut(key, value) {
        return openDb().then(function (db) {
            var tx = db.transaction("meta", "readwrite");
            tx.objectStore("meta").put(value, key);
            return txDone(tx);
        });
    }

    function metaGet(key) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var request = db.transaction("meta", "readonly").objectStore("meta").get(key);
                request.onsuccess = function () { resolve(request.result); };
                request.onerror = function () { reject(request.error); };
            });
        });
    }

    function bindTime() {
        var start = document.getElementById("sf-time-start");
        var stop = document.getElementById("sf-time-stop");
        document.querySelectorAll("[data-time='stop']").forEach(function (pause) {
            pause.addEventListener("click", function () { if (stop) { stop.click(); } });
        });
        if (start) {
            start.addEventListener("click", function () {
                metaPut("time-start", new Date().toISOString()).then(function () {
                    text(document.getElementById("sf-sync-result"), "Time started on this phone.");
                }).catch(function () {});
            });
        }
        if (stop) {
            stop.addEventListener("click", function () {
                metaGet("time-start").then(function (started) {
                    if (!started) { return; }
                    return queueDraft("TIME_ENTRY", {
                        started_at_local: started,
                        ended_at_local: new Date().toISOString(),
                        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || ""
                    }).then(function () { return metaPut("time-start", ""); });
                }).catch(function () {});
            });
        }
    }

    function currentPack() {
        var info = entity();
        if (!info) { return Promise.resolve(null); }
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var request = db.transaction("packs", "readonly").objectStore("packs").getAll();
                request.onsuccess = function () {
                    var rows = request.result || [];
                    var match = null;
                    rows.forEach(function (row) {
                        if (Number(row.entity_id) === Number(info.entity_id)) { match = row; }
                    });
                    resolve(match);
                };
                request.onerror = function () { reject(request.error); };
            });
        });
    }

    function bindScanConfirm() {
        var button = document.getElementById("sf-scan-confirm");
        if (!button) { return; }
        button.addEventListener("click", function () {
            var code = fieldValue("sf-delivery-code").trim();
            if (code === "") { return; }
            currentPack().then(function (pack) {
                return queueDraft("SCAN_CONFIRM", {code: code, pack_id: pack ? pack.id : 0});
            }).then(function () {
                var field = document.getElementById("sf-delivery-code");
                if (field) { field.value = ""; }
            }).catch(function () {});
        });
    }

    function bindOrder() {
        var button = document.getElementById("sf-photo-order");
        if (!button) { return; }
        button.addEventListener("click", function () {
            var ids = [];
            document.querySelectorAll("#sf-photo-list [data-photo-id]").forEach(function (row) {
                ids.push(Number(row.getAttribute("data-photo-id")));
            });
            if (!ids.length) { return; }
            queueDraft("PHOTO_REORDER", {photo_ids: ids}).catch(function () {});
        });
    }

    function bindReport() {
        var form = document.getElementById("sf-report");
        if (!form) { return; }
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            postJson("/api/v1/mobile/report", {
                error_code: fieldValue("sf-report-code") || "UNKNOWN",
                app_version: document.body.getAttribute("data-app-version") || "",
                sw_version: "signforge-shell-v3",
                platform: navigator.platform || "",
                module: "field",
                online: navigator.onLine ? "1" : "0"
            }).then(function (result) {
                text(document.getElementById("sf-report-result"), result.message || "Report stored.");
            }).catch(function () {
                text(document.getElementById("sf-report-result"), "The report was not sent.");
            });
        });
    }

    function bindReady() {
        var button = document.getElementById("sf-field-ready");
        if (!button) { return; }
        button.addEventListener("click", function () {
            pendingDrafts().then(function (rows) {
                return openDb().then(function (db) {
                    return new Promise(function (resolve) {
                        var request = db.transaction("packs", "readonly").objectStore("packs").getAll();
                        request.onsuccess = function () { resolve(request.result || []); };
                        request.onerror = function () { resolve([]); };
                    });
                }).then(function (packs) {
                    var notes = [];
                    if (!packs.length) { notes.push("No field pack is on this phone."); }
                    if (rows.length) { notes.push(rows.length + " item(s) still waiting to sync."); }
                    text(document.getElementById("sf-sync-result"), notes.length ? notes.join(" ") : "Field pack is on this phone and the sync queue is empty.");
                });
            }).catch(function () {});
        });
    }

    function guardOfflineSession() {
        if (navigator.onLine) { return; }
        currentPack().then(function (pack) {
            if (!pack || !pack.offline_until) { return; }
            var until = Date.parse(String(pack.offline_until).replace(" ", "T"));
            if (!until || until > Date.now()) { return; }
            document.querySelectorAll("[data-sf-work]").forEach(function (node) { node.hidden = true; });
            text(document.getElementById("sf-pack-state"), "This offline session has expired. Connect and sign in again. Unsynced drafts stay on this phone.");
        }).catch(function () {});
    }

    document.addEventListener("DOMContentLoaded", function () {
        banner();
        showCount();
        storageWarning();
        bindMeasure();
        bindPhoto();
        bindSignature();
        bindPack();
        bindScan();
        bindSync();
        bindInstall();
        bindUpdate();
        noteButton();
        bindActions();
        bindTime();
        bindScanConfirm();
        bindOrder();
        bindReport();
        bindReady();
        guardOfflineSession();
        window.addEventListener("online", function () {
            banner();
            syncNow();
        });
        window.addEventListener("offline", banner);
    });

    window.SignForgeField = {statuses: STATUSES, syncNow: syncNow, deviceId: deviceId};
})();
