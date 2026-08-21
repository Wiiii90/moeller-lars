@props(['settings', 'showStatus' => true])

@php
    $showEmail = (bool) $settings->show_public_email && $settings->public_email !== null;
    $socialLinks = \App\Domain\Content\SocialLinks::visible($settings->social_links);
@endphp

<section class="contact-section" aria-labelledby="contact-heading">
    <h2 id="contact-heading" class="category-heading">Contact</h2>

    @if ($showEmail || $socialLinks !== [])
        <div class="contact-details" aria-label="Contact details">
            @if ($showEmail)
                <div class="contact-details__row">
                    <span class="contact-details__label">E-Mail</span>
                    <a
                        href="mailto:{{ $settings->public_email }}"
                        data-matomo-event-category="Contact"
                        data-matomo-event-action="email_click"
                        data-matomo-event-name="Public email"
                    >{{ $settings->public_email }}</a>
                </div>
            @endif
            @foreach ($socialLinks as $socialLink)
                <div class="contact-details__row">
                    <span class="contact-details__label">{{ \App\Domain\Content\SocialLinks::label($socialLink['platform']) }}</span>
                    <a
                        href="{{ $socialLink['url'] }}"
                        rel="noopener noreferrer"
                        data-matomo-event-category="Outbound"
                        data-matomo-event-action="social_click"
                        data-matomo-event-name="{{ $socialLink['platform'] }}"
                    >{{ \App\Domain\Content\SocialLinks::displayValue($socialLink['url']) }}</a>
                </div>
            @endforeach
        </div>
    @endif

    @if ($settings->contact_state === 'under_construction')
        @if ($showStatus)
            <p class="contact-status contact-status--quiet" role="status">
                {{ $settings->contact_status_text }}
            </p>
        @endif
    @elseif ($settings->contact_state === 'enabled')
        @if (session('contact_success'))
            <p
                class="contact-message"
                role="status"
                data-matomo-event-category="Contact"
                data-matomo-event-on-load="contact_submit_success"
                data-matomo-event-name="Contact form"
            >{{ session('contact_success') }}</p>
        @endif

        @if ($errors->has('contact'))
            <p class="contact-message" role="alert">{{ $errors->first('contact') }}</p>
        @endif

        <form class="contact-form" method="post" action="{{ route('contact.submit') }}">
            @csrf

            <div class="contact-form__field">
                <label for="contact-name">Name</label>
                <input id="contact-name" name="name" type="text" maxlength="160" required value="{{ old('name') }}">
                @error('name')<p class="contact-form__error">{{ $message }}</p>@enderror
            </div>

            <div class="contact-form__field">
                <label for="contact-email">E-Mail</label>
                <input id="contact-email" name="email" type="email" maxlength="320" required value="{{ old('email') }}">
                @error('email')<p class="contact-form__error">{{ $message }}</p>@enderror
            </div>

            <div class="contact-form__field">
                <label for="contact-message">Nachricht</label>
                <textarea id="contact-message" name="message" maxlength="5000" rows="6" required>{{ old('message') }}</textarea>
                @error('message')<p class="contact-form__error">{{ $message }}</p>@enderror
            </div>

            <div class="contact-form__honeypot" aria-hidden="true">
                <label for="contact-company">Company</label>
                <input id="contact-company" name="company" type="text" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit">Nachricht senden</button>
        </form>
    @endif
</section>
