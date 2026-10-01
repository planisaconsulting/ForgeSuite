/**
 * App shell cache only. Job data, photos, and forms stay online.
 * A later phase can queue photos. This file does not sync them.
 */
const SHELL = "signforge-shell-v1";
const FILES = [
  "/assets/css/app.css",
  "/assets/js/app.js",
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
  }));
});

self.addEventListener("fetch", function (event) {
  var url = new URL(event.request.url);
  if (event.request.method !== "GET" || FILES.indexOf(url.pathname) === -1) {
    return;
  }
  event.respondWith(caches.match(event.request).then(function (cached) {
    return cached || fetch(event.request);
  }));
});
