<?php

namespace App\Http\Controllers;

use App\Domain\Content\SafeLinkPolicy;
use App\Domain\Content\SitePreviewContext;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicContactController extends Controller
{
    public function __construct(
        private readonly SafeLinkPolicy $safeLinkPolicy,
        private readonly SitePreviewContext $preview,
    ) {}

    public function show(): View
    {
        abort_unless($this->preview->sectionIsAvailable(SiteSection::TYPE_CONTACT), 404);

        $settings = PublicContentSetting::query()->sole();
        if (! $this->preview->active()) {
            abort_if($settings->getAttribute('contact_state') === 'hidden', 404);
        }

        return view('pages.contact', ['settings' => $settings]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $settings = PublicContentSetting::query()->sole();
        abort_unless(
            SiteSection::isPublished(SiteSection::TYPE_CONTACT)
                && $settings->getAttribute('contact_state') === 'enabled',
            404,
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email:rfc', 'max:320'],
            'website' => ['nullable', 'string', 'max:2048'],
            'comment' => ['required', 'string', 'max:5000'],
            'company' => ['nullable', 'string', 'max:0'],
        ]);

        $website = $data['website'] ?? null;
        if ($website !== null) {
            $scheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true) || ! $this->safeLinkPolicy->isAllowed($website)) {
                throw ValidationException::withMessages([
                    'website' => 'The website must be a valid HTTP or HTTPS URL.',
                ]);
            }
        }

        $recipient = $settings->getAttribute('contact_recipient_email');
        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $recipient = config('contact.recipient');
        }
        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return back()->withErrors(['contact' => 'Message delivery is currently unavailable.'])->withInput();
        }

        $mailer = config('mail.default');
        if (! is_string($mailer) || ! $this->mailerCanDeliver($mailer)) {
            return back()->withErrors(['contact' => 'Message delivery is currently unavailable.'])->withInput();
        }

        $body = "Name: {$data['name']}\nEmail: {$data['email']}\n";
        if ($website !== null) {
            $body .= "Website: {$website}\n";
        }
        $body .= "\n{$data['comment']}";

        try {
            Mail::raw($body, function (Message $message) use ($recipient, $data): void {
                $message
                    ->to($recipient)
                    ->replyTo($data['email'], $data['name'])
                    ->subject('Website contact · Lars Möller');
            });
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
