<?php
/** "Support us" band with the two HelloAsso calls to action. */
$settings = Settings::all();
?>
<section class="support" aria-labelledby="support-title">
  <div class="container">
    <h2 id="support-title">Soutenir <?= e($settings['site_name']) ?></h2>
    <div class="support-grid">
      <div class="support-item">
        <h3>Faire un don</h3>
        <p>Un don, même modeste, aide l'association à mener ses actions.</p>
        <a class="btn btn-primary btn-large" href="<?= e(Settings::donUrl()) ?>">Faire un don</a>
      </div>
      <div class="support-item">
        <h3>Devenir membre</h3>
        <p>Adhérez pour rejoindre l'association et participer à sa vie.</p>
        <a class="btn btn-secondary btn-large" href="<?= e(Settings::adhesionUrl()) ?>">Adhérer</a>
      </div>
    </div>
    <p class="support-note">Le paiement se fait sur HelloAsso, la plateforme de paiement des associations. Ce site ne traite aucun paiement.</p>
  </div>
</section>
