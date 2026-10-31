<?php

declare(strict_types=1);

namespace OCA\KdvnVisitors\Service;

/**
 * Guest-facing and host-facing texts in Vietnamese, English and Japanese.
 * (Nextcloud's own translation system only translates into the *viewer's*
 * language; guests aren't Nextcloud users, so we keep our own small table.)
 */
class Texts {
	public const LANGUAGES = ['vi', 'en', 'ja'];

	private const T = [
		'en' => [
			'mail_subject' => 'Your visitor pass for {company} – {date}',
			'mail_heading' => 'You are invited to {company}',
			'mail_intro' => '{host} has registered your visit. When you arrive, hold the QR code on your visitor pass up to the reception tablet.',
			'when' => 'When',
			'host' => 'Your host',
			'room' => 'Meeting room',
			'address' => 'Address',
			'open_pass' => 'Open visitor pass',
			'code_line' => 'If the QR code does not scan, type this code on the tablet: {code}',
			'pass_title' => 'Visitor pass',
			'pass_show' => 'Hold this QR code up to the camera on the reception tablet.',
			'pass_code' => 'Code',
			'pass_brightness' => 'Tip: turn up your screen brightness so it scans faster.',
			'pass_checked_in' => 'You checked in at {time}.',
			'pass_cancelled' => 'This visit has been cancelled. Please contact {host}.',
			'pass_not_found' => 'This visitor pass was not found. The link may have been replaced by a newer one.',
			'talk_arrived' => '🛎️ {guest} ({company}) has arrived at reception.',
			'talk_room' => 'Meeting room: {room}',
			'talk_time' => 'Scheduled: {time}',
			'pass_checked_out' => 'You checked out at {time}. Thank you for visiting.',
			'talk_walkin_host' => '🚶 {guest} ({company}) is at reception without an appointment and is asking for you.',
			'talk_walkin_action' => 'Let them in or decline: {link}',
			'talk_phone' => 'Phone: {phone}',
			'talk_purpose' => 'Purpose: {purpose}',
			'talk_decided_in' => '✅ {who} let {guest} ({company}) in.',
			'talk_decided_out' => '⛔ {who} declined {guest} ({company}).',
			'talk_checked_in_by' => '{who} checked {guest} in at reception.',
			'talk_invited' => '🎫 You invited {guest} ({company}).',
			'talk_when' => 'When: {when}',
			'talk_email' => 'Email: {email}',
			'talk_code' => 'Code (type it at the reception tablet if the QR code does not scan): {code}',
			'talk_pass_link' => 'Visitor pass link: {link}',
			'talk_host_line' => 'Host: {host}',
			'rc_walkin' => '🚶 Walk-in: {guest} ({company}) wants to see {host}.',
			'rc_walkin_nohost' => '🚶 Walk-in: {guest} ({company}) is not sure who to see. Please help them.',
			'rc_problem' => '⚠️ {guest} ({company}) could not check in: {reason}. Host: {host}.',
			'rc_arrived' => '🛎️ {guest} ({company}) has arrived to see {host}.',
			'rc_host_unreachable' => '⚠️ {guest} ({company}) has arrived for {host}, but the Talk message to {host} failed. Please call them.',
			'reason_cancelled' => 'the visit was cancelled',
			'reason_too_early' => 'too early, the visit starts at {time}',
			'reason_expired' => 'the pass has expired',
			'reason_declined' => 'the visit was declined',
			'nobody' => 'not chosen',
		],
		'vi' => [
			'mail_subject' => 'Thẻ khách của bạn tại {company} – {date}',
			'mail_heading' => 'Thư mời đến {company}',
			'mail_intro' => '{host} đã đăng ký lịch hẹn cho bạn. Khi đến nơi, vui lòng đưa mã QR trên thẻ khách vào camera của máy tính bảng tại quầy lễ tân.',
			'when' => 'Thời gian',
			'host' => 'Người tiếp đón',
			'room' => 'Phòng họp',
			'address' => 'Địa chỉ',
			'open_pass' => 'Mở thẻ khách',
			'code_line' => 'Nếu không quét được mã QR, hãy nhập mã này trên máy tính bảng: {code}',
			'pass_title' => 'Thẻ khách',
			'pass_show' => 'Đưa mã QR này vào camera của máy tính bảng tại quầy lễ tân.',
			'pass_code' => 'Mã nhập tay',
			'pass_brightness' => 'Mẹo: tăng độ sáng màn hình để quét nhanh hơn.',
			'pass_checked_in' => 'Bạn đã check-in lúc {time}.',
			'pass_cancelled' => 'Lịch hẹn này đã bị hủy. Vui lòng liên hệ {host}.',
			'pass_not_found' => 'Không tìm thấy thẻ khách này. Liên kết có thể đã được thay bằng liên kết mới.',
			'talk_arrived' => '🛎️ {guest} ({company}) đã đến quầy lễ tân.',
			'talk_room' => 'Phòng họp: {room}',
			'talk_time' => 'Giờ hẹn: {time}',
			'pass_checked_out' => 'Bạn đã check-out lúc {time}. Cảm ơn bạn đã ghé thăm.',
			'talk_walkin_host' => '🚶 {guest} ({company}) đang ở quầy lễ tân, không có lịch hẹn trước và muốn gặp bạn.',
			'talk_walkin_action' => 'Cho vào hoặc từ chối tại: {link}',
			'talk_phone' => 'Điện thoại: {phone}',
			'talk_purpose' => 'Mục đích: {purpose}',
			'talk_decided_in' => '✅ {who} đã cho {guest} ({company}) vào.',
			'talk_decided_out' => '⛔ {who} đã từ chối {guest} ({company}).',
			'talk_checked_in_by' => '{who} đã check-in cho {guest} tại quầy lễ tân.',
			'talk_invited' => '🎫 Bạn đã mời {guest} ({company}).',
			'talk_when' => 'Thời gian: {when}',
			'talk_email' => 'Email: {email}',
			'talk_code' => 'Mã (nhập tại máy tính bảng lễ tân nếu không quét được QR): {code}',
			'talk_pass_link' => 'Link thẻ khách: {link}',
			'talk_host_line' => 'Người tiếp đón: {host}',
			'rc_walkin' => '🚶 Khách không hẹn trước: {guest} ({company}) muốn gặp {host}.',
			'rc_walkin_nohost' => '🚶 Khách không hẹn trước: {guest} ({company}) không rõ cần gặp ai. Vui lòng hỗ trợ.',
			'rc_problem' => '⚠️ {guest} ({company}) không thể check-in: {reason}. Người tiếp đón: {host}.',
			'rc_arrived' => '🛎️ {guest} ({company}) đã đến gặp {host}.',
			'rc_host_unreachable' => '⚠️ {guest} ({company}) đã đến gặp {host}, nhưng không gửi được tin nhắn Talk cho {host}. Vui lòng gọi điện báo.',
			'reason_cancelled' => 'lịch hẹn đã bị hủy',
			'reason_too_early' => 'đến quá sớm, lịch hẹn lúc {time}',
			'reason_expired' => 'thẻ khách đã hết hạn',
			'reason_declined' => 'lượt thăm đã bị từ chối',
			'nobody' => 'chưa chọn',
		],
		'ja' => [
			'mail_subject' => '来訪者パスのご案内：{company}（{date}）',
			'mail_heading' => '{company}へのご来訪のご案内',
			'mail_intro' => '弊社の{host}がご来訪を登録いたしました。ご到着の際は、来訪者パスのQRコードを受付のタブレットのカメラにかざしてください。',
			'when' => '日時',
			'host' => '担当者',
			'room' => '会議室',
			'address' => '住所',
			'open_pass' => '来訪者パスを開く',
			'code_line' => 'QRコードが読み取れない場合は、タブレットで次のコードを入力してください：{code}',
			'pass_title' => '来訪者パス',
			'pass_show' => '受付のタブレットのカメラにこのQRコードをかざしてください。',
			'pass_code' => '入力用コード',
			'pass_brightness' => '画面を明るくすると読み取りやすくなります。',
			'pass_checked_in' => '{time}に受付済みです。',
			'pass_cancelled' => 'このご来訪はキャンセルされました。{host}までお問い合わせください。',
			'pass_not_found' => 'この来訪者パスは見つかりません。新しいリンクに置き換えられた可能性があります。',
			'talk_arrived' => '🛎️ {company}の{guest}様が受付に到着しました。',
			'talk_room' => '会議室：{room}',
			'talk_time' => '予定：{time}',
			'pass_checked_out' => '{time}に退館済みです。ご来訪ありがとうございました。',
			'talk_walkin_host' => '🚶 {company}の{guest}様が予約なしで受付に来られ、あなたとの面会を希望されています。',
			'talk_walkin_action' => '入館の許可・お断りはこちら：{link}',
			'talk_phone' => '電話：{phone}',
			'talk_purpose' => 'ご用件：{purpose}',
			'talk_decided_in' => '✅ {who}が{company}の{guest}様の入館を許可しました。',
			'talk_decided_out' => '⛔ {who}が{company}の{guest}様の入館をお断りしました。',
			'talk_checked_in_by' => '{who}が受付で{guest}様をチェックインしました。',
			'talk_invited' => '🎫 {company}の{guest}様を招待しました。',
			'talk_when' => '日時：{when}',
			'talk_email' => 'メール：{email}',
			'talk_code' => 'コード（QRコードが読み取れない場合は受付タブレットで入力）：{code}',
			'talk_pass_link' => '来訪者パスのリンク：{link}',
			'talk_host_line' => '担当者：{host}',
			'rc_walkin' => '🚶 予約なしの来訪：{company}の{guest}様が{host}との面会を希望されています。',
			'rc_walkin_nohost' => '🚶 予約なしの来訪：{company}の{guest}様の訪問先が不明です。ご対応ください。',
			'rc_problem' => '⚠️ {company}の{guest}様が受付できませんでした：{reason}。担当者：{host}',
			'rc_arrived' => '🛎️ {company}の{guest}様が{host}を訪問されました。',
			'rc_host_unreachable' => '⚠️ {company}の{guest}様が{host}を訪問されましたが、{host}へのTalk通知に失敗しました。お電話でお知らせください。',
			'reason_cancelled' => '来訪がキャンセルされています',
			'reason_too_early' => '予定時刻（{time}）より早すぎます',
			'reason_expired' => 'パスの有効期限が切れています',
			'reason_declined' => '入館がお断りされています',
			'nobody' => '未選択',
		],
	];

	public static function get(string $lang, string $key, array $params = []): string {
		$text = self::T[self::normalize($lang)][$key] ?? self::T['en'][$key] ?? $key;
		foreach ($params as $name => $value) {
			$text = str_replace('{' . $name . '}', (string)$value, $text);
		}
		return $text;
	}

	/** "vi_VN" -> "vi", "ja" -> "ja", anything else -> "en" */
	public static function normalize(?string $lang): string {
		$short = strtolower(substr((string)$lang, 0, 2));
		return in_array($short, self::LANGUAGES, true) ? $short : 'en';
	}
}
