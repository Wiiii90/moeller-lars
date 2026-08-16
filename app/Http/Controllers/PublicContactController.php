<?php

namespace App\Http\Controllers;

use App\Domain\Content\SafeLinkPolicy;
use App\Models\PublicContentSetting;
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
    public function __construct(private readonly SafeLinkPolicy $safeLinkPolicy) {}

    public function show(): View
    {
        $settings = PublicContentSetting::query()->findOrFail(1);
        abort_if($settings->getAttribute('contact_state') === 'hidden', 404);

        return view('pages.contact', ['settings' => $settings]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $settings = PublicContentSetting::query()->findOrFail(1);
        abort_unless($settings->getAttribute('contact_state') === 'enabled', 404);

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

        $recipient = config('contact.recipient');
        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
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
}
