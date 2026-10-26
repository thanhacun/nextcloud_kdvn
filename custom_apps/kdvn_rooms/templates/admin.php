<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div id="kdvn-rooms" class="section">
	<h2><?php p($l->t('Meeting rooms')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Rooms added here can be booked from the Calendar app: open an event, go to Resources, and search for the room.')); ?>
	</p>
	<div id="kdvn-rooms-app" data-email-domain="<?php p($_['emailDomain']); ?>">
		<p><?php p($l->t('Loading…')); ?></p>
	</div>
</div>
