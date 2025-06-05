</main> <br><footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted"><?php echo date("Y"); ?> Powered by DeeneLife Team</span>
    </div>
</footer>

<script src="<?php echo isset($base_url) ? $base_url : ''; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo isset($base_url) ? $base_url : ''; ?>assets/js/theme-switcher.js"></script> <?php // ?>
<script src="<?php echo isset($base_url) ? $base_url : ''; ?>assets/js/script.js"></script>
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('<?php echo $base_url; ?>service-worker.js') // base_url ব্যবহার করুন
      .then(registration => {
        console.log('ServiceWorker registration successful with scope: ', registration.scope);
      })
      .catch(err => {
        console.log('ServiceWorker registration failed: ', err);
      });
  });
}
</script>
</body>
</html>