<?php
/** @var array $_ */
$listed = array_column($_['listed'], 'label', 'id');
?>
<div id="kd-admin" class="section" data-save-url="<?php p($_['saveUrl']); ?>">
	<h2>Directory</h2>
	<p class="settings-hint">Tick the groups people may browse. Leave permission-only groups (for example folder access groups) unticked.
		The label replaces the group name in the directory (optional). Disabled accounts are never listed.</p>
	<table class="kd-table">
		<thead><tr><th>Listed</th><th>Group</th><th>Members</th><th>Label shown to users</th><th>Denied</th></tr></thead>
		<tbody>
		<?php foreach ($_['groups'] as $g): ?>
			<tr data-group="<?php p($g['id']); ?>">
				<td><input type="checkbox" class="kd-listed" <?php if (isset($listed[$g['id']])) { p('checked'); } ?>></td>
				<td><?php p($g['name']); ?><?php if ($g['name'] !== $g['id']) { ?> <small>(<?php p($g['id']); ?>)</small><?php } ?></td>
				<td><?php p((string)$g['count']); ?></td>
				<td><input type="text" class="kd-label" maxlength="80" value="<?php p($listed[$g['id']] ?? ''); ?>"></td>
				<td><input type="checkbox" class="kd-denied" <?php if (in_array($g['id'], $_['denied'], true)) { p('checked'); } ?>></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="settings-hint"><strong>Denied</strong>: members of a ticked group cannot open the directory and are not listed in it
		(temporary or guest accounts). Administrators are never denied.</p>
	<button type="button" id="kd-save" class="primary">Save</button> <span id="kd-status"></span>
</div>
