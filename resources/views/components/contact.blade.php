<section class="contact-section" aria-labelledby="contact-heading">
    <h2 id="contact-heading" class="category-heading">Contact</h2>

    @if ($settings->contact_state === 'under_construction')
        <div class="contact-status" role="status">
            <span class="contact-status__icon" aria-hidden="true">
                @switch($settings->contact_icon)
                    @case('mail') @ @break
                    @case('info') i @break
                    @default ×
                @endswitch
            </span>
            <p>{{ $settings->contact_status_text }}</p>
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
                <label for="contact-email">Email</label>
                <input id="contact-email" name="email" type="email" maxlength="320" required value="{{ old('email') }}">
                @error('email')<p class="contact-form__error">{{ $message }}</p>@enderror
            </div>

            <div class="contact-form__field">
                <label for="contact-website">Website</label>
                <input id="contact-website" name="website" type="url" maxlength="2048" value="{{ old('website') }}">
                @error('website')<p class="contact-form__error">{{ $message }}</p>@enderror
            </div>

            <div class="contact-form__field">
                <label for="contact-comment">Comment</label>
                <textarea id="contact-comment" name="comment" maxlength="5000" rows="7" required>{{ old('comment') }}</textarea>
                @error('comment')<p class="contact-form__error">{{ $message }}</p>@enderror
            </div>

            <div class="contact-form__honeypot" aria-hidden="true">
                <label for="contact-company">Company</label>
                <input id="contact-company" name="company" type="text" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit">Send</button>
        </form>
    @endif
</section>
