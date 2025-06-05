const CACHE_NAME = 'dquiz-cache-v1';
// যে ফাইলগুলো অফলাইনে দেখানোর জন্য ক্যাশ করতে চান, সেগুলোর পাথ এখানে যোগ করুন।
// আপনার সাইটের গুরুত্বপূর্ণ CSS, JS, ইমেজ এবং প্রধান পেজগুলো যোগ করতে পারেন।
const urlsToCache = [
  '/', // অথবা '/index.php' যদি এটি আপনার মূল পেজ হয়
  '/index.php',
  '/quizzes.php',
  '/categories.php',
  '/study_materials.php',
  '/assets/css/bootstrap.min.css',
  '/assets/css/style.css',
  '/assets/js/bootstrap.bundle.min.js',
  '/assets/js/script.js',
  '/assets/js/theme-switcher.js',
  '/assets/images/logo.png',
  '/assets/images/ogq.jpg',
  // '/path/to/other/important/assets...' // প্রয়োজন অনুযায়ী আরও ফাইল যোগ করুন
  // ম্যানিফেস্ট ফাইলে উল্লেখিত আইকনগুলোও এখানে যোগ করতে পারেন
  '/assets/images/icons/favicon-96x96.png',
  '/assets/images/icons/apple-touch-icon.png',
  '/assets/images/icons/android-chrome-192x192.png',
  '/assets/images/icons/android-chrome-512x512.png'
];

// সার্ভিস ওয়ার্কার ইনস্টল করার সময়
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
      .catch(err => {
        console.error('Failed to cache urls:', err);
      })
  );
  self.skipWaiting(); // নতুন সার্ভিস ওয়ার্কারকে দ্রুত সক্রিয় করুন
});

// নেটওয়ার্ক রিকোয়েস্টের জন্য
self.addEventListener('fetch', event => {
  // শুধু GET রিকোয়েস্টগুলো ক্যাশ করুন
  if (event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // ক্যাশে পেলে সেখান থেকে দিন
        if (response) {
          return response;
        }
        // ক্যাশে না পেলে নেটওয়ার্ক থেকে আনুন
        return fetch(event.request).then(
          networkResponse => {
            // যদি নেটওয়ার্ক থেকে সফলভাবে আসে
            if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
              return networkResponse;
            }
            // প্রতিক্রিয়াটি ক্লোন করুন। একটি স্ট্রিম একবারই ব্যবহার করা যায়।
            // একটি কপি ব্রাউজার ব্যবহার করবে, অন্যটি ক্যাশে রাখা হবে।
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });
            return networkResponse;
          }
        ).catch(error => {
          // নেটওয়ার্ক ফেইল করলে, এখানে একটি অফলাইন পেজ দেখানো যেতে পারে
          console.error('Fetching failed:', error);
          // return caches.match('/offline.html'); // একটি ডিফল্ট অফলাইন পেজ তৈরি করতে পারেন
        });
      })
  );
});

// পুরনো ক্যাশ পরিষ্কার করার জন্য
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME]; // বর্তমান ক্যাশের নাম
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            // যদি ক্যাশের নাম হোয়াইটলিস্টে না থাকে, তাহলে ডিলিট করুন
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  return self.clients.claim(); // নতুন সার্ভিস ওয়ার্কারকে পেজ নিয়ন্ত্রণ করতে বলুন
});