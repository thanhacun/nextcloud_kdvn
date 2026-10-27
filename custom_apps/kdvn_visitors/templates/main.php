<?php
/** @var array $_ */
?>
<div id="app-content">
	<div id="kv-app"
		data-api-url="<?php p($_['apiUrl']); ?>"
		data-reception-url="<?php p($_['receptionUrl']); ?>"
		data-users-url="<?php p($_['usersUrl']); ?>"
		data-purposes="<?php p(json_encode($_['purposes'])); ?>"
		data-default-language="<?php p($_['defaultLanguage']); ?>">
		<p class="kv-loading">Loading…</p>
	</div>
</div>
