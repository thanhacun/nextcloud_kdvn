<?php
/** @var array $_ */
?>
<main class="kv-pass">
<?php if ($_['notFound']): ?>
	<div class="kv-card kv-card--empty">
		<?php foreach ($_['messages'] as $message): ?>
			<p><?php p($message); ?></p>
		<?php endforeach; ?>
	</div>
<?php else: $L = $_['labels']; ?>
	<div class="kv-card" lang="<?php p($_['lang']); ?>" style="--kv-accent: <?php p($_['accent']); ?>; --kv-accent-text: <?php p($_['accentText']); ?>">
		<header class="kv-card__head">
			<span class="kv-card__company">
				<?php if ($_['logoUrl'] !== ''): ?>
					<img class="kv-card__logo" src="<?php p($_['logoUrl']); ?>" alt="<?php p($_['company']); ?>">
				<?php else: ?>
					<?php p($_['company']); ?>
				<?php endif; ?>
			</span>
			<span class="kv-card__title"><?php p($L['pass_title']); ?></span>
		</header>

		<h1 class="kv-card__guest"><?php p($_['guestName']); ?></h1>
		<p class="kv-card__guest-company"><?php p($_['guestCompany']); ?></p>

		<?php if ($_['notice'] !== ''): ?>
			<p class="kv-card__notice"><?php p($_['notice']); ?></p>
		<?php endif; ?>

		<?php if ($_['showQr']): ?>
			<div id="kv-qr" class="kv-card__qr" data-value="<?php p($_['qrValue']); ?>" role="img" aria-label="QR code"></div>
			<p class="kv-card__hint"><?php p($L['pass_show']); ?></p>
			<p class="kv-card__code"><span><?php p($L['pass_code']); ?></span> <strong><?php p($_['code']); ?></strong></p>
		<?php endif; ?>

		<dl class="kv-card__facts">
			<dt><?php p($L['when']); ?></dt><dd><?php p($_['when']); ?></dd>
			<dt><?php p($L['host']); ?></dt><dd><?php p($_['hostName']); ?></dd>
			<?php if ($_['roomName'] !== ''): ?>
				<dt><?php p($L['room']); ?></dt><dd><?php p($_['roomName']); ?></dd>
			<?php endif; ?>
			<?php if ($_['address'] !== ''): ?>
				<dt><?php p($L['address']); ?></dt><dd><?php p($_['address']); ?></dd>
			<?php endif; ?>
		</dl>

		<?php if ($_['showQr']): ?>
			<p class="kv-card__tip"><?php p($L['pass_brightness']); ?></p>
		<?php endif; ?>
	</div>
<?php endif; ?>
</main>
