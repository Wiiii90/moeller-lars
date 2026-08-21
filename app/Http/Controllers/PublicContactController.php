<?php

namespace App\Http\Controllers;

use App\Domain\Contact\ContactDeliveryReadiness;
use App\Domain\Content\SitePreviewContext;
use App\Mail\WebsiteContactMessage;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PublicContactController extends Controller
{
    public function __construct(
        private readonly SitePreviewContext $preview,
        private readonly ContactDeliveryReadiness $deliveryReadiness,
    ) {}

    public function show(): View
    {
        abort_unless($this->preview->sectionIsAvailable(SiteSection::TYPE_CONTACT), 404);

        return view('pages.contact', [
            'generalSettings' => PublicContentSetting::general(),
            'contactSettings' => PublicContentSetting::contact(),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $contactSettings = PublicContentSetting::contact();
        abort_unless(
            SiteSection::isPublished(SiteSection::TYPE_CONTACT)
                && $contactSettings->getAttribute('contact_state') === 'enabled',
            404,
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email:rfc', 'max:320'],
            'message' => ['required', 'string', 'max:5000'],
            'company' => ['nullable', 'string', 'max:0'],
        ]);

        $generalSettings = PublicContentSetting::general();
        $recipient = $this->deliveryReadiness->resolveRecipient($generalSettings);
        $senderAddress = $this->deliveryReadiness->senderAddress();
        $delivery = $this->deliveryReadiness->snapshot($generalSettings);

        if ($recipient === null || $senderAddress === null || ! $delivery['mailer_ready']) {
            return back()->withErrors(['contact' => 'Message delivery is currently unavailable.'])->withInput();
        }

        try {
            Mail::to($recipient)->send(new WebsiteContactMessage(
                visitorName: $data['name'],
                visitorEmail: $data['email'],
                messageBody: $data['message'],
                senderAddress: $senderAddress,
                senderName: $this->deliveryReadiness->senderName(),
            ));
        } catch (Throwable $exception) {
            Log::warning('Contact delivery failed.', ['exception' => $exception::class]);

            return back()->withErrors(['contact' => 'Message delivery failed. Please try again later.'])->withInput();
        }

        return back()->with('contact_success', 'Your message was sent.');
    }
}
