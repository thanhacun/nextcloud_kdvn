<?php
/** @var array $_ */
?>
<div id="app-content">
	<div id="kv-confirm"
		data-valid="<?php p($_['valid'] ? '1' : '0'); ?>"
		data-taken="<?php p($_['taken'] ? '1' : '0'); ?>"
		data-room="<?php p($_['room']); ?>"
		data-minutes="<?php p((string)$_['minutes']); ?>"
		data-name="<?php p($_['name']); ?>"
		data-confirm-url="<?php p($_['confirmUrl']); ?>"
		data-lang="<?php p($_['lang']); ?>">
		<noscript>JavaScript is required.</noscript>
	</div>
</div>
