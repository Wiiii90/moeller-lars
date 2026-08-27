<?php

namespace App\Http\Controllers;

use App\Domain\Contact\ContactDeliveryReadiness;
use App\Domain\Content\SiteNodeType;
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

    public function __construct(private readonly ContactDeliveryReadiness $deliveryReadiness) {}

    public function submit(Request $request): RedirectResponse
    {
        abort_unless($this->publishedContactFormExists(), 404);

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

        $generalSettings = PublicContentSetting::general();
        $recipient = $this->deliveryReadiness->resolveRecipient($generalSettings);
        $senderAddress = $this->deliveryReadiness->senderAddress();
        $delivery = $this->deliveryReadiness->snapshot($generalSettings);

        if ($recipient === null || $senderAddress === null || ! $delivery['mailer_ready']) {
            $contactMessage->markMailUnavailable();

            return back()->with('contact_success', self::RECEIVED_MESSAGE);
        }

        try {
            Mail::to($recipient)->send(new WebsiteContactMessage(
                visitorName: $data['name'],
                visitorEmail: $data['email'],
                messageBody: $data['message'],
                senderAddress: $senderAddress,
                senderName: $this->deliveryReadiness->senderName(),
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
