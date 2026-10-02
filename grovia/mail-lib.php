<?php
require_once __DIR__ . '/mail-config.php';
require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Sends one email via SMTP and ALWAYS logs the attempt to email_log with
// the real outcome — Sent or Failed, never a status that doesn't match
// what actually happened. Returns ['success' => bool, 'status' => string,
// 'error' => ?string].
function gh_send_email(PDO $db, int $userId, string $toEmail, string $toName, string $subject, string $bodyHtml, string $emailType, string $sentBy = 'Admin'): array {
    $status = 'Failed';
    $errorReason = null;

    if (GH_SMTP_USERNAME === '' || GH_SMTP_PASSWORD === '') {
        $errorReason = 'SMTP not configured yet — fill in mail-config.php to send real email.';
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = GH_SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = GH_SMTP_USERNAME;
            $mail->Password = GH_SMTP_PASSWORD;
            $mail->SMTPSecure = GH_SMTP_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = GH_SMTP_PORT;
            $mail->setFrom(GH_SMTP_FROM_EMAIL !== '' ? GH_SMTP_FROM_EMAIL : GH_SMTP_USERNAME, GH_SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $bodyHtml;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)));
            $mail->send();
            $status = 'Sent';
        } catch (PHPMailerException $e) {
            $errorReason = $mail->ErrorInfo ?: $e->getMessage();
        } catch (Throwable $e) {
            $errorReason = $e->getMessage();
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO email_log (user_id, subject, message, email_type, sent_by, status) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $subject, $bodyHtml, $emailType, $sentBy, $status]);

    return ['success' => $status === 'Sent', 'status' => $status, 'error' => $errorReason];
}

// A 6-digit numeric code, not a link — short enough to type by hand, the
// standard shape for a "check your email" one-time code.
function gh_generate_verification_code(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Generates + stores a fresh 15-minute code (reuses the verification_token /
// verification_token_expiry columns — same fields the old link-based flow
// used, just holding a short code instead of a long token now) and emails
// it. $userRow needs user_id/full_name/email. The user types the code back
// in on verify.php; nothing here is a clickable link. Returns the same
// shape as gh_send_email().
function gh_send_verification_email(PDO $db, array $userRow): array {
    $code = gh_generate_verification_code();
    $expiryMinutes = 15;
    $expiry = date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"));

    $stmt = $db->prepare('UPDATE users SET verification_token = ?, verification_token_expiry = ? WHERE user_id = ?');
    $stmt->execute([$code, $expiry, $userRow['user_id']]);

    $name = htmlspecialchars($userRow['full_name'] ?: $userRow['email'], ENT_QUOTES);
    $codeDigits = htmlspecialchars($code, ENT_QUOTES);
    $year = date('Y');

    // Table-based layout with inline styles throughout — the only markup
    // that renders consistently across Gmail/Outlook/Apple Mail, unlike the
    // flexbox/grid CSS the rest of this app uses.
    $body = <<<HTML
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6faf7;padding:40px 16px;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
          <tr>
            <td align="center">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:20px;overflow:hidden;border:1px solid #e3ede6;">
                <tr>
                  <td style="background:linear-gradient(135deg,#22C55E,#16A34A);background-color:#16A34A;padding:28px 32px;">
                    <span style="font-size:20px;font-weight:700;color:#ffffff;letter-spacing:-0.02em;">Grov<span style="color:#DCFCE7;">ia</span></span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:36px 32px 8px;">
                    <p style="margin:0 0 4px;font-size:13px;font-weight:600;color:#16A34A;text-transform:uppercase;letter-spacing:0.06em;">Verify your email</p>
                    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#0e1912;">Hi {$name}, confirm it's you</h1>
                    <p style="margin:0 0 24px;font-size:14.5px;line-height:1.6;color:#41564a;">Enter this code on the Grovia verification page to activate your account. It expires in {$expiryMinutes} minutes.</p>
                  </td>
                </tr>
                <tr>
                  <td style="padding:0 32px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                      <tr>
                        <td align="center" style="background:#eafbef;border:1.5px dashed #22C55E;border-radius:14px;padding:22px 16px;">
                          <span style="font-family:'Courier New',monospace;font-size:36px;font-weight:700;letter-spacing:10px;color:#0f7a3e;">{$codeDigits}</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding:24px 32px 8px;">
                    <p style="margin:0;font-size:13px;line-height:1.6;color:#7c8f83;">Didn't request this? You can safely ignore this email — your account stays untouched and no one can verify it without this code.</p>
                  </td>
                </tr>
                <tr>
                  <td style="padding:28px 32px 32px;border-top:1px solid #e3ede6;margin-top:8px;">
                    <p style="margin:20px 0 0;font-size:12px;color:#9aa79f;">Grovia Smart Greenhouse &copy; {$year}. This is an automated message, please don't reply directly to it.</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
        HTML;

    return gh_send_email(
        $db,
        (int)$userRow['user_id'],
        $userRow['email'],
        $userRow['full_name'] ?? '',
        'Your Grovia verification code',
        $body,
        'Verification',
        'System'
    );
}
