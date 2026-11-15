<?php
/** @var array $_ */
$labels = ['temperature' => 'Temperature (°C)', 'humidity' => 'Humidity (% RH)', 'co2' => 'CO2 (ppm)'];
$fields = ['okMin' => 'Good from', 'okMax' => 'Good up to', 'warnMin' => 'Warning from', 'warnMax' => 'Warning up to'];
?>
<div id="ks-admin" class="section"
	data-save-url="<?php p($_['saveUrl']); ?>"
	data-display-link-url="<?php p($_['displayLinkUrl']); ?>"
	data-sensors="<?php p(json_encode($_['sensors'])); ?>"
	data-buildings="<?php p(json_encode($_['buildings'])); ?>"
	data-default-thresholds="<?php p(json_encode($_['thresholds'])); ?>">
	<h2>Office sensors</h2>
	<form id="ks-form" style="display:flex;flex-direction:column;gap:10px;max-width:900px">
		<label>Data source
			<select name="provider">
				<option value="off" <?php if ($_['provider'] === 'off') { p('selected'); } ?>>Off (tiles show NA)</option>
				<option value="mock" <?php if ($_['provider'] === 'mock') { p('selected'); } ?>>Mock values (for testing only)</option>
			</select>
		</label>
		<p class="settings-hint">The supplier BACnet API will be added as a third source when its details are known.</p>

		<h3>Sensors</h3>
		<p class="settings-hint">
			One row per sensor, for example "Office area" and "Server room". The office is a building of Calendar Resource Management
			(managed in Meeting Rooms). "Point name" is how the sensor is called at the data source (used by the supplier source later).
			"Room display" shows the sensor in the header strip of that office's room display. Thresholds left empty use the defaults below.
			<?php if (empty($_['sensors'])): ?>No sensor is configured yet: one default sensor ("Office") is shown until you save a list.<?php endif; ?>
		</p>
		<div id="ks-sensors"></div>
		<div><button type="button" id="ks-add">Add a sensor</button></div>

		<h3>Default thresholds</h3>
		<p class="settings-hint">A value inside "Good" shows Comfortable / Normal / Good, inside "Warning" shows Warning, anything else Bad. A sensor can override these (for example a server room).</p>
		<?php foreach ($labels as $metric => $label): ?>
			<fieldset style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;border:0;padding:0">
				<legend><strong><?php p($label); ?></strong></legend>
				<?php foreach ($fields as $field => $fieldLabel): ?>
					<label><?php p($fieldLabel); ?>
						<input type="number" step="any" style="width:110px" name="<?php p($metric . '.' . $field); ?>" value="<?php p($_['thresholds'][$metric][$field]); ?>">
					</label>
				<?php endforeach; ?>
			</fieldset>
		<?php endforeach; ?>
		<div><button type="submit" class="primary">Save</button></div>
		<p id="ks-status" role="status" aria-live="polite"></p>
	</form>

	<h3>Display link</h3>
	<p class="settings-hint">
		<?php if ($_['displayCreatedAt'] > 0): ?>
			The link was created on <?php p(date('Y-m-d H:i', $_['displayCreatedAt'])); ?> (server time). It can't be shown again; a new one stops the old link.
		<?php else: ?>
			No link yet.
		<?php endif; ?>
		Add <code>&amp;building=ID</code> to show one office only (the ID is shown after each office name in the Sensors table),
		<code>&amp;lang=vi</code> or <code>&amp;lang=ja</code> for another language, <code>&amp;theme=light</code> for light colors.
	</p>
	<button type="button" id="ks-new-link">Create display link</button>
	<div id="ks-link-result" hidden style="margin-top:12px;gap:8px;max-width:760px">
		<p><strong>Open this link on the screen and bookmark it. Shown only once.</strong></p>
		<input type="text" readonly id="ks-link" style="flex:1;min-width:280px">
		<button type="button" id="ks-copy">Copy</button>
	</div>
</div>
