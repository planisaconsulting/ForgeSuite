/**
 * App shell only. Authenticated HTML and API responses are not cached.
 * Field drafts live in IndexedDB, not in this cache.
 */
const SHELL = "signforge-shell-v3";
const FILES = [
  "/assets/css/app.css",
  "/assets/js/app.js",
  "/assets/js/field.js",
  "/offline.html",
  "/assets/images/icon-192.png",
  "/assets/images/icon-512.png",
  "/assets/vendor/bootstrap/css/bootstrap.min.css",
  "/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"
];

self.addEventListener("install", function (event) {
  event.waitUntil(caches.open(SHELL).then(function (cache) {
    return cache.addAll(FILES);
  }));
});

self.addEventListener("activate", function (event) {
  event.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (key) {
      return key !== SHELL;
    }).map(function (key) {
      return caches.delete(key);
    }));
  }).then(function () {
    return self.clients.matchAll();
  }).then(function (clients) {
    clients.forEach(function (client) {
      client.postMessage({type: "update"});
    });
  }));
});

self.addEventListener("fetch", function (event) {
  var url = new URL(event.request.url);
  if (event.request.method !== "GET") {
    return;
  }
  if (FILES.indexOf(url.pathname) !== -1) {
    event.respondWith(caches.match(event.request).then(function (cached) {
      return cached || fetch(event.request);
    }));
    return;
  }
  if (event.request.mode === "navigate" && url.pathname.indexOf("/m") === 0) {
    event.respondWith(fetch(event.request).catch(function () {
      return caches.match("/offline.html");
    }));
  }
});
