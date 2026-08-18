@props(['settings', 'showStatus' => true])

<section class="contact-section" aria-labelledby="contact-heading">
    <h2 id="contact-heading" class="category-heading">Contact</h2>

    @if ($settings->public_email !== null || $settings->instagram_handle !== null)
        <div class="contact-details" aria-label="Contact details">
            @if ($settings->public_email !== null)
                <div class="contact-details__row">
                    <span class="contact-details__label">E-Mail</span>
                    <a href="mailto:{{ $settings->public_email }}">{{ $settings->public_email }}</a>
                </div>
            @endif
            @if ($settings->instagram_handle !== null)
                <div class="contact-details__row">
                    <span class="contact-details__label">Instagram</span>
                    <a href="https://www.instagram.com/{{ $settings->instagram_handle }}/" rel="noopener noreferrer">{{ $settings->instagram_handle }}</a>
                </div>
            @endif
        </div>
    @endif

    @if ($settings->contact_state === 'hidden' || $settings->contact_state === 'under_construction')
        @if ($showStatus)
            <p class="contact-status contact-status--quiet" role="status">
                @if ($settings->contact_state === 'under_construction' && $settings->contact_status_text)
                    {{ $settings->contact_status_text }}
                @else
                    Direct messages through the website are not active yet.
                @endif
            </p>
        @endif

        <div class="contact-form contact-form--preview" role="group" aria-disabled="true">
            <div class="contact-form__field">
                <label for="contact-name-preview">Name</label>
                <input id="contact-name-preview" type="text" disabled>
            </div>

            <div class="contact-form__field">
                <label for="contact-email-preview">E-Mail</label>
                <input id="contact-email-preview" type="email" disabled>
            </div>

            <div class="contact-form__field">
                <label for="contact-comment-preview">Nachricht</label>
                <textarea id="contact-comment-preview" rows="6" disabled></textarea>
            </div>

            <button type="button" disabled>Nachricht senden</button>
        </div>
    @elseif ($settings->contact_state === 'enabled')
        @if (session('contact_success'))
            <p class="contact-message" role="status">{{ session('contact_success') }}</p>
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
                <label for="contact-comment">Nachricht</label>
                <textarea id="contact-comment" name="comment" maxlength="5000" rows="6" required>{{ old('comment') }}</textarea>
                @error('comment')<p class="contact-form__error">{{ $message }}</p>@enderror
            </div>

            <div class="contact-form__honeypot" aria-hidden="true">
                <label for="contact-company">Company</label>
                <input id="contact-company" name="company" type="text" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit">Nachricht senden</button>
        </form>
    @endif
</section>
