    @php
        $preview = app(\App\Domain\Content\SitePreviewContext::class);
        $illustratedIndex = 0;
    @endphp
    <section class="blog-page" aria-label="Blog posts">
        @foreach ($posts as $post)
            @php
                $entrySection = ($embeddedListing ?? false) ? $post->siteSection : $section;
                $postUrl = $preview->url(
                    $entrySection->isHome()
                        ? route('home.journal.show', ['slug' => $post->getKey()])
                        : route('journal.show', ['section' => $entrySection->slug, 'slug' => $post->getKey()])
                );
                $cover = $post->mediaUsages->firstWhere('role', \App\Models\JournalEntryMedia::ROLE_COVER);
                $hasCover = $cover instanceof \App\Models\JournalEntryMedia;
                if ($hasCover) {
                    $illustratedIndex++;
                }
                $reverseCover = $hasCover && $illustratedIndex % 2 === 0;
                $postDate = $post->published_at ?? $post->scheduled_at;
                $excerpt = trim((string) ($post->excerpt ?? ''));
                $excerptFallback = $excerpt === '';
                if ($excerptFallback) {
                    $excerpt = \Illuminate\Support\Str::limit(
                        \App\Domain\Content\RichTextDocument::plainText((string) ($post->body ?? '')), 220,
                    );
                }
            @endphp
            <article class="blog-entry @if ($hasCover) blog-entry--illustrated @if ($reverseCover) blog-entry--reverse @endif @else blog-entry--text-only @endif">
                <div class="blog-entry__body">
                    @if ($hasCover)
                        <div class="blog-entry__mount">
                            <a href="{{ $postUrl }}" class="blog-entry__cover" aria-label="Read {{ $post->title }}">
                                {!! $journalMedia->render($cover, 'journal-entry-media journal-entry-media--listing-cover', $loop->first, includeInheritedCopyright: false, includeCopyrightNotice: false, showCaption: false) !!}
                            </a>
                        </div>
                    @endif
                    <header class="blog-entry__header">
                        @if ($postDate instanceof \DateTimeInterface)
                            <time class="blog-entry__date" datetime="{{ $postDate->format('Y-m-d') }}">{{ \App\Domain\Content\RegionalFormats::publicDate($postDate) }}</time>
                        @endif
                        <h2 class="blog-entry__title"><a href="{{ $postUrl }}">{{ $post->title }}</a></h2>
                    </header>
                    @if ($excerpt !== '')
                        <div class="blog-entry__excerpt {{ $excerptFallback ? '' : 'rich-text' }}">{!! $excerptFallback ? e($excerpt) : $richText->render($excerpt) !!}</div>
                    @endif
                    <a class="blog-entry__read-more" href="{{ $postUrl }}">Read more <x-public-icon name="arrow-right" /></a>
                </div>
            </article>
        @endforeach
    </section>
