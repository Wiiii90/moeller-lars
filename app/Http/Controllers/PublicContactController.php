<?php

namespace App\Http\Controllers;

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
    public function __construct(private readonly SitePreviewContext $preview) {}

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
        $recipient = $generalSettings->getAttribute('contact_recipient_email');
        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $recipient = config('contact.recipient');
        }
        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return back()->withErrors(['contact' => 'Message delivery is currently unavailable.'])->withInput();
        }

        $senderAddress = config('mail.from.address');
        if (! is_string($senderAddress) || filter_var($senderAddress, FILTER_VALIDATE_EMAIL) === false) {
            return back()->withErrors(['contact' => 'Message delivery is currently unavailable.'])->withInput();
        }
        $senderName = config('mail.from.name');
        if (! is_string($senderName) || trim($senderName) === '') {
            $senderName = (string) config('app.name', 'Website');
        }

        $mailer = config('mail.default');
        if (! is_string($mailer) || ! $this->mailerCanDeliver($mailer)) {
            return back()->withErrors(['contact' => 'Message delivery is currently unavailable.'])->withInput();
        }

        try {
            Mail::to($recipient)->send(new WebsiteContactMessage(
                visitorName: $data['name'],
                visitorEmail: $data['email'],
                messageBody: $data['message'],
                senderAddress: $senderAddress,
                senderName: $senderName,
            ));
        } catch (Throwable $exception) {
            Log::warning('Contact delivery failed.', ['exception' => $exception::class]);

            return back()->withErrors(['contact' => 'Message delivery failed. Please try again later.'])->withInput();
        }

        return back()->with('contact_success', 'Your message was sent.');
    }

    /** @param array<string, true> $visited */
    private function mailerCanDeliver(string $mailer, array $visited = []): bool
    {
        if (isset($visited[$mailer])) {
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
