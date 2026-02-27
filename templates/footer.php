<?php
// templates/footer.php
?>

</main>

<footer class="border-top bg-white mt-4">
  <div class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2">
      <div class="small text-muted">
        <strong class="text-body">BakingProStore</strong>
        <span class="ms-2">E-commerce Demo</span>
      </div>

      <div class="small text-muted d-flex flex-wrap gap-3">
        <span><i class="bi bi-geo-alt me-1"></i>Mahasarakham</span>
        <span><i class="bi bi-telephone me-1"></i>Contact</span>
        <span><i class="bi bi-clock me-1"></i><?= date('Y') ?></span>
      </div>
    </div>

    <div class="small text-muted mt-3">
      <span>© <?= date('Y') ?> BakingProStore. All rights reserved.</span>
    </div>
  </div>
</footer>

<!-- Bootstrap Bundle (JS) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>