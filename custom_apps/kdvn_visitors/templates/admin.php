<?php
/** @var array $_ */
?>
<?php
$groupOptions = function (string $selected, string $none) use ($_): string {
	$html = '<option value="">' . htmlspecialchars($none) . '</option>';
	foreach ($_['groups'] as $group) {
		$html .= '<option value="' . htmlspecialchars($group['id']) . '"' . ($group['id'] === $selected ? ' selected' : '') . '>' . htmlspecialchars($group['name']) . '</option>';
	}
	return $html;
};
?>
<div id="kv-admin" class="section"
	data-save-url="<?php p($_['saveUrl']); ?>"
	data-kiosk-url="<?php p($_['kioskUrl']); ?>"
	data-test-talk-url="<?php p($_['testTalkUrl']); ?>"
	data-test-reception-url="<?php p($_['testReceptionUrl']); ?>"
	data-logo-api-url="<?php p($_['logoApiUrl']); ?>"
	data-display-link-url="<?php p($_['displayLinkUrl']); ?>">

	<h2>Visitors</h2>

	<form id="kv-settings-form" class="kv-admin-form">
		<h3>Company</h3>
		<label>Company name (shown to guests)
			<input type="text" name="companyName" maxlength="255" value="<?php p($_['companyName']); ?>">
		</label>
		<label>Office address (shown in invitations)
			<textarea name="officeAddress" rows="2" maxlength="500"><?php p($_['officeAddress']); ?></textarea>
		</label>

		<h3>Talk arrival messages</h3>
		<?php if (!$_['talkInstalled']): ?>
			<p class="kv-warn">The Talk app is not enabled, so hosts will get a Nextcloud notification instead.</p>
		<?php endif; ?>
		<p class="settings-hint">
			Use the Nextcloud account that already sends our notifications (a bot, not a real person's account).
			Log in as that user once, then go to Personal settings &gt; Security and create an app password named "Visitors".
			The bot never appears in the "Who are you visiting?" search.
		</p>
		<label>Notification sender account (user ID)
			<input type="text" name="talkUser" autocomplete="off" value="<?php p($_['talkUser']); ?>">
		</label>
		<label>App password of that account
			<input type="password" name="talkPassword" autocomplete="new-password"
				placeholder="<?php p($_['hasTalkPassword'] ? 'Saved. Type a new one to replace it.' : 'Not set'); ?>">
		</label>

		<h3>Reception</h3>
		<p class="settings-hint">
			Each office has its own reception group (set in the Offices table below). Members see the Reception tab with
			the guests of their own office only: waiting walk-ins, manual check-in and check-out, inviting on behalf of
			others. They also get that office's "Reception desk" Talk conversation: arrivals, walk-ins and check-in problems.
			Anyone can still invite a guest to any office. Messages there use the Nextcloud language of the sender account above.
			Administrators see every office.
		</p>
		<label>Default reception group (for offices without their own, and visits of unknown office)
			<select name="receptionGroup"><?php print_unescaped($groupOptions($_['receptionGroup'], 'None (admins only)')); ?></select>
		</label>
		<fieldset class="kv-admin-groups">
			<legend>Hide these groups from the staff search (service accounts, shared mailboxes...)</legend>
			<?php foreach ($_['groups'] as $group): ?>
				<label class="kv-admin-check"><input type="checkbox" class="kv-hidden-group" value="<?php p($group['id']); ?>" <?php if (in_array($group['id'], $_['hiddenGroups'], true)) { p('checked'); } ?>> <?php p($group['name']); ?></label>
			<?php endforeach; ?>
		</fieldset>
		<p class="settings-hint">Members of the ticked groups can't be chosen as a host on the tablet or when inviting on behalf of someone. Click Save settings to apply.</p>
		<label>Office time zone (for "today" and the end-of-day check-out)
			<input type="text" name="officeTimezone" value="<?php p($_['officeTimezone']); ?>" placeholder="Asia/Ho_Chi_Minh">
		</label>
		<label>Check guests out automatically
			<select name="autoCheckoutMinutes">
				<?php foreach ([30 => '30 minutes after the visit ends', 60 => '1 hour after the visit ends', 120 => '2 hours after the visit ends', 240 => '4 hours after the visit ends', 0 => 'Only overnight'] as $m => $label): ?>
					<option value="<?php p((string)$m); ?>" <?php if ($m === $_['autoCheckoutMinutes']) { p('selected'); } ?>><?php p($label); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<p class="settings-hint">Walk-ins and visits without an end time count as 2 hours long. Everyone still checked in is checked out after midnight.
			The tablet's check-out button stays useful for an exact leaving time; you can hide it under Appearance.</p>
		<label>Delete visit records after (days)
			<input type="number" name="retentionDays" min="7" max="3650" value="<?php p((string)$_['retentionDays']); ?>">
		</label>

		<h3>Walk-in form on the tablet</h3>
		<label>Purposes of visit (drop-down list)
			<textarea name="purposes" rows="5" maxlength="2500"><?php p($_['purposesText']); ?></textarea>
		</label>
		<p class="settings-hint">
			One purpose per line. Optionally add the Vietnamese and Japanese wording after a bar:
			<code>Meeting | Họp | 会議</code>. Reception always sees the first (English) wording.
			The tablet adds an "Other" choice with a free text box by itself. The staff invite form uses the same list.
		</p>
		<label>Staff names on the tablet
			<select name="hostNamePrivacy">
				<option value="full" <?php if ($_['hostNamePrivacy'] === 'full') { p('selected'); } ?>>Show full names</option>
				<option value="masked" <?php if ($_['hostNamePrivacy'] === 'masked') { p('selected'); } ?>>Hide most letters (N*** V*** A***), search needs 3 letters</option>
			</select>
		</label>
		<p class="settings-hint">The search ignores accents and capital letters either way: "nguyen van a" finds "Nguyễn Văn A".</p>

		<h3>Calendar</h3>
		<p class="settings-hint">
			When someone invites outside guests to a meeting in Calendar, the guests can get a visitor pass automatically
			(a separate email from Calendar's own invitation, sent within about 5 minutes). If the meeting moves, the pass
			follows; if a guest is removed, declines, or the meeting is cancelled, the pass is cancelled.
			Only meetings in offices ticked under Room displays &gt; Visitor reception count.
			Outside guest = not a room, not a Nextcloud user, and not on the organizer's email domain.
			Recurring meetings don't create passes yet.
		</p>
		<label>Create visitor passes from Calendar
			<select name="autoPassMode"><option value="room" <?php if ($_['autoPassMode'] === 'room') { p('selected'); } ?>>When a meeting room is booked and outside guests are invited</option><option value="all" <?php if ($_['autoPassMode'] === 'all') { p('selected'); } ?>>For every outside guest invited</option><option value="off" <?php if ($_['autoPassMode'] === 'off') { p('selected'); } ?>>Off</option></select>
		</label>

		<h3>Room displays</h3>
		<p class="settings-hint">
			Public screens in front of the meeting rooms: free/busy now and the coming bookings, per building.
			Displays are in English; add <code>&amp;lang=vi</code> or <code>&amp;lang=ja</code> to a link for Vietnamese or Japanese, <code>&amp;theme=light</code> for light colors,
			and <code>&amp;layout=condensed</code>, <code>pages</code> or <code>timeline</code> for another layout (you can pick one when creating the links).
		</p>
		<p class="settings-hint"><strong>Offices:</strong> tick the offices that have a visitor reception. Only their rooms
			create visitor passes from Calendar and appear in the invite form; meetings in other offices are left alone.
			For each office pick its <em>reception group</em> (sees that office's guests and its Talk conversation) and,
			optionally, its <em>area group</em> (the people working there: the invite form preselects their office for them).
			Each office has its own tablet link, so walk-ins reach the right reception.</p>
		<p class="settings-hint">The displays show today and the next 5 working days of each building (add <code>&amp;days=3</code> to a link for fewer).</p>
		<?php if ($_['buildings'] !== []): ?>
		<table class="kv-admin-workdays">
			<thead><tr><th>Office</th><th>Visitor reception</th><th>Reception group</th><th>Area group</th><th>Tablet</th><?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><th><?php p($d); ?></th><?php endforeach; ?></tr></thead>
			<tbody>
			<?php foreach ($_['buildings'] as $b): ?>
				<tr data-building="<?php p((string)$b['id']); ?>">
					<th scope="row"><?php p($b['name']); ?></th>
					<td><input type="checkbox" class="kv-reception" aria-label="<?php p($b['name'] . ' has a visitor reception'); ?>" <?php if ($b['reception']) { p('checked'); } ?>></td>
					<td><select class="kv-office-group" aria-label="<?php p($b['name'] . ' reception group'); ?>"><?php print_unescaped($groupOptions($b['receptionGroup'], 'Default group')); ?></select></td>
					<td><select class="kv-staff-group" aria-label="<?php p($b['name'] . ' area group'); ?>"><?php print_unescaped($groupOptions($b['staffGroup'], 'None')); ?></select></td>
					<td>
						<button type="button" class="kv-new-kiosk" data-building="<?php p((string)$b['id']); ?>" data-name="<?php p($b['name']); ?>"><?php p($b['kioskAt'] > 0 ? 'New link' : 'Create link'); ?></button>
						<?php if ($b['kioskAt'] > 0): ?><span class="settings-hint"><?php p(date('Y-m-d', $b['kioskAt'])); ?></span><?php endif; ?>
					</td>
					<?php for ($d = 1; $d <= 7; $d++): ?>
						<td><input type="checkbox" class="kv-workday" value="<?php p((string)$d); ?>" aria-label="<?php p($b['name'] . ' ' . ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$d]); ?>" <?php if (in_array($d, $b['workdays'], true)) { p('checked'); } ?>></td>
					<?php endfor; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
		<label>Guests shown on the display
			<select name="displayGuestMode"><option value="company" <?php if ($_['displayGuestMode'] === 'company') { p('selected'); } ?>>Company names only</option><option value="names" <?php if ($_['displayGuestMode'] === 'names') { p('selected'); } ?>>Guest names and company</option><option value="count" <?php if ($_['displayGuestMode'] === 'count') { p('selected'); } ?>>Only the number of guests</option></select>
		</label>
		<label>Meeting titles on the display
			<select name="displayTitleMode"><option value="title" <?php if ($_['displayTitleMode'] === 'title') { p('selected'); } ?>>Real title (private events show "Booked")</option><option value="booked" <?php if ($_['displayTitleMode'] === 'booked') { p('selected'); } ?>>Only "Booked" and the organizer</option></select>
		</label>

		<h3>Tablet and visitor pass appearance</h3>
		<p class="settings-hint">
			The logo is your Nextcloud logo (Administration settings &gt; Theming). Leave texts empty to use the built-in ones.
			You can use {company} in the title. The tablet picks up changes within 15 minutes, or reload it.
		</p>
		<?php $a = $_['appearance']; ?>
		<div class="kv-admin-logo">
			<div class="kv-admin-logo__preview">
				<img id="kv-logo-preview" src="<?php p($_['logoUrl']); ?>" alt="Current logo">
			</div>
			<div class="kv-admin-logo__actions">
				<label class="kv-admin-upload">
					<span class="button">Upload logo…</span>
					<input type="file" id="kv-logo-file" accept="image/png,image/jpeg,image/webp" hidden>
				</label>
				<button type="button" id="kv-logo-remove" <?php if (!$_['hasOwnLogo']) { p('hidden'); } ?>>Use Nextcloud logo instead</button>
				<p class="settings-hint">
					<span id="kv-logo-hint"><?php p($_['hasOwnLogo'] ? 'Using the uploaded logo.' : 'Using the Nextcloud Theming logo. Upload one to use a different logo here.'); ?></span>
					PNG, JPEG or WebP, max 2 MB. A PNG with a transparent background looks best.
				</p>
			</div>
		</div>
		<div class="kv-admin-inline">
			<label>Accent color
				<input type="color" name="accent" value="<?php p($a['accent']); ?>">
			</label>
			<label class="kv-admin-check"><input type="checkbox" name="showLogo" <?php if ($a['showLogo']) { p('checked'); } ?>> Show logo</label>
			<label class="kv-admin-check"><input type="checkbox" name="walkIn" <?php if ($a['walkIn']) { p('checked'); } ?>> Walk-in button on tablet</label>
			<label class="kv-admin-check"><input type="checkbox" name="checkOut" <?php if ($a['checkOut']) { p('checked'); } ?>> Check-out button on tablet</label>
		</div>
		<table class="kv-admin-texts">
			<thead><tr><th></th><th>Welcome title</th><th>Message under the clock (e.g. Wi-Fi, floor info)</th></tr></thead>
			<tbody>
			<?php foreach (['vi' => 'Tiếng Việt', 'en' => 'English', 'ja' => '日本語'] as $code => $label): ?>
				<tr>
					<th scope="row"><?php p($label); ?></th>
					<td><input type="text" name="title_<?php p($code); ?>" maxlength="120" value="<?php p($a['texts'][$code]['title']); ?>" lang="<?php p($code); ?>"></td>
					<td><input type="text" name="message_<?php p($code); ?>" maxlength="300" value="<?php p($a['texts'][$code]['message']); ?>" lang="<?php p($code); ?>"></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="kv-admin-actions">
			<button type="submit" class="primary">Save settings</button>
			<button type="button" id="kv-test-talk">Send me a test message</button>
			<button type="button" id="kv-test-reception">Test the Reception desk conversation</button>
		</div>
		<p id="kv-settings-status" class="kv-status" role="status" aria-live="polite"></p>
	</form>

	<h3>Room display links</h3>
	<p class="settings-hint">
		<?php if ($_['displayCreatedAt'] > 0): ?>
			Display links were created on <?php p(date('Y-m-d H:i', $_['displayCreatedAt'])); ?> (server time).
			For security they can't be shown again. Creating new ones stops the old links on all screens.
		<?php else: ?>
			No display links yet. Create them and open the right one on each screen.
		<?php endif; ?>
	</p>
	<button type="button" id="kv-new-display">Create display links</button>
	<div id="kv-display-result" class="kv-kiosk-result" hidden>
		<p><strong>Open the link for each building on its screen and bookmark it. Shown only once.</strong></p>
		<label class="kv-display-layout">Layout for these links
			<select id="kv-display-layout">
				<option value="">Standard: long lists scroll by themselves</option>
				<option value="condensed">Condensed: today in full, later days one line each (small screens, e.g. 15 inch)</option>
				<option value="pages">Pages: lists flip pages with "1 / 3"</option>
				<option value="timeline">Timeline: today across all rooms (big screens)</option>
			</select>
		</label>
		<ul id="kv-display-links" class="kv-display-links"></ul>
	</div>

	<h3>Reception tablet</h3>
	<p class="settings-hint">
		One tablet link per office: use the "Create link" button in the Offices table above. A walk-in on that tablet
		is sent to that office's reception only. A link is shown only once; creating a new one stops the office's old link.
		<?php if ($_['kioskUnassignedAt'] > 0): ?>
			The link made before version 0.6 (<?php p(date('Y-m-d', $_['kioskUnassignedAt'])); ?>) still works but belongs to no office:
			its walk-ins go to the default reception group. Replace it with an office link, then it stops working.
		<?php endif; ?>
	</p>
	<div id="kv-kiosk-result" class="kv-kiosk-result" hidden>
		<p><strong id="kv-kiosk-title">Open this link on the reception tablet and bookmark it.</strong> It is shown only once.</p>
		<input type="text" readonly id="kv-kiosk-link">
		<button type="button" id="kv-copy-kiosk">Copy</button>
	</div>
</div>
