<?php
/** @var array $_ */
?>
<div id="kv-display"
	data-data-url="<?php p($_['dataUrl']); ?>"
	<?php if (isset($_['bookUrl'])): ?>data-touch="1" data-claim-url="<?php p($_['claimUrl']); ?>" data-claim-status-url="<?php p($_['claimStatusUrl']); ?>" data-book-url="<?php p($_['bookUrl']); ?>"<?php if (!empty($_['faceUrl'])): ?> data-face-url="<?php p($_['faceUrl']); ?>"<?php endif; ?><?php endif; ?>
	data-company="<?php p($_['company']); ?>"
	data-logo-url="<?php p($_['logoUrl']); ?>"
	style="--kd-accent: <?php p($_['accent']); ?>; --kd-accent-text: <?php p($_['accentText']); ?>">
	<noscript>JavaScript is required.</noscript>
</div>
