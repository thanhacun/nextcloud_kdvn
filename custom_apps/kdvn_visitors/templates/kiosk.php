<?php
/** @var array $_ */
?>
<div id="kv-kiosk"
	data-company="<?php p($_['company']); ?>"
	data-retention-days="<?php p((string)$_['retentionDays']); ?>"
	data-check-in-url="<?php p($_['checkInUrl']); ?>"
	data-check-out-url="<?php p($_['checkOutUrl']); ?>"
	data-hosts-url="<?php p($_['hostsUrl']); ?>"
	data-walk-in-url="<?php p($_['walkInUrl']); ?>"
	data-purposes="<?php p(json_encode($_['purposes'])); ?>"
	data-mask-hosts="<?php p($_['maskHosts'] ? '1' : '0'); ?>"
	data-appearance="<?php p(json_encode($_['appearance'])); ?>">
	<noscript>JavaScript is required.</noscript>
</div>
