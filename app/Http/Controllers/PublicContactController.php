<?php

namespace App\Http\Controllers;

use App\Domain\Contact\ContactDeliveryReadiness;
use App\Domain\Content\SiteNodeType;
use App\Domain\Publication\CommittedRead;
use App\Mail\WebsiteContactMessage;
use App\Models\ContactMessage;
use App\Models\CustomPageSetting;
use App\Models\PublicContentSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PublicContactController extends Controller
{
    private const RECEIVED_MESSAGE = 'Your message was received.';

    public function __construct(
        private readonly ContactDeliveryReadiness $deliveryReadiness,
        private readonly CommittedRead $committedRead,
    ) {}

    public function submit(Request $request): RedirectResponse
    {
        $deliveryContext = $this->committedRead->run(function (): array {
            if (! $this->publishedContactFormExists()) {
                return ['form_exists' => false];
            }

            $generalSettings = PublicContentSetting::general();
            $delivery = $this->deliveryReadiness->snapshot($generalSettings);

            return [
                'form_exists' => true,
                'recipient' => $this->deliveryReadiness->resolveRecipient($generalSettings),
                'sender_address' => $this->deliveryReadiness->senderAddress(),
                'sender_name' => $this->deliveryReadiness->senderName(),
                'mailer_ready' => $delivery['mailer_ready'],
            ];
        });

        abort_unless($deliveryContext['form_exists'], 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email:rfc', 'max:320'],
            'message' => ['required', 'string', 'max:5000'],
            'company' => ['nullable', 'string', 'max:0'],
        ]);

        $contactMessage = ContactMessage::query()->create([
            'sender_name' => $data['name'],
            'sender_email' => $data['email'],
            'message' => $data['message'],
            'mail_delivery_status' => ContactMessage::DELIVERY_PENDING,
        ]);

        $recipient = $deliveryContext['recipient'] ?? null;
        $senderAddress = $deliveryContext['sender_address'] ?? null;
        $senderName = $deliveryContext['sender_name'] ?? null;
        $mailerReady = (bool) ($deliveryContext['mailer_ready'] ?? false);

        if (! is_string($recipient) || ! is_string($senderAddress) || ! $mailerReady) {
            $contactMessage->markMailUnavailable();

            return back()->with('contact_success', self::RECEIVED_MESSAGE);
        }

        try {
            Mail::to($recipient)->send(new WebsiteContactMessage(
                visitorName: $data['name'],
                visitorEmail: $data['email'],
                messageBody: $data['message'],
                senderAddress: $senderAddress,
                senderName: is_string($senderName) ? $senderName : (string) config('app.name', 'Website'),
            ));
            $contactMessage->markMailDelivered();
        } catch (Throwable $exception) {
            $contactMessage->markMailFailed();
            Log::warning('Contact delivery failed.', [
                'exception_type' => $exception::class,
                'contact_message_id' => $contactMessage->getKey(),
            ]);
        }

        return back()->with('contact_success', self::RECEIVED_MESSAGE);
    }

    private function publishedContactFormExists(): bool
    {
        return CustomPageSetting::query()
            ->whereHas('siteSection', static fn ($query) => $query
                ->where('type', SiteNodeType::CustomPage->value)
                ->where('state', 'published'))
            ->get(['blocks'])
            ->contains(function (CustomPageSetting $settings): bool {
                foreach ($settings->components() as $block) {
                    if (($block['type'] ?? null) === 'contact'
                        && ($block['show_form'] ?? true) === true
                        && ($block['form_state'] ?? 'enabled') === 'enabled') {
                        return true;
                    }
                }

                return false;
            });
    }
}
