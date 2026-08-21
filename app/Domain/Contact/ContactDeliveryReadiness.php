<?php

namespace App\Domain\Contact;

use App\Models\PublicContentSetting;

final class ContactDeliveryReadiness
{
    /** @return array{recipient_ready: bool, recipient_source: string, sender_ready: bool, mailer_ready: bool, ready: bool} */
    public function snapshot(?PublicContentSetting $generalSettings = null): array
    {
        $generalSettings ??= PublicContentSetting::general();
        $recipient = $this->resolveRecipient($generalSettings);
        $sender = $this->senderAddress();
        $mailerReady = $this->mailerCanDeliver((string) config('mail.default'));

        return [
            'recipient_ready' => $recipient !== null,
            'recipient_source' => $this->recipientSource($generalSettings),
            'sender_ready' => $sender !== null,
            'mailer_ready' => $mailerReady,
            'ready' => $recipient !== null && $sender !== null && $mailerReady,
        ];
    }

    public function resolveRecipient(PublicContentSetting $generalSettings): ?string
    {
        $recipient = $generalSettings->getAttribute('contact_recipient_email');
        if (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false) {
            return $recipient;
        }

        $fallback = config('contact.recipient');

        return is_string($fallback) && filter_var($fallback, FILTER_VALIDATE_EMAIL) !== false ? $fallback : null;
    }

    public function senderAddress(): ?string
    {
        $sender = config('mail.from.address');

        return is_string($sender) && filter_var($sender, FILTER_VALIDATE_EMAIL) !== false ? $sender : null;
    }

    public function senderName(): string
    {
        $senderName = config('mail.from.name');
        if (is_string($senderName) && trim($senderName) !== '') {
            return $senderName;
        }

        return (string) config('app.name', 'Website');
    }

    private function recipientSource(PublicContentSetting $generalSettings): string
    {
        $recipient = $generalSettings->getAttribute('contact_recipient_email');
        if (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false) {
            return 'General';
        }

        $fallback = config('contact.recipient');

        return is_string($fallback) && filter_var($fallback, FILTER_VALIDATE_EMAIL) !== false ? 'Server fallback' : 'Missing';
    }

    /** @param array<string, true> $visited */
    private function mailerCanDeliver(string $mailer, array $visited = []): bool
    {
        if ($mailer === '' || isset($visited[$mailer])) {
            return false;
        }
        $visited[$mailer] = true;

        $configuration = config("mail.mailers.{$mailer}");
        if (! is_array($configuration)) {
            return false;
        }

        $transport = $configuration['transport'] ?? null;
        if (! is_string($transport)) {
            return false;
        }

        if (in_array($transport, ['smtp', 'sendmail', 'mailgun', 'ses', 'ses-v2', 'postmark', 'resend'], true)) {
            return true;
        }

        if (! in_array($transport, ['failover', 'roundrobin'], true)) {
            return false;
        }

        $mailers = $configuration['mailers'] ?? null;
        if (! is_array($mailers) || $mailers === []) {
            return false;
        }

        foreach ($mailers as $childMailer) {
            if (! is_string($childMailer) || ! $this->mailerCanDeliver($childMailer, $visited)) {
                return false;
            }
        }

        return true;
    }
}
